<?php

namespace App\Models;

use App\Models\Concerns\SerializaDatasComFuso;
use App\Models\Concerns\UrlDeImagem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\Time;
use App\Models\Jogo;

class Campeonato extends Model
{
    use SerializaDatasComFuso; // datas no JSON da API: 2025-01-01T00:00:00-03:00
    use UrlDeImagem;

    // Endereço completo da logo e do banner no JSON da API (o banco guarda só o nome do arquivo) e o rótulo do
    // tipo ("Pontos Corridos"; 26/10/2026, pedido do app)
    protected $appends = ['logo_url', 'banner_url', 'tipo_label'];

    protected $table = 'tbl_campeonato';
    protected $primaryKey = 'id_campeonato';
    public $timestamps = false;

    // Valores de tipo_campeonato (gravados em maiúsculas, como os que já estão no banco) => rótulo
    public const TIPOS = [
        'LIGA'            => 'Liga',
        'COPA'            => 'Copa',
        'TORNEIO'         => 'Torneio',
        'MATA-MATA'       => 'Mata-mata',
        'PONTOS CORRIDOS' => 'Pontos Corridos',
        'AMISTOSO'        => 'Amistoso',
    ];

    protected $fillable = [
        'logo_evento',
        'banner_evento',
        'nome_campeonato',
        'organizador_campeonato',
        'descricao_campeonato',
        'tipo_campeonato',
        'data_inicio_campeonato',
        'data_fim_campeonato',
        'local_evento',
        'id_local', // o local principal do cadastro (L2); local_evento é a cópia do nome
        'id_categoria',
        'status_campeonato',
    ];

    protected $casts = [
        'data_inicio_campeonato' => 'datetime',
        'data_fim_campeonato'    => 'datetime',
    ];

    // Tipo sempre em maiúsculas (o select do formulário usa as chaves de TIPOS)
    public function setTipoCampeonatoAttribute($valor): void
    {
        $this->attributes['tipo_campeonato'] = $valor === null ? null : mb_strtoupper(trim($valor));
    }

