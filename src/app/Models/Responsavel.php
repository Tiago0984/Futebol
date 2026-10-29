<?php

namespace App\Models;

use App\Models\Concerns\AcessaOApp;
use App\Support\Cpf;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

// Authenticatable e HasApiTokens: o responsável é o segundo perfil de login do app (Fase 9)
class Responsavel extends Authenticatable
{
    use HasApiTokens, AcessaOApp;

    public const PERFIL       = 'responsavel';
    public const BROKER       = 'responsaveis';
    public const COLUNA_EMAIL = 'email_responsavel';

    protected $table = 'tbl_responsavel';
    protected $primaryKey = 'id_responsavel';
    public $timestamps = false;

    // Senha do login (o padrão do Laravel é a coluna "password")
    protected $authPasswordName = 'senha_responsavel';

    // Opções de tbl_atleta_responsavel.grau_parentesco_responsavel (varchar(20)), iguais no site e no admin.
    // O valor gravado é o próprio texto exibido.
    public const GRAUS_PARENTESCO = ['Pai', 'Mãe', 'Avô', 'Avó', 'Tio', 'Tia', 'Responsável Legal', 'Outro'];

    protected $fillable = [
        'nome_responsavel',
        'cpf_responsavel',
        'rg_responsavel',
        'telefone_responsavel',
        'whatsapp_responsavel',
        'email_responsavel',
        'assinatura_responsavel',
        'aceite_responsavel',
        'id_endereco',
    ];

    // senha_responsavel fica fora do $fillable e nunca vai para o JSON: só o fluxo de senha (Fase 9) grava
    protected $hidden = [
        'senha_responsavel',
    ];

    // E-mail sempre gravado normalizado (minúsculas, sem espaços; vazio vira null): é o login do responsável
    protected function emailResponsavel(): Attribute
    {
        return Attribute::make(set: fn ($valor) => self::normalizarEmail($valor));
    }

    public static function normalizarEmail(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return $email === '' ? null : $email;
    }

    // CPF sempre gravado como 000.000.000-00 (21/10/2026; App\Support\Cpf)
    protected function cpfResponsavel(): Attribute
    {
        return Attribute::make(set: fn ($valor) => Cpf::formatar($valor));
    }

    // Só os dígitos do CPF ("034.466.148-26" → "03446614826"): os cadastros antigos tinham formatos diferentes
    public static function digitosCpf(?string $cpf): string
    {
        return Cpf::digitos($cpf);
    }

    /**
     * Responsável já cadastrado com esse CPF, comparando só os dígitos (cadastro do site e do admin
     * reaproveitam em vez de criar outro). Com repetidos antigos, o mais antigo.
     */
    public static function porCpf(?string $cpf, ?int $exceto = null): ?self
    {
        $digitos = self::digitosCpf($cpf);

        if ($digitos === '') {
            return null;
        }

        return self::whereRaw("REGEXP_REPLACE(cpf_responsavel, '[^0-9]', '') = ?", [$digitos])
            ->when($exceto, fn ($q) => $q->where('id_responsavel', '<>', $exceto))
            ->orderBy('id_responsavel')
            ->first();
    }

    public function endereco()
    {
        return $this->belongsTo(Endereco::class, 'id_endereco', 'id_endereco');
    }

    public function atletas()
    {
        return $this->belongsToMany(Atleta::class, 'tbl_atleta_responsavel', 'id_responsavel', 'id_atleta')
                    ->withPivot('grau_parentesco_responsavel');
    }

    public function autorizacoes()
    {
        return $this->hasMany(Autorizacao::class, 'id_responsavel', 'id_responsavel');
    }

    // Os filhos que contam no app: só atleta ATIVO (como nas listas e nas notificações)
    public function atletasAtivos()
    {
        return $this->atletas()->where('tbl_atletas.status_atleta', 'ATIVO');
    }

    public function historico()
    {
        return $this->hasMany(ResponsavelHistorico::class, 'id_responsavel', 'id_responsavel')
            ->orderByDesc('data_responsavel_historico')
            ->orderByDesc('id_responsavel_historico');
    }

    // ---------- dados mudados pelo app (29/10/2026) ----------

    /**
     * Os filhos que moram no mesmo endereço do responsável (o app pergunta se a mudança vale também para eles): os que
     * usam o MESMO registro (o cadastro pelo painel grava um só para o atleta e o responsável) e, desde 30/10/2026, os
     * que têm outro registro com o MESMO endereço escrito (o cadastro pelo site grava um para cada). Com o endereço
     * carregado (id_endereco).
     */
    public function filhosNoMesmoEndereco(): Collection
    {
        $meu = $this->endereco;
        if (! $meu) {
            return collect();
        }

        $texto = Endereco::normalizar($meu->only(Endereco::CAMPOS));

        return $this->atletas()->with('endereco')->orderBy('nome_atleta')
            ->get(['tbl_atletas.id_atleta', 'nome_atleta', 'tbl_atletas.id_endereco'])
            ->filter(fn (Atleta $filho) => $filho->endereco && (
                $filho->id_endereco === $meu->id_endereco
                || Endereco::normalizar($filho->endereco->only(Endereco::CAMPOS)) === $texto
            ))->values();
    }

