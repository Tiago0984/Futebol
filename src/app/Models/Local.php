<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Local de treino, jogo ou evento (Etapa L1, 30/10/2026): nome, endereço e o link do Google Maps opcional, para o
 * "Abrir mapa" do app achar o lugar certo (o texto livre "Quadra A" não acha nada). Ativo/inativo, como a comissão
 * técnica: o desativado sai das novas escolhas e continua nos eventos em que já está (L2). Exclusão só do local que
 * nada usa (cadastro errado, de teste ou repetido; dono do projeto, 29/10/2026).
 */
class Local extends Model
{
    protected $table = 'tbl_local';
    protected $primaryKey = 'id_local';
    public $timestamps = false;

    protected $fillable = [
        'nome_local',
        'rua_local',
        'numero_local',
        'complemento_local',
        'bairro_local',
        'cidade_local',
        'estado_local',
        'cep_local',
        'mapa_url_local',
    ];

    // Endereços aceitos no link do mapa (o link abre no celular dos atletas, que são menores): só o Google Maps,
    // inclusive o link curto do "Compartilhar". [host => começo do caminho exigido]
    private const HOSTS_DO_MAPA = [
        'maps.app.goo.gl'   => '/',
        'goo.gl'            => '/maps',
        'maps.google.com'   => '/',
        'maps.google.com.br' => '/',
        'google.com'        => '/maps',
        'www.google.com'    => '/maps',
        'google.com.br'     => '/maps',
        'www.google.com.br' => '/maps',
    ];

    // As tabelas que guardam o local: [tabela, coluna do texto] (o texto é a cópia do nome do local escolhido)
    public const USOS = [
        'tbl_evento_calendario' => 'local_evento_calendario',
        'tbl_grade_treino'      => 'local_grade_treino',
        'tbl_campeonato'        => 'local_evento',
    ];

    // Valor do select para manter o texto livre de antes num evento, linha da grade ou campeonato que ainda o tem
    public const TEXTO_ANTIGO = 'texto-antigo';

    protected static function booted(): void
    {
        // Renomear leva o nome novo para tudo o que usa o local (decisão do dono do projeto, 30/10/2026): sem aviso
        // aos atletas e sem histórico, porque o lugar é o mesmo
        static::updated(function (Local $local) {
            if ($local->wasChanged('nome_local')) {
                foreach (self::USOS as $tabela => $coluna) {
                    DB::table($tabela)->where('id_local', $local->id_local)->update([$coluna => $local->nome_local]);
                }
            }
        });
    }

    public function scopeAtivos($query)
    {
        return $query->where('status_local', 'ATIVO');
    }

    // Ativos para o select "Local", pelo nome (uma consulta por select; sem once(), que guardaria a lista entre
    // requisições do mesmo processo, como nos testes)
    public static function paraEscolher(): Collection
    {
        return self::ativos()->orderBy('nome_local')->get();
    }

    /**
     * O que gravar a partir do select "Local": [id_local, texto do local], ou a mensagem de erro. Vazio = sem local;
     * TEXTO_ANTIGO = mantém o texto que o registro já tem (sem local); um id = o local (ativo, ou o inativo que já
     * estava: continua onde estava, mas não é escolhido de novo), com o nome dele no texto.
     */
    public static function daEscolha(?string $valor, ?int $idAtual, ?string $textoAtual): array|string
    {
        if ($valor === null || $valor === '') {
            return [null, null];
        }

        if ($valor === self::TEXTO_ANTIGO) {
            return ! $idAtual && filled($textoAtual) ? [null, $textoAtual] : 'Escolha um local da lista.';
        }

        if (! ctype_digit($valor)) {
            return 'Escolha um local da lista.';
        }

        $local = self::find((int) $valor);
        if ($local && ($local->id_local === $idAtual || $local->estaAtivo())) {
            return [$local->id_local, $local->nome_local];
        }

        return 'Escolha um local ativo da lista.';
    }

    public function estaAtivo(): bool
    {
        return $this->status_local === 'ATIVO';
    }

    // CEP gravado como 00000-000 (vazio = null), UF em maiúsculas
    public function setCepLocalAttribute($valor): void
    {
        $digitos = preg_replace('/\D/', '', (string) $valor);
        $this->attributes['cep_local'] = $digitos === '' ? null : substr($digitos, 0, 5) . '-' . substr($digitos, 5);
    }

    public function setEstadoLocalAttribute($valor): void
    {
        $this->attributes['estado_local'] = mb_strtoupper(trim((string) $valor));
    }

    // "Rua X, 123, Portão 2 – Centro, São Carlos/SP, 13560-000" (sem CEP, termina na UF)
    public function enderecoEmUmaLinha(): string
    {
        $linha = Endereco::texto([
            'rua_endereco'         => $this->rua_local,
            'numero_endereco'      => $this->numero_local,
            'complemento_endereco' => $this->complemento_local,
            'bairro_endereco'      => $this->bairro_local,
            'cidade_endereco'      => $this->cidade_local,
            'estado_endereco'      => $this->estado_local,
            'cep_endereco'         => $this->cep_local,
        ]);

        return rtrim($linha, ', ');
    }

    // O link cadastrado ou, sem ele, a busca do Google Maps com o nome e o endereço (o nome ajuda a achar o lugar)
    public function mapaUrl(): string
    {
        return $this->mapa_url_local
            ?: 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($this->nome_local . ', ' . $this->enderecoEmUmaLinha());
    }

