<?php

namespace App\Models;

use App\Models\Concerns\SerializaDatasComFuso;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class Atleta extends Authenticatable
{
    // HasApiTokens: permite gerar e gerenciar tokens do Sanctum (login pela API)
    // SerializaDatasComFuso: data_nasc_atleta no JSON da API como 2014-01-01T00:00:00-02:00
    use HasApiTokens, SerializaDatasComFuso;

    protected $table = 'tbl_atletas';
    protected $primaryKey = 'id_atleta';
    public $timestamps = false;

    // Valores de tbl_categoria_atleta.status_categoria_atleta
    public const CATEGORIA_ATIVA     = 'ATIVO';
    public const CATEGORIA_ENCERRADA = 'ENCERRADO';

    // Status de quem já passou pela aprovação da matrícula; só entre eles o admin alterna.
    // PENDENTE e REJEITADO só mudam pela tela de Matrículas.
    public const STATUS_APROVADOS = ['ATIVO', 'INATIVO'];

    // Faixa atendida pela escolinha, contada pelo ano (idade = ano atual − ano de nascimento)
    public const IDADE_MINIMA = 9;
    public const IDADE_MAXIMA = 17;

    protected $fillable = [
        'nome_atleta',
        'data_nasc_atleta',
        'rg_atleta',
        'cpf_atleta',
        'numero_matricula_atleta',
        'posicao_atleta',
        'telefone_atleta',
        'peso_atleta',
        'altura_atleta',
        'sexo_atleta',
        'escola_atleta',
        'serie_atleta',
        'descricao_atleta',
        'foto_atleta',
        'sala_atleta',
        'periodo_escolar_atleta',
        'status_atleta',
        'token_cadastro',
        'id_endereco',
        'email_atleta',
        'password',
        'remember_token',
    ];

    // Nunca devolver esses campos no JSON da API
    protected $hidden = [
        'password',
        'remember_token',
        'token_cadastro',
    ];

    protected $casts = [
        'data_nasc_atleta' => 'date',
        'peso_atleta'      => 'decimal:2',
        'altura_atleta'    => 'decimal:2',
    ];

    public function endereco()
    {
        return $this->belongsTo(Endereco::class, 'id_endereco', 'id_endereco');
    }

    public function responsaveis()
    {
        return $this->belongsToMany(Responsavel::class, 'tbl_atleta_responsavel', 'id_atleta', 'id_responsavel')
            ->withPivot('grau_parentesco_responsavel');
    }

    // Define que um Atleta pertence a muitos Times
    public function times()
    {
        return $this->belongsToMany(Time::class, 'tbl_atleta_time', 'id_atleta', 'id_time')
            ->withPivot([
                'status_atleta_time',
                'posicao_atleta_time',
                'camisa_atleta_time',
                'gols_atleta_time',
                'defesas_atleta_time',
                'jogos_atleta_time',
                'convocacao_atleta_time'
            ]);
    }

    /**
     * Próximo número de matrícula (A001, A002...). Leitura com lockForUpdate: dentro de uma transação,
     * um SELECT comum leria a foto antiga do banco e repetiria o mesmo MAX; a leitura com bloqueio vê o
     * último valor confirmado e faz um cadastro simultâneo esperar o outro terminar.
     */
    public static function proximoNumeroMatricula(): string
    {
        $maior = self::whereRaw("numero_matricula_atleta REGEXP '^A[0-9]+$'")
            ->selectRaw('MAX(CAST(SUBSTRING(numero_matricula_atleta, 2) AS UNSIGNED)) AS maior')
            ->lockForUpdate()
            ->value('maior');

        return 'A' . str_pad((string) (($maior ?? 0) + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Grava o número de matrícula se o atleta ainda não tiver (aprovação e cadastro pelo admin).
     * Se outro cadastro gravar o mesmo número no meio do caminho, o índice único recusa
     * e o número é recalculado, até 5 tentativas.
     */
    public function atribuirNumeroMatricula(): string
    {
        if ($this->numero_matricula_atleta) {
            return $this->numero_matricula_atleta;
        }

        for ($tentativa = 1; ; $tentativa++) {
            $numero = self::proximoNumeroMatricula();

            try {
                $this->update(['numero_matricula_atleta' => $numero]);

                return $numero;
            } catch (UniqueConstraintViolationException $e) {
                if ($tentativa >= 5) {
                    throw $e;
                }
            }
        }
    }

    public function foiAprovado(): bool
    {
        return in_array(strtoupper((string) $this->status_atleta), self::STATUS_APROVADOS, true);
    }

    /**
     * Datas de nascimento aceitas no cadastro (site e admin): de 9 a 17 anos no ano,
     * ou seja, de 1º/jan de (ano − 17) até 31/dez de (ano − 9). Inclui a mensagem de erro.
     */
    public static function limitesNascimento(?int $ano = null): array
    {
        $ano        ??= (int) now()->format('Y');
        $anoMaisVelho = $ano - self::IDADE_MAXIMA;
        $anoMaisNovo  = $ano - self::IDADE_MINIMA;

        return [
            'min'      => "{$anoMaisVelho}-01-01",
            'max'      => "{$anoMaisNovo}-12-31",
            'mensagem' => 'O atleta deve ter de ' . self::IDADE_MINIMA . ' a ' . self::IDADE_MAXIMA
                . " anos em {$ano} (nascido entre {$anoMaisVelho} e {$anoMaisNovo}).",
        ];
    }

    // Regras de validação de data_nasc_atleta, com as mensagens, para os controllers
    public static function regrasNascimento(): array
    {
        $limites = self::limitesNascimento();

        return [
            'regra'     => "required|date|after_or_equal:{$limites['min']}|before_or_equal:{$limites['max']}",
            'mensagens' => [
                'data_nasc_atleta.after_or_equal'  => $limites['mensagem'],
                'data_nasc_atleta.before_or_equal' => $limites['mensagem'],
            ],
        ];
    }

    // Histórico completo de categorias (linhas ativas e encerradas)
    public function categorias()
    {
        return $this->belongsToMany(Categoria::class, 'tbl_categoria_atleta', 'id_atleta', 'id_categoria')
            ->withPivot([
                'data_inicio_categoria_atleta',
                'data_fim_categoria_atleta',
                'data_atualizacao_categoria_atleta',
                'status_categoria_atleta',
                'observacao_categoria_atleta',
            ]);
    }

    // Atletas ATIVO com a categoria (linha ATIVO em tbl_categoria_atleta) informada, em ordem de nome
    public static function idsAtivosNaCategoria(int $idCategoria): array
    {
        return self::where('status_atleta', 'ATIVO')
            ->whereHas('categoriasAtivas', fn ($q) => $q->where('tbl_categoria.id_categoria', $idCategoria))
            ->orderBy('nome_atleta')
            ->pluck('id_atleta')
            ->all();
    }

    // Inscrições do atleta em eventos (sem status: só existem enquanto inscrito)
    public function inscricoesEmEventos()
    {
        return $this->hasMany(EventoAtleta::class, 'id_atleta', 'id_atleta');
    }

    // Notificações do atleta (Fase 8), da mais nova para a mais antiga
    public function notificacoes()
    {
        return $this->hasMany(Notificacao::class, 'id_atleta', 'id_atleta')
            ->orderByDesc('data_notificacao')
            ->orderByDesc('id_notificacao');
    }

    /**
     * Para o aviso de troca de categoria: eventos futuros e não cancelados da categoria antiga em que
     * o atleta está inscrito pela categoria (sair) e da nova em que ainda não está (entrar).
     * Inscrições individuais na categoria antiga ficam: foram escolha do admin.
     */
    public function eventosParaMoverInscricoes(int $idCategoriaAntiga, int $idCategoriaNova): array
    {
        $inscricoes = $this->inscricoesEmEventos()->get()->keyBy('id_evento_calendario');

        $sair = EventoCalendario::futurosAtivosDaCategoria($idCategoriaAntiga)
            ->filter(fn ($evento) => ($inscricoes[$evento->id_evento_calendario] ?? null)?->origem_evento_atleta === 'CATEGORIA')
            ->values();

        $entrar = EventoCalendario::futurosAtivosDaCategoria($idCategoriaNova)
            ->reject(fn ($evento) => isset($inscricoes[$evento->id_evento_calendario]))
            ->values();

        return ['sair' => $sair, 'entrar' => $entrar];
    }

    /**
     * "Mover inscrições" (confirmado pelo admin): sai dos eventos da categoria antiga e entra nos da nova.
     * O atleta recebe UMA notificação de resumo (AGENDA), não uma por evento.
     */
    public function moverInscricoes(int $idCategoriaAntiga, int $idCategoriaNova, ?int $idUsuario): array
    {
        return DB::transaction(function () use ($idCategoriaAntiga, $idCategoriaNova, $idUsuario) {
            ['sair' => $sair, 'entrar' => $entrar] = $this->eventosParaMoverInscricoes($idCategoriaAntiga, $idCategoriaNova);

            $saiu = [];
            foreach ($sair as $evento) {
                if ($evento->removerInscricao($this->id_atleta, $idUsuario, notificar: false)) {
                    $saiu[] = $evento->id_evento_calendario;
                }
            }

            $entrou = [];
            foreach ($entrar as $evento) {
                if ($evento->inscrever($this->id_atleta, 'CATEGORIA', $idUsuario, notificar: false)) {
                    $entrou[] = $evento->id_evento_calendario;
                }
            }

            $notificados = Notificacao::inscricoesMovidas($this->id_atleta, $idCategoriaAntiga, $idCategoriaNova, $saiu, $entrou, $idUsuario);

            return ['sairam' => count($saiu), 'entraram' => count($entrou), 'notificados' => $notificados];
        });
    }

    // Só a categoria atual (no máximo uma linha ATIVO)
    public function categoriasAtivas()
    {
        return $this->categorias()->wherePivot('status_categoria_atleta', self::CATEGORIA_ATIVA);
    }

    /**
     * Troca a categoria do atleta sem apagar histórico: encerra a linha ativa (data_fim + ENCERRADO)
     * e abre uma nova. Escolher a mesma categoria não faz nada; null só encerra a atual.
     * A validação (sexo, idade, motivo) fica com quem chama: Categoria::erroParaAtleta().
     */
    public function trocarCategoria(?int $idCategoria, ?string $observacao = null): void
    {
        $atual = DB::table('tbl_categoria_atleta')
            ->where('id_atleta', $this->id_atleta)
            ->where('status_categoria_atleta', self::CATEGORIA_ATIVA)
            ->value('id_categoria');

        if ($atual !== null && (int) $atual === $idCategoria) {
            return;
        }

        DB::transaction(function () use ($idCategoria, $observacao) {
            DB::table('tbl_categoria_atleta')
                ->where('id_atleta', $this->id_atleta)
                ->where('status_categoria_atleta', self::CATEGORIA_ATIVA)
                ->update([
                    'data_fim_categoria_atleta' => now(),
                    'status_categoria_atleta'   => self::CATEGORIA_ENCERRADA,
                ]);

            if ($idCategoria !== null) {
                DB::table('tbl_categoria_atleta')->insert([
                    'id_categoria'                 => $idCategoria,
                    'id_atleta'                    => $this->id_atleta,
                    'data_inicio_categoria_atleta' => now(),
                    'status_categoria_atleta'      => self::CATEGORIA_ATIVA,
                    'observacao_categoria_atleta'  => $observacao,
                ]);
            }
        });

        $this->unsetRelation('categorias')->unsetRelation('categoriasAtivas');
    }

    public function cartoes()
    {
        return $this->hasMany(Cartao::class, 'id_atleta', 'id_atleta');
    }

    public function autorizacoes()
    {
        return $this->hasMany(Autorizacao::class, 'id_atleta', 'id_atleta');
    }

    // Situação da autorização para as telas de Matrículas:
    // ASSINADA (alguma assinada), PENDENTE (há link para assinar) ou SEM (nenhum registro, ou pendente sem link)
    public function situacaoAutorizacao(): string
    {
        if ($this->autorizacoes->contains('status_autorizacao', 'ASSINADO')) {
            return 'ASSINADA';
        }

        return $this->autorizacoes->contains(fn ($a) => filled($a->token_assinatura)) ? 'PENDENTE' : 'SEM';
    }

    // Autorização que a tela mostra: a assinada, senão a pendente mais recente com link
    public function autorizacaoAtual(): ?Autorizacao
    {
        return $this->autorizacoes->firstWhere('status_autorizacao', 'ASSINADO')
            ?? $this->autorizacoes->sortByDesc('id_autorizacao')->first(fn ($a) => filled($a->token_assinatura))
            ?? $this->autorizacoes->sortByDesc('id_autorizacao')->first();
    }

    // Motivo que impede aprovar a matrícula, ou null se pode aprovar. Vale para o servidor e para as telas.
    public function bloqueioAprovacao(): ?string
    {
        $limites = self::limitesNascimento();
        $nascimento = $this->data_nasc_atleta ? substr((string) $this->data_nasc_atleta, 0, 10) : null;

        if (! $nascimento || $nascimento < $limites['min'] || $nascimento > $limites['max']) {
            return $limites['mensagem'];
        }

        return match ($this->situacaoAutorizacao()) {
            'ASSINADA' => null,
            'PENDENTE' => 'Aguardando a assinatura da autorização pelo responsável.',
            default    => 'Sem autorização do responsável: reative a matrícula para gerar o link de assinatura.',
        };
    }

    /**
     * Garante uma autorização pendente com link para o atleta que ainda não tem assinada
     * (usado ao reativar uma matrícula). Retorna a autorização com link, ou null se o atleta
     * não tem responsável para assinar.
     */
    public function garantirAutorizacaoPendente(): ?Autorizacao
    {
        $this->load('autorizacoes');

        if ($this->situacaoAutorizacao() !== 'SEM') {
            return $this->autorizacaoAtual();
        }

        // Pendente antiga sem token: só ganha o link, sem criar outra linha
        if ($semToken = $this->autorizacoes->sortByDesc('id_autorizacao')->first()) {
            $semToken->update(['token_assinatura' => Str::random(60)]);
            return $semToken;
        }

        $responsavel = $this->responsaveis()->first();
        if (! $responsavel) {
            return null;
        }

        return $this->autorizacoes()->create([
            'id_responsavel'              => $responsavel->id_responsavel,
            'data_assinatura_autorizacao' => now(),
            'token_assinatura'            => Str::random(60),
            'status_autorizacao'          => 'PENDENTE',
        ]);
    }

    /**
     * URL da foto, ou null quando não há foto (a tela mostra as iniciais).
     * O cadastro do site grava no disco public ('atletas/arquivo.jpg', servido em /storage);
     * o do admin grava só o nome do arquivo em public/futebol/images/our-teams,
     * e 'default-player.jpg' quando não envia foto.
     */
    public function urlFoto(): ?string
    {
        $foto = $this->foto_atleta;

        if (blank($foto) || $foto === 'default-player.jpg') {
            return null;
        }

        return str_contains($foto, '/')
            ? asset('storage/' . $foto)
            : asset('futebol/images/our-teams/' . $foto);
    }

    // Exclui o atleta e todos os vínculos dele (as FKs não têm ON DELETE CASCADE).
    // Atleta com cartões não é excluído, para não apagar o histórico dos jogos:
    // retorna false e quem chamou deve avisar o usuário.
    public function excluirComDependencias(): bool
    {
        if ($this->cartoes()->exists()) {
            return false;
        }

        DB::transaction(function () {
            $this->responsaveis()->detach();
            $this->categorias()->detach();
            $this->times()->detach();
            $this->autorizacoes()->delete();
            $this->inscricoesEmEventos()->delete(); // tbl_evento_atleta (FK sem cascade)
            $this->notificacoes()->delete(); // tbl_notificacao (FK sem cascade)
            $this->tokens()->delete(); // tokens do Sanctum (personal_access_tokens não tem FK)
            $this->delete();
        });

        return true;
    }
}