    /**
     * PATCH /api/v1/responsavel: grava os campos (já validados) e o endereço, e registra cada mudança real no histórico
     * (id_usuario NULL = pelo app), numa transação. Endereço: com $dosFilhos = true, o responsável e os filhos de
     * filhosNoMesmoEndereco() recebem o novo (com uma linha no histórico de cada filho); com false, só o responsável.
     * Nunca muda o endereço de quem não está na mudança (gravarEndereco).
     */
    public function atualizarPeloApp(array $dados, ?array $endereco, bool $dosFilhos): void
    {
        DB::transaction(function () use ($dados, $endereco, $dosFilhos) {
            $campos = array_keys(array_diff_key(ResponsavelHistorico::CAMPOS, ['endereco' => true]));
            $antes = collect($campos)->mapWithKeys(fn ($c) => [$c => $this->{$c}]);

            $this->update($dados);

            $mudancas = $antes->filter(fn ($valor, $campo) => (string) $valor !== (string) $this->{$campo})
                ->map(fn ($valor, $campo) => [$valor, $this->{$campo}])->all();

            if ($endereco !== null && ($textos = $this->gravarEndereco(Endereco::normalizar($endereco), $dosFilhos))) {
                $mudancas['endereco'] = $textos;
            }

            foreach ($mudancas as $campo => [$antigo, $novo]) {
                $this->historico()->create([
                    'campo_responsavel_historico'        => $campo,
                    'valor_antigo_responsavel_historico' => $antigo === null ? null : (string) $antigo,
                    'valor_novo_responsavel_historico'   => $novo === null ? null : (string) $novo,
                    'id_usuario'                         => null,
                ]);
            }
        });
    }

    /**
     * Grava o endereço novo; devolve [texto antigo, texto novo] do responsável, ou null se nada mudou. Quem muda: o
     * responsável e, com $dosFilhos, os filhos de filhosNoMesmoEndereco(). Cada registro de endereço envolvido é mudado
     * no lugar só se todos que o usam estão na mudança; senão, quem muda ganha um registro novo e o resto fica como
     * estava (ex.: o pai que divide o registro com o filho, quando é a mãe quem muda sem levar o filho).
     */
    private function gravarEndereco(array $novo, bool $dosFilhos): ?array
    {
        $atual = $this->endereco;
        $antigo = $atual ? Endereco::normalizar($atual->only(Endereco::CAMPOS)) : null;

        if ($antigo === $novo) {
            return null;
        }

        if (! $atual) {
            $this->forceFill(['id_endereco' => Endereco::create($novo)->id_endereco])->save();
            $this->unsetRelation('endereco');

            return [null, Endereco::texto($novo)];
        }

        $filhos = $dosFilhos ? $this->filhosNoMesmoEndereco() : collect();

        // Quem muda, por registro de endereço: [id_endereco => ['responsaveis' => [ids], 'atletas' => [ids]]]
        $grupos = [$atual->id_endereco => ['responsaveis' => [$this->id_responsavel], 'atletas' => []]];
        foreach ($filhos as $filho) {
            $grupos[$filho->id_endereco] ??= ['responsaveis' => [], 'atletas' => []];
            $grupos[$filho->id_endereco]['atletas'][] = $filho->id_atleta;
        }

        foreach ($grupos as $idEndereco => $quem) {
            $usadoPorOutros = self::where('id_endereco', $idEndereco)->whereNotIn('id_responsavel', $quem['responsaveis'])->exists()
                || Atleta::where('id_endereco', $idEndereco)->whereNotIn('id_atleta', $quem['atletas'] ?: [0])->exists();

            if ($usadoPorOutros) {
                $idNovo = Endereco::create($novo)->id_endereco;
                self::whereIn('id_responsavel', $quem['responsaveis'] ?: [0])->update(['id_endereco' => $idNovo]);
                Atleta::whereIn('id_atleta', $quem['atletas'] ?: [0])->update(['id_endereco' => $idNovo]);
            } else {
                Endereco::whereKey($idEndereco)->update($novo);
            }
        }
        $this->refresh(); // id_endereco e o endereço novos (o update em massa não passa pelo objeto)

        foreach ($filhos as $filho) {
            AtletaHistorico::create([
                'id_atleta'                     => $filho->id_atleta,
                'campo_atleta_historico'        => AtletaHistorico::CAMPO_ENDERECO,
                'valor_antigo_atleta_historico' => Endereco::texto(Endereco::normalizar($filho->endereco->only(Endereco::CAMPOS))),
                'valor_novo_atleta_historico'   => Endereco::texto($novo),
                'id_usuario'                    => null, // pelo app, do responsável
            ]);
        }

        return [Endereco::texto($antigo), Endereco::texto($novo)];
    }

    // ---------- acesso ao app (trait AcessaOApp) ----------

    // Responsável entra no app enquanto tiver algum filho ATIVO
    public function podeEntrarNoApp(): bool
    {
        return $this->atletasAtivos()->exists();
    }

    public function nomeNoApp(): string
    {
        return (string) $this->nome_responsavel;
    }

    public function semSenha(): bool
    {
        return blank($this->senha_responsavel);
    }

    protected function gravarSenha(string $hash): void
    {
        $this->forceFill(['senha_responsavel' => $hash])->save();
    }

    /**
     * Converte um grau gravado em outro formato ("PAI", "MAE") para a opção da lista ("Pai", "Mãe").
     * Primeiro compara sem diferenciar maiúsculas; depois, também sem acentos. Valor desconhecido
     * volta como está. Sem acento, "AVO" é ambíguo (Avô/Avó) e vira a primeira opção, Avô.
     */
    public static function normalizarGrau(?string $grau): ?string
    {
        if (blank($grau)) {
            return null;
        }

        $maiusculo = fn (string $texto) => mb_strtoupper(trim($texto));
        $semAcento = fn (string $texto) => Str::ascii($maiusculo($texto));

        foreach ([$maiusculo, $semAcento] as $comparar) {
            foreach (self::GRAUS_PARENTESCO as $opcao) {
                if ($comparar($opcao) === $comparar($grau)) {
                    return $opcao;
                }
            }
        }

        return $grau;
    }
}