    // Link https do Google Maps (sem usuário e senha no endereço, que esconderiam outro site)
    public static function linkDoGoogleMaps(?string $url): bool
    {
        $partes = parse_url(trim((string) $url));
        if (! $partes || ($partes['scheme'] ?? '') !== 'https' || isset($partes['user']) || isset($partes['pass']) || isset($partes['port'])) {
            return false;
        }

        $inicio = self::HOSTS_DO_MAPA[mb_strtolower($partes['host'] ?? '')] ?? null;
        $caminho = $partes['path'] ?? '/';

        return $inicio !== null && str_starts_with($caminho, $inicio) && ($inicio !== '/' || strlen($caminho) > 1 || isset($partes['query']));
    }

    /**
     * Textos de local soltos (sem id_local) nos eventos, na grade e no local principal dos campeonatos, com quantas
     * vezes aparecem; os iguais sem diferenciar maiúsculas e acentos (a collation) contam juntos. $semCadastro = só os
     * que ainda não são o nome de um local: a lista de Locais oferece "Cadastrar" com o nome preenchido.
     *
     * @return Collection<int, object{texto: string, usos: int}>
     */
    public static function textosSoltos(bool $semCadastro = false): Collection
    {
        $usados = DB::query()->fromSub(
            collect(self::USOS)->map(fn ($coluna, $tabela) => DB::table($tabela)->whereNull('id_local')->selectRaw("TRIM({$coluna}) AS texto"))
                ->reduce(fn ($uniao, $consulta) => $uniao ? $uniao->unionAll($consulta) : $consulta),
            'usados'
        );

        return $usados->selectRaw('MIN(texto) AS texto, COUNT(*) AS usos')
            ->whereNotNull('texto')->where('texto', '<>', '')
            ->when($semCadastro, fn ($q) => $q->whereNotExists(fn ($s) => $s->from('tbl_local')->whereColumn('tbl_local.nome_local', 'usados.texto')))
            ->groupBy('texto')
            ->orderByDesc('usos')->orderBy('texto')
            ->get()
            ->map(fn ($linha) => (object) ['texto' => $linha->texto, 'usos' => (int) $linha->usos]);
    }

    public static function textosSemCadastro(): Collection
    {
        return self::textosSoltos(semCadastro: true);
    }

    // Quantos usos soltos (sem local) têm esse texto, por tabela: [tabela => quantidade]
    public static function usosDoTexto(string $texto): array
    {
        return collect(self::USOS)->map(fn ($coluna, $tabela) => DB::table($tabela)->whereNull('id_local')
            ->whereRaw("TRIM({$coluna}) = ?", [trim($texto)])->count())->all();
    }

    // Quantos eventos, linhas da grade e campeonatos usam cada local, numa consulta por tabela (a lista de Locais):
    // [id_local => [tabela => quantidade]]; local sem uso não aparece
    public static function usosPorLocal(): array
    {
        $usos = [];
        foreach (array_keys(self::USOS) as $tabela) {
            DB::table($tabela)->whereNotNull('id_local')->groupBy('id_local')
                ->selectRaw('id_local, COUNT(*) AS quantos')->pluck('quantos', 'id_local')
                ->each(function ($quantos, $idLocal) use (&$usos, $tabela) {
                    $usos[$idLocal] = ($usos[$idLocal] ?? array_fill_keys(array_keys(self::USOS), 0));
                    $usos[$idLocal][$tabela] = (int) $quantos;
                });
        }

        return $usos;
    }

    // Os usos deste local: [tabela => quantidade]
    public function usos(): array
    {
        return collect(self::USOS)->map(fn ($coluna, $tabela) => DB::table($tabela)->where('id_local', $this->id_local)->count())->all();
    }

    // "175 evento(s), 17 horário(s) da grade" a partir de [tabela => quantidade] (zeros fora)
    public static function descreverUsos(array $usos): string
    {
        return implode(', ', array_filter([
            ($usos['tbl_evento_calendario'] ?? 0) ? "{$usos['tbl_evento_calendario']} evento(s)" : null,
            ($usos['tbl_grade_treino'] ?? 0) ? "{$usos['tbl_grade_treino']} horário(s) da grade" : null,
            ($usos['tbl_campeonato'] ?? 0) ? "{$usos['tbl_campeonato']} campeonato(s)" : null,
        ]));
    }

    /**
     * "Ligar os N usos" (L2): os eventos, linhas da grade e campeonatos com esse texto e sem local passam a usar este
     * local, com o nome dele no texto. Sem aviso aos atletas e sem "Alterado" (é o mesmo lugar); no evento cujo texto
     * muda, uma linha no histórico ("Local: Campo da AACJ → Campo AACJ"). Devolve [tabela => quantidade].
     */
    public function ligarUsos(string $texto, ?int $idUsuario): array
    {
        return DB::transaction(function () use ($texto, $idUsuario) {
            $eventos = DB::table('tbl_evento_calendario')->whereNull('id_local')
                ->whereRaw('TRIM(local_evento_calendario) = ?', [trim($texto)])
                ->get(['id_evento_calendario', 'local_evento_calendario']);

            foreach ($eventos as $evento) {
                if ($evento->local_evento_calendario !== $this->nome_local) {
                    EventoHistorico::create([
                        'id_evento_calendario'          => $evento->id_evento_calendario,
                        'campo_evento_historico'        => 'local_evento_calendario',
                        'valor_antigo_evento_historico' => $evento->local_evento_calendario,
                        'valor_novo_evento_historico'   => $this->nome_local,
                        'id_usuario'                    => $idUsuario,
                        'data_evento_historico'         => now(),
                    ]);
                }
            }

            return collect(self::USOS)->map(fn ($coluna, $tabela) => DB::table($tabela)->whereNull('id_local')
                ->whereRaw("TRIM({$coluna}) = ?", [trim($texto)])
                ->update(['id_local' => $this->id_local, $coluna => $this->nome_local]))->all();
        });
    }
}