    // Rótulo do tipo; valor antigo fora da lista aparece como está
    public function getTipoLabelAttribute(): string
    {
        return self::TIPOS[$this->tipo_campeonato] ?? (string) $this->tipo_campeonato;
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'id_categoria', 'id_categoria');
    }

    // Participantes, cada time uma vez (o mesmo time pode estar em várias categorias: Fase H, Etapa H1)
    public function times()
    {
        return $this->belongsToMany(Time::class, 'tbl_campeonato_time', 'id_campeonato', 'id_time')->distinct();
    }

    // ── Categorias e participantes por categoria (Fase H, Etapa H1) ─────────

    /**
     * Categorias do campeonato (tbl_campeonato_categoria), na ordem dos formulários. Na tela todas valem igual
     * (sem "principal"); a de referência (tbl_campeonato.id_categoria, categoriaDeReferencia) está sempre entre
     * elas e é a que vale para a idade, a convocação e os jogos até a Etapa H3, quando o jogo ganha a categoria dele.
     */
    // Local principal (Etapa L2; inativo continua aqui): sugestão para o jogo criado sem local
    public function local()
    {
        return $this->belongsTo(Local::class, 'id_local', 'id_local');
    }

    public function categorias()
    {
        return $this->belongsToMany(Categoria::class, 'tbl_campeonato_categoria', 'id_campeonato', 'id_categoria')
            ->withPivot('classificados_campeonato_categoria', 'data_encerramento_campeonato_categoria')
            ->orderBy('sexo_categoria', 'desc')->orderBy('idade_min_categoria');
    }

    /**
     * Categoria de referência (tbl_campeonato.id_categoria) entre as marcadas, escolhida pelo sistema até a
     * Etapa H3: a atual, se continua marcada (acrescentar categoria não regrava os jogos); senão, a mais velha
     * (maior idade máxima; no empate, a masculina), para a idade não barrar quem joga numa categoria acima.
     */
    public static function categoriaDeReferencia(array $idsCategorias, ?int $atual = null): ?int
    {
        $idsCategorias = array_map('intval', $idsCategorias);
        if ($atual !== null && in_array($atual, $idsCategorias, true)) {
            return $atual;
        }

        $id = Categoria::whereIn('id_categoria', $idsCategorias)
            ->orderByDesc('idade_max_categoria')->orderByDesc('sexo_categoria')->value('id_categoria');

        return $id === null ? null : (int) $id;
    }

    // Categorias no JSON da API: [{id_categoria, nome_categoria, sexo_categoria, rotulo}]; com $ids, só essas
    // (campeonatos do atleta: só as dele)
    public function categoriasNaApi(?array $ids = null): array
    {
        return $this->categorias
            ->when($ids !== null, fn ($categorias) => $categorias->whereIn('id_categoria', $ids))
            ->map(fn ($categoria) => [
                'id_categoria'   => (int) $categoria->id_categoria,
                'nome_categoria' => $categoria->nome_categoria,
                'sexo_categoria' => $categoria->sexo_categoria,
                'rotulo'         => $categoria->rotulo,
            ])->values()->all();
    }

    // "Sub-11 Masculino, Sub-15 Masculino" (a lista de Campeonatos)
    public function getRotuloDasCategoriasAttribute(): string
    {
        return $this->categorias->pluck('rotulo')->implode(', ');
    }

    // Participantes de cada categoria: [id_categoria => [id_time, ...]]
    public function participantesPorCategoria(): array
    {
        return DB::table('tbl_campeonato_time')->where('id_campeonato', $this->id_campeonato)
            ->orderBy('id_categoria')->orderBy('id_time')->get(['id_categoria', 'id_time'])
            ->groupBy('id_categoria')
            ->map(fn ($linhas) => $linhas->pluck('id_time')->map(fn ($id) => (int) $id)->all())
            ->all();
    }

    /**
     * Grava as categorias ($idsCategorias, já com a de referência) e os participantes de cada uma
     * ($participantes: [id_categoria => [id_time, ...]]; categoria fora da lista é ignorada), numa transação.
     * Só sai quem saiu e só entra quem entrou: a convocação aponta para o participante (FK composta, Etapa H2),
     * então quem fica não pode ser apagado e regravado (e continua no grupo dele, Etapa H4). A FK composta do
     * participante só aceita categoria do campeonato. Os grupos da categoria que sai saem junto (os times dela
     * já saíram).
     */
    public function gravarCategoriasEParticipantes(array $idsCategorias, array $participantes): void
    {
        $idsCategorias = array_values(array_unique(array_map('intval', $idsCategorias)));

        $linhas = [];
        foreach ($idsCategorias as $idCategoria) {
            foreach (array_unique(array_map('intval', $participantes[$idCategoria] ?? [])) as $idTime) {
                $linhas["{$idCategoria}|{$idTime}"] = ['id_campeonato' => $this->id_campeonato, 'id_categoria' => $idCategoria, 'id_time' => $idTime];
            }
        }

        DB::transaction(function () use ($idsCategorias, $linhas) {
            $saem = DB::table('tbl_campeonato_time')->where('id_campeonato', $this->id_campeonato)
                ->get(['id_campeonato_time', 'id_categoria', 'id_time'])
                ->reject(fn ($linha) => isset($linhas["{$linha->id_categoria}|{$linha->id_time}"]))
                ->pluck('id_campeonato_time')->all();
            DB::table('tbl_campeonato_time')->whereIn('id_campeonato_time', $saem)->delete();
            DB::table('tbl_campeonato_grupo')->where('id_campeonato', $this->id_campeonato)
                ->whereNotIn('id_categoria', $idsCategorias)->delete();
            DB::table('tbl_campeonato_categoria')->where('id_campeonato', $this->id_campeonato)
                ->whereNotIn('id_categoria', $idsCategorias)->delete();

            DB::table('tbl_campeonato_categoria')->insertOrIgnore(array_map(
                fn ($idCategoria) => ['id_campeonato' => $this->id_campeonato, 'id_categoria' => $idCategoria], $idsCategorias));
            DB::table('tbl_campeonato_time')->insertOrIgnore(array_values($linhas));
        });

        $this->unsetRelation('categorias')->unsetRelation('times');
        $this->categoriasDosTimes = [];
    }

    /**
     * Participantes marcados numa categoria que não está no cadastro do time ($participantes: [id_categoria =>
     * [id_time]]): uma mensagem por caso. Desde 27/10/2026 o time tem as categorias de que participa marcadas na
     * tela de Times (a AACJ no Sub-13 e no Sub-15; o Botafogo onde joga), e só entra no campeonato nelas. Substitui
     * "um time da AACJ por categoria" e "time masculino não joga em categoria feminina" (marcar o feminino no
     * cadastro é a decisão de quem administra; pergunta 14).
     */
    public static function timesForaDasCategorias(array $participantes): array
    {
        $idsTimes = collect($participantes)->flatten()->map(fn ($id) => (int) $id)->unique()->all();
        if (! $idsTimes) {
            return [];
        }

        $times      = Time::with('categorias')->whereIn('id_time', $idsTimes)->get()->keyBy('id_time');
        $categorias = Categoria::whereIn('id_categoria', array_keys($participantes))->get()->keyBy('id_categoria');

        $erros = [];
        foreach ($participantes as $idCategoria => $idsDaCategoria) {
            foreach (array_map('intval', (array) $idsDaCategoria) as $idTime) {
                $time = $times->get($idTime);
                if ($time && ! $time->categorias->contains('id_categoria', (int) $idCategoria)) {
                    $erros[] = "O {$time->nome_time} não tem o " . ($categorias[$idCategoria]->rotulo ?? 'categoria')
                        . ' marcado no cadastro do time. Marque a categoria na tela de Times antes.';
                }
            }
        }

        return $erros;
    }

    /**
     * Categoria do time no campeonato (null se ele não participa nela), guardada por time e categoria. Desde
     * 27/10/2026 o time (a AACJ) pode estar em várias categorias do mesmo campeonato: a convocação informa qual
     * ($idCategoria). Sem ela, a primeira em que ele participa (o caso de uma só).
     */
    private array $categoriasDosTimes = [];

    public function categoriaDoTime(Time $time, ?int $idCategoria = null): ?Categoria
    {
        $chave = "{$time->id_time}|{$idCategoria}";
        if (! array_key_exists($chave, $this->categoriasDosTimes)) {
            $id = DB::table('tbl_campeonato_time')->where('id_campeonato', $this->id_campeonato)
                ->where('id_time', $time->id_time)
                ->when($idCategoria, fn ($q) => $q->where('id_categoria', $idCategoria))
                ->orderBy('id_categoria')->value('id_categoria');
            $this->categoriasDosTimes[$chave] = $id ? Categoria::find($id) : null;
        }

        return $this->categoriasDosTimes[$chave];
    }

    /**
     * Participações dos times da AACJ (INTERNO) no campeonato, uma por time e categoria, para o bloco Convocação:
     * cada uma com o time, a categoria e quantos atletas ativos ele convocou nela. Masculino antes, pela idade.
     */
    public function participacoesConvocaveis(): Collection
    {
        $linhas = DB::table('tbl_campeonato_time as ct')
            ->join('tbl_time as t', 't.id_time', '=', 'ct.id_time')
            ->where('ct.id_campeonato', $this->id_campeonato)->where('t.tipo_time', 'INTERNO')
            ->get(['ct.id_time', 'ct.id_categoria']);
        $times      = Time::whereIn('id_time', $linhas->pluck('id_time'))->get()->keyBy('id_time');
        $categorias = Categoria::whereIn('id_categoria', $linhas->pluck('id_categoria'))->get()->keyBy('id_categoria');
        $totais     = $this->convocacoes()->deAtivos()->groupBy('id_time', 'id_categoria')
            ->selectRaw('id_time, id_categoria, COUNT(*) AS total')->get()
            ->mapWithKeys(fn ($c) => ["{$c->id_time}|{$c->id_categoria}" => (int) $c->total]);

        return $linhas->map(fn ($l) => (object) [
            'time'      => $times[$l->id_time],
            'categoria' => $categorias[$l->id_categoria],
            'total'     => $totais["{$l->id_time}|{$l->id_categoria}"] ?? 0,
        ])->sortBy(fn ($p) => [$p->categoria->sexo_categoria === 'M' ? 0 : 1, (int) $p->categoria->idade_min_categoria, $p->time->nome_time])
            ->values();
    }

    // Tira participantes e categorias (exclusão do campeonato; as FKs são NO ACTION)
    public function apagarCategoriasEParticipantes(): void
    {
        DB::table('tbl_campeonato_time')->where('id_campeonato', $this->id_campeonato)->delete();
        DB::table('tbl_campeonato_grupo')->where('id_campeonato', $this->id_campeonato)->delete();
        DB::table('tbl_campeonato_categoria')->where('id_campeonato', $this->id_campeonato)->delete();
    }

    // ── Grupos e classificação (Fase H, Etapa H4) ──────────────────────────

    public function grupos()
    {
        return $this->hasMany(CampeonatoGrupo::class, 'id_campeonato', 'id_campeonato')->orderBy('nome_campeonato_grupo');
    }

    /**
     * Participantes de uma categoria com o grupo de cada um: coleção de {id_time, nome_time, logo_time,
     * tipo_time, id_campeonato_grupo}, pelo nome do time (todas as categorias, sem $idCategoria; com id_categoria).
     */
    public function participantesComGrupo(?int $idCategoria = null)
    {
        return DB::table('tbl_campeonato_time as ct')
            ->join('tbl_time as t', 't.id_time', '=', 'ct.id_time')
            ->where('ct.id_campeonato', $this->id_campeonato)
            ->when($idCategoria, fn ($q) => $q->where('ct.id_categoria', $idCategoria))
            ->orderBy('t.nome_time')
            ->get(['ct.id_categoria', 'ct.id_campeonato_grupo', 't.id_time', 't.nome_time', 't.logo_time', 't.tipo_time']);
    }

    /**
     * Põe os participantes da categoria nos grupos ($grupoPorTime: [id_time => id_grupo ou null = sem grupo]).
     * Só grupos daquela categoria do campeonato (o banco também confere, pela FK composta); time que não é
     * participante da categoria é ignorado. Devolve a mensagem de erro, ou null se gravou.
     */
    public function definirGrupos(int $idCategoria, array $grupoPorTime): ?string
    {
        $idsGrupos = $this->grupos()->where('id_categoria', $idCategoria)->pluck('id_campeonato_grupo')->map(fn ($id) => (int) $id)->all();
        foreach ($grupoPorTime as $idGrupo) {
            if ($idGrupo !== null && ! in_array((int) $idGrupo, $idsGrupos, true)) {
                return 'Escolha um grupo desta categoria do campeonato.';
            }
        }

        DB::transaction(function () use ($idCategoria, $grupoPorTime) {
            foreach ($grupoPorTime as $idTime => $idGrupo) {
                DB::table('tbl_campeonato_time')->where('id_campeonato', $this->id_campeonato)
                    ->where('id_categoria', $idCategoria)->where('id_time', (int) $idTime)
                    ->update(['id_campeonato_grupo' => $idGrupo === null ? null : (int) $idGrupo]);
            }
        });

        return null;
    }

    // Quantos passam de cada grupo (chave) na categoria, ou null = não definido (ajuste da Etapa H4, 15/10/2026)
    public const CLASSIFICADOS_MAX = 16;

    public function definirClassificados(int $idCategoria, ?int $classificados): void
    {
        DB::table('tbl_campeonato_categoria')->where('id_campeonato', $this->id_campeonato)->where('id_categoria', $idCategoria)
            ->update(['classificados_campeonato_categoria' => $classificados]);
        $this->unsetRelation('categorias');
    }

    /**
     * Campeão de cada categoria (Fase I, Etapa I4; dono do projeto, 21/10/2026), calculado (nada é gravado):
     * - com final (publicada e ativa): o vencedor da final (Jogo::resultadoDoConfronto, no jogo único ou na volta), o vice e,
     *   se houver a disputa de 3º com vencedor, o 3º lugar. Final sem vencedor ainda: null;
     * - sem mata-mata, numa tabela única (pontos corridos): o 1º, depois que o admin ENCERRA a categoria (25/10/2026: o sistema
     *   não sabe se ainda vem um returno) e se ainda pode ser encerrada (erroParaEncerrar: sem "classificam N", TODOS os jogos da fase de grupos (ativos)
     *   publicados e com placar). Empatado em tudo com outro(s) no 1º lugar (a marca de sorteio): "a definir", com os nomes;
     * - mata-mata sem final, ou grupos sem mata-mata: null (em andamento).
     * Devolve [id_categoria => null ou ['situacao' => 'campeao'|'sorteio', 'modo' => 'final'|'tabela', 'campeao', 'vice', 'terceiro' (cada um
     * ['id_time', 'nome', 'logo'] ou null), 'como' (texto), 'empatados' (nomes, no sorteio)]]. $classificacoes:
     * o resultado de classificacoes(), quando quem chama já o tem (evita calcular de novo).
     */
    public function campeoes(?array $classificacoes = null): array
    {
        $classificacoes ??= $this->classificacoes();
        $time = fn ($t) => $t ? ['id_time' => (int) $t->id_time, 'nome' => $t->nome_time, 'logo' => $t->logo_time] : null;

        $mataMata = Jogo::with(['evento', 'timeCasa', 'timeVisitante', 'jogoIda', 'jogoVolta'])
            ->where('id_campeonato', $this->id_campeonato)
            ->whereNotNull('fase_jogo')->where('fase_jogo', '<>', Jogo::FASE_PADRAO)
            ->whereHas('evento', fn ($q) => $q->publicados()->where('status_evento_calendario', 'ATIVO'))
            ->get()
            ->groupBy(fn ($jogo) => (int) $jogo->evento->id_categoria);

        $campeoes = [];
        foreach ($classificacoes as $c) {
            $idCategoria = (int) $c['categoria']->id_categoria;
            $doMataMata  = $mataMata[$idCategoria] ?? collect();

            if ($doMataMata->isNotEmpty()) {
                // A final que decide (jogo único ou volta); a ida decide na volta
                $decide = fn (string $fase) => $doMataMata->where('fase_jogo', $fase)->first(fn ($jogo) => ! $jogo->jogoVolta);
                $final  = $decide('FINAL');
                $resultado = $final?->resultadoDoConfronto();
                if (! $resultado) {
                    $campeoes[$idCategoria] = null;
                    continue;
                }

                $casaVenceu = (int) $resultado['id_time'] === (int) $final->id_time_casa;
                $terceiro   = $decide('TERCEIRO');
                $doTerceiro = $terceiro?->resultadoDoConfronto();
                $campeoes[$idCategoria] = [
                    'situacao'  => 'campeao',
                    'modo'      => 'final',
                    'campeao'   => $time($casaVenceu ? $final->timeCasa : $final->timeVisitante),
                    'vice'      => $time($casaVenceu ? $final->timeVisitante : $final->timeCasa),
                    'terceiro'  => $doTerceiro ? $time((int) $doTerceiro['id_time'] === (int) $terceiro->id_time_casa ? $terceiro->timeCasa : $terceiro->timeVisitante) : null,
                    'como'      => 'Final: ' . $resultado['texto'],
                    'empatados' => [],
                ];
                continue;
            }

            // Pontos corridos: só com a categoria encerrada pelo admin (e ainda em condições de encerrar)
            if (! $c['categoria']->pivot->data_encerramento_campeonato_categoria || $this->erroParaEncerrar($c)) {
                $campeoes[$idCategoria] = null;
                continue;
            }

            $tabela   = $c['tabelas'][0]['tabela'];
            $primeiro = $tabela[0];
            $comoTime = fn (array $linha) => ['id_time' => $linha['id_time'], 'nome' => $linha['nome'], 'logo' => $linha['logo']];
            if ($primeiro['sorteio']) {
                $chave = fn ($l) => [$l['pontos'], $l['v'], $l['gm'] - $l['gc'], $l['gm']];
                $empatados = array_values(array_filter($tabela, fn ($l) => $l['sorteio'] && $chave($l) === $chave($primeiro)));
                $campeoes[$idCategoria] = ['situacao' => 'sorteio', 'modo' => 'tabela', 'campeao' => null, 'vice' => null, 'terceiro' => null,
                    'como' => 'Empate em todos os critérios no 1º lugar: decidir por sorteio',
                    'empatados' => array_column($empatados, 'nome')];
                continue;
            }

            $campeoes[$idCategoria] = ['situacao' => 'campeao', 'modo' => 'tabela', 'campeao' => $comoTime($primeiro), 'vice' => null, 'terceiro' => null,
                'como' => '1º na tabela (pontos corridos)', 'empatados' => []];
        }

        return $campeoes;
    }

    /**
     * Por que a categoria não pode ser encerrada como pontos corridos (null = pode), a partir de uma linha de
     * classificacoes(). O campeão pela tabela exige: sem mata-mata (jogo de outra fase ou "classificam N", que
     * anuncia o mata-mata), uma tabela só e todos os jogos da fase de grupos (ativos) publicados e com placar.
     */
    public function erroParaEncerrar(array $classificacao): ?string
    {
        $idCategoria = (int) $classificacao['categoria']->id_categoria;
        $daCategoria = fn () => Jogo::where('id_campeonato', $this->id_campeonato)
            ->whereHas('evento', fn ($q) => $q->where('id_categoria', $idCategoria)->where('status_evento_calendario', 'ATIVO'));

        if ($daCategoria()->whereNotNull('fase_jogo')->where('fase_jogo', '<>', Jogo::FASE_PADRAO)->exists()) {
            return 'A categoria tem mata-mata: o campeão sai da final.';
        }
        if ($classificacao['classificados'] !== null) {
            return "Com \"classificam {$classificacao['classificados']} por grupo\", ainda vem o mata-mata: o campeão sai da final. "
                . 'Nos pontos corridos, deixe o campo vazio.';
        }
        if (count($classificacao['tabelas']) !== 1) {
            return 'Com mais de um grupo (chave), o campeão sai do mata-mata.';
        }

        $jogos = $daCategoria()->where(fn ($q) => $q->where('fase_jogo', Jogo::FASE_PADRAO)->orWhereNull('fase_jogo'));
        if (! (clone $jogos)->exists()) {
            return 'A categoria ainda não tem jogos.';
        }
        // Pendente: sem placar ou ainda em rascunho (o rascunho fica fora da classificação)
        $pendentes = $jogos->where(fn ($q) => $q->whereNull('placar_time_casa_jogos')->orWhereNull('placar_time_visitante_jogos')
            ->orWhereHas('evento', fn ($e) => $e->whereNull('data_publicacao_evento_calendario')))->count();

        return $pendentes ? "Ainda há {$pendentes} jogo(s) da fase de grupos sem placar ou em rascunho." : null;
    }

    // Encerrar (agora) ou reabrir (null) a categoria: o campeão dos pontos corridos só aparece encerrada
    public function definirEncerramento(int $idCategoria, bool $encerrada): void
    {
        DB::table('tbl_campeonato_categoria')->where('id_campeonato', $this->id_campeonato)->where('id_categoria', $idCategoria)
            ->update(['data_encerramento_campeonato_categoria' => $encerrada ? now() : null]);
        $this->unsetRelation('categorias');
    }

    // Texto curto do campeão de uma categoria (campeoes()): "Botafogo da Vila" ou "a definir (sorteio entre X e Y)"
    public static function textoDoCampeao(?array $campeao): ?string
    {
        return match ($campeao['situacao'] ?? null) {
            'campeao' => $campeao['campeao']['nome'],
            'sorteio' => 'a definir (sorteio entre ' . collect($campeao['empatados'])->join(', ', ' e ') . ')',
            default   => null,
        };
    }

    /**
     * Classificação por categoria e grupo (Fase H, Etapa H4): para cada categoria do campeonato,
     * ['categoria' => Categoria, 'classificados' => N ou null, 'tabelas' => [['grupo' => nome ou null,
     * 'classificados' => quantos passam nesta tabela (null na "Sem grupo"), 'tabela' => linhas de Jogo::classificacao]]].
     * Conta só a fase de grupos dos jogos da categoria (a do evento, Etapa H3). Cada grupo lista todos os times
     * dele (os sem jogo com zeros); categoria sem grupo tem uma tabela só, com todos os participantes; com grupos,
     * quem ficou sem grupo vai para a tabela "Sem grupo". Um jogo entre times de grupos diferentes conta para
     * cada um na tabela do grupo dele.
     */
    public function classificacoes(): array
    {
        $jogos = $this->jogos()->with(['evento', 'timeCasa', 'timeVisitante'])->get();
        $participantes = $this->participantesComGrupo();
        $grupos = $this->grupos()->get()->groupBy('id_categoria');

        $resultado = [];
        foreach ($this->categorias()->get() as $categoria) {
            $daCategoria = $jogos->filter(fn ($jogo) => (int) $jogo->evento?->id_categoria === (int) $categoria->id_categoria);
            $times = $participantes->where('id_categoria', $categoria->id_categoria)->values();
            $tabela = fn ($doGrupo) => Jogo::classificacao($daCategoria, $doGrupo);

            // Grupo vazio: tabela vazia (a lista vazia de times não pode virar "todos que jogaram")
            $classificados = $categoria->pivot->classificados_campeonato_categoria;
            $classificados = $classificados === null ? null : (int) $classificados;

            $tabelas = [];
            foreach ($grupos[$categoria->id_categoria] ?? [] as $grupo) {
                $tabelas[] = ['grupo' => $grupo->nome_campeonato_grupo, 'classificados' => $classificados,
                    'tabela' => $tabela($times->where('id_campeonato_grupo', $grupo->id_campeonato_grupo)->values())];
            }
            $semGrupo = $times->whereNull('id_campeonato_grupo')->values();
            if (! $tabelas) {
                // Categoria sem grupo e sem participantes cadastrados: quem jogou
                $tabelas[] = ['grupo' => null, 'classificados' => $classificados,
                    'tabela' => $tabela($semGrupo->isNotEmpty() ? $semGrupo : null)];
            } elseif ($semGrupo->isNotEmpty()) {
                $tabelas[] = ['grupo' => 'Sem grupo', 'classificados' => null, 'tabela' => $tabela($semGrupo)];
            }

            $resultado[] = ['categoria' => $categoria, 'classificados' => $classificados, 'tabelas' => $tabelas];
        }

        return $resultado;
    }

    public function jogos()
    {
        return $this->hasMany(Jogo::class, 'id_campeonato', 'id_campeonato');
    }

    // Campeonatos que o site e a API mostram: inativo some (com os jogos dele) e a página dá 404 (21/10/2026)
    public function scopeAtivos($query)
    {
        return $query->where('status_campeonato', 'ATIVO');
    }

    public function estaAtivo(): bool
    {
        return mb_strtoupper((string) $this->status_campeonato) === 'ATIVO';
    }

    // "Em andamento" (menu do admin): status ATIVO e hoje dentro do período (CLAUDE.md, seção 5)
    public function scopeEmAndamento($query)
    {
        $hoje = now()->toDateString();

        return $query->where('status_campeonato', 'ATIVO')
            ->whereDate('data_inicio_campeonato', '<=', $hoje)
            ->whereDate('data_fim_campeonato', '>=', $hoje);
    }

    /**
     * Categorias com jogos que sairiam do campeonato na edição ($idsCategorias: as que ficam): ["Sub-17
     * Masculino (3 jogo(s))", ...]. Cada jogo tem a categoria dele (a do evento, Fase H, Etapa H3), inclusive
     * concluídos, cancelados e ocultos: a categoria só sai do campeonato sem jogos.
     */
    public function categoriasQueSaemComJogos(array $idsCategorias): array
    {
        return EventoCalendario::whereIn('id_evento_calendario', $this->jogos()->select('id_evento'))
            ->whereNotNull('id_categoria')
            ->whereNotIn('id_categoria', array_map('intval', $idsCategorias))
            ->with('categoria')
            ->get()
            ->groupBy('id_categoria')
            ->map(fn ($eventos) => "{$eventos->first()->categoria->rotulo} ({$eventos->count()} jogo(s))")
            ->sort()
            ->values()
            ->all();
    }

    // Por que não pode ser excluído (ou null): jogo preserva o histórico e a FK de tbl_jogos é NO ACTION;
    // a convocação também (tbl_convocacao, FK NO ACTION)
    public function motivoParaNaoExcluir(): ?string
    {
        $jogos = $this->jogos()->count();
        if ($jogos) {
            return "O campeonato {$this->nome_campeonato} tem {$jogos} jogo(s) e não pode ser excluído. Inative-o para tirá-lo do site.";
        }

        $convocados = $this->convocacoes()->count();

        return $convocados
            ? "O campeonato {$this->nome_campeonato} tem {$convocados} atleta(s) convocado(s). Remova a convocação antes de excluir."
            : null;
    }

    // ── Convocação (Fase G, Etapa 1) ────────────────────────────────────────

    public function convocacoes()
    {
        return $this->hasMany(Convocacao::class, 'id_campeonato', 'id_campeonato');
    }

    /**
     * Ano que vale para a idade na convocação: o de início do campeonato (dono do projeto, 14/10/2026). Quando o
     * campeonato começa, o atleta precisa caber na faixa que ele exige; o jogo continua conferindo pelo ano dele.
     */
    public function anoDaIdade(): int
    {
        return (int) ($this->data_inicio_campeonato ?? now())->format('Y');
    }

    // Convocáveis que passam da idade da categoria do time no campeonato (Etapa H2; $idCategoria: qual, quando o
    // time está em várias): [id_atleta => "Nome (16 anos)"]; sem categoria, sem limite
    public function acimaDaIdade(Time $time, array $idsAtletas, ?int $idCategoria = null): array
    {
        return $this->categoriaDoTime($time, $idCategoria)?->atletasAcimaDaIdade($idsAtletas, $this->anoDaIdade()) ?? [];
    }

    // Os mesmos atletas, só com a idade: [id_atleta => 16]
    public function idadesAcimaDaIdade(Time $time, array $idsAtletas, ?int $idCategoria = null): array
    {
        return $this->categoriaDoTime($time, $idCategoria)?->idadesAcimaDoLimite($idsAtletas, $this->anoDaIdade()) ?? [];
    }

    // "Sub-15 Masculino, até 15 anos" (null sem limite de idade)
    public function limiteDeIdade(Time $time, ?int $idCategoria = null): ?string
    {
        return $this->categoriaDoTime($time, $idCategoria)?->limite_de_idade;
    }

    // Por que o time não convoca neste campeonato (ou null): só time interno e participante (na categoria
    // informada, quando há)
    public function erroDoTimeNaConvocacao(Time $time, ?int $idCategoria = null): ?string
    {
        if ($time->tipo_time !== 'INTERNO') {
            return 'Times externos não possuem atletas da associação: não há convocação.';
        }

        if ($this->categoriaDoTime($time, $idCategoria)) {
            return null;
        }

        return $idCategoria && $this->categoriaDoTime($time)
            ? "O {$time->nome_time} não joga no " . (Categoria::find($idCategoria)?->rotulo ?? 'categoria informada') . " do campeonato {$this->nome_campeonato}."
            : "O {$time->nome_time} não participa do campeonato {$this->nome_campeonato}.";
    }

    /**
     * Confere uma convocação antes de gravar ($camisas: [id_atleta => número da camisa]). Tudo ou nada: devolve
     * as mensagens de erro (vazio = pode gravar). Regras: atleta ATIVO; um time por atleta em cada categoria do
     * campeonato (o do Sub-15 pode ser convocado também no Sub-17: Etapa H2); idade da categoria do time; camisa
     * sem repetir no pedido nem no time naquela categoria (contando os convocados inativos, que guardam o número). A faixa do
     * número é conferida na validação do formulário.
     */
    public function errosDaConvocacao(Time $time, array $camisas, ?int $idCategoria = null): array
    {
        $ids       = array_map('intval', array_keys($camisas));
        $categoria = $this->categoriaDoTime($time, $idCategoria);
        $atletas   = Atleta::whereIn('id_atleta', $ids)->pluck('nome_atleta', 'id_atleta');
        $ativos    = Atleta::whereIn('id_atleta', $ids)->where('status_atleta', 'ATIVO')->pluck('id_atleta')->all();
        $jaConvocados = $this->convocacoes()->where('id_categoria', $categoria?->id_categoria)
            ->with('time:id_time,nome_time')->whereIn('id_atleta', $ids)->get()->keyBy('id_atleta');
        $camisasDoTime = $this->convocacoes()->where('id_time', $time->id_time)->where('id_categoria', $categoria?->id_categoria)
            ->with('atleta:id_atleta,nome_atleta')->get()->keyBy('camisa_convocacao');

        $erros = [];
        foreach ($camisas as $idAtleta => $camisa) {
            $nome = $atletas[$idAtleta] ?? null;
            if ($nome === null || ! in_array((int) $idAtleta, $ativos, true)) {
                $erros[] = 'Escolha só atletas ativos.';
                continue;
            }
            if ($convocacao = $jaConvocados->get($idAtleta)) {
                $erros[] = (int) $convocacao->id_time === (int) $time->id_time
                    ? "{$nome} já está convocado pelo {$time->nome_time}."
                    : "{$nome} já foi convocado pelo {$convocacao->time->nome_time} no {$categoria->rotulo} deste campeonato: "
                        . 'é um time por atleta em cada categoria.';
            }
            if ($dono = $camisasDoTime->get((int) $camisa)) {
                $erros[] = "A camisa {$camisa} do {$time->nome_time} já é de {$dono->atleta->nome_atleta}.";
            }
        }

        $repetidas = array_keys(array_filter(array_count_values(array_map('intval', $camisas)), fn ($n) => $n > 1));
        foreach ($repetidas as $camisa) {
            $erros[] = "A camisa {$camisa} foi escolhida para mais de um atleta.";
        }

        if ($acima = $this->acimaDaIdade($time, $ids, $idCategoria)) {
            $erros[] = implode(', ', $acima) . " passa(m) da idade da categoria do {$time->nome_time} ({$this->limiteDeIdade($time, $idCategoria)}): "
                . 'atleta mais velho não joga em categoria abaixo da idade dele.';
        }

        return array_values(array_unique($erros));
    }

    // Grava a convocação já conferida (errosDaConvocacao), numa transação, na categoria do time no campeonato
    // ($idCategoria: qual, quando o time está em várias); devolve quantos foram convocados
    public function convocar(Time $time, array $camisas, ?int $idUsuario, ?int $idCategoria = null): int
    {
        $idCategoria = $this->categoriaDoTime($time, $idCategoria)->id_categoria;

        DB::transaction(function () use ($time, $camisas, $idUsuario, $idCategoria) {
            foreach ($camisas as $idAtleta => $camisa) {
                $convocacao = new Convocacao([
                    'id_campeonato'     => $this->id_campeonato,
                    'id_categoria'      => $idCategoria,
                    'id_time'           => $time->id_time,
                    'id_atleta'         => (int) $idAtleta,
                    'camisa_convocacao' => (int) $camisa,
                ]);
                $convocacao->id_usuario = $idUsuario;
                $convocacao->save();
            }
        });

        return count($camisas);
    }

    // Por que a camisa não pode ser trocada para este número (ou null): já é de outro convocado do time na mesma
    // categoria (convocacao_camisa_categoria_unique)
    public function erroDaCamisa(Convocacao $convocacao, int $camisa): ?string
    {
        $dono = $this->convocacoes()->where('id_time', $convocacao->id_time)->where('id_categoria', $convocacao->id_categoria)
            ->where('camisa_convocacao', $camisa)
            ->where('id_convocacao', '<>', $convocacao->id_convocacao)->with('atleta:id_atleta,nome_atleta')->first();

        return $dono ? "A camisa {$camisa} do {$convocacao->time->nome_time} já é de {$dono->atleta->nome_atleta}." : null;
    }

    /**
     * Aviso (sem bloquear) para quem é de outro sexo que o da categoria do time no campeonato (CLAUDE.md, seção 8,
     * pergunta 14): ["Ana é do sexo feminino; o AACJ Sub-15 joga no Sub-15 Masculino.", ...]. Sem categoria, nada.
     */
    public function avisosDeSexo(Time $time, array $idsAtletas, ?int $idCategoria = null): array
    {
        $categoria = $this->categoriaDoTime($time, $idCategoria);
        if (! $categoria || ! $idsAtletas) {
            return [];
        }

        return Atleta::whereIn('id_atleta', $idsAtletas)->where('sexo_atleta', '<>', $categoria->sexo_categoria)
            ->orderBy('nome_atleta')->get(['nome_atleta', 'sexo_atleta'])
            ->map(fn ($atleta) => "{$atleta->nome_atleta} é do sexo " . mb_strtolower(Categoria::SEXOS[$atleta->sexo_atleta] ?? $atleta->sexo_atleta)
                . "; o {$time->nome_time} joga no {$categoria->rotulo}.")
            ->all();
    }

    /**
     * Times com convocados que sairiam da categoria em que convocaram ($participantes: [id_categoria => [id_time]],
     * o pedido da edição): ["Time Azul no Sub-15 Masculino (3 convocado(s))", ...]. A convocação aponta para o
     * participante (FK composta): o time só sai da categoria (ou do campeonato) depois de remover a convocação.
     */
    public function timesQueSaemComConvocados(array $participantes): array
    {
        $ficam = [];
        foreach ($participantes as $idCategoria => $idsTimes) {
            foreach ((array) $idsTimes as $idTime) {
                $ficam[(int) $idCategoria . '|' . (int) $idTime] = true;
            }
        }

        return $this->convocacoes()
            ->join('tbl_time', 'tbl_time.id_time', '=', 'tbl_convocacao.id_time')
            ->groupBy('tbl_convocacao.id_categoria', 'tbl_time.id_time', 'tbl_time.nome_time')
            ->orderBy('tbl_time.nome_time')
            ->selectRaw('tbl_convocacao.id_categoria, tbl_time.id_time, tbl_time.nome_time, COUNT(*) AS total')
            ->get()
            ->reject(fn ($linha) => isset($ficam["{$linha->id_categoria}|{$linha->id_time}"]))
            ->map(fn ($linha) => "{$linha->nome_time} no " . Categoria::find($linha->id_categoria)->rotulo . " ({$linha->total} convocado(s))")
            ->values()
            ->all();
    }

    public function getLogoUrlAttribute(): ?string
    {
        return static::urlDaImagem($this->attributes['logo_evento'] ?? null, 'campeonatos');
    }

    public function getBannerUrlAttribute(): ?string
    {
        return static::urlDaImagem($this->attributes['banner_evento'] ?? null, 'campeonatos');
    }

    // Iniciais do nome, no lugar da logo (opcional desde 21/10/2026): "Copa da Cidade" => "CC"
    public function getIniciaisAttribute(): string
    {
        return collect(preg_split('/\s+/u', trim((string) $this->nome_campeonato)))
            ->reject(fn ($palavra) => in_array(mb_strtolower($palavra), ['da', 'de', 'do', 'das', 'dos', 'e'], true))
            ->take(2)
            ->map(fn ($palavra) => mb_strtoupper(mb_substr($palavra, 0, 1)))
            ->join('');
    }

    // Quantos locais dos jogos aparecem pelo nome no site; os outros viram "e mais N"
    public const LOCAIS_NO_SITE = 2;

    /**
     * Local do campeonato para o site (21/10/2026): o local principal, se preenchido; senão, os locais dos jogos
     * ($jogos com o evento: cada jogo tem o seu), na ordem em que vêm e sem repetir: "Arena A", "Arena A e Arena B"
     * ou "Arena A, Arena B e mais 2". Sem nenhum, null (a linha do local some).
     */
    public function textoDosLocais(iterable $jogos): ?string
    {
        if (filled($this->local_evento)) {
            return $this->local_evento;
        }

        $locais = collect($jogos)
            ->map(fn ($jogo) => trim((string) $jogo->evento?->local_evento_calendario))
            ->filter()
            ->unique(fn ($local) => mb_strtolower($local))
            ->values();

        return match (true) {
            $locais->isEmpty()                         => null,
            $locais->count() <= self::LOCAIS_NO_SITE   => $locais->join(', ', ' e '),
            default                                    => $locais->take(self::LOCAIS_NO_SITE)->join(', ')
                                                          . ' e mais ' . ($locais->count() - self::LOCAIS_NO_SITE),
        };
    }
}
