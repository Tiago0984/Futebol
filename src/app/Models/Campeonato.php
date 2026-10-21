<?php

namespace App\Models;

use App\Models\Concerns\SerializaDatasComFuso;
use App\Models\Concerns\UrlDeImagem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Models\Time;
use App\Models\Jogo;

class Campeonato extends Model
{
    use SerializaDatasComFuso; // datas no JSON da API: 2025-01-01T00:00:00-03:00
    use UrlDeImagem;

    // Endereço completo da logo e do banner no JSON da API (o banco guarda só o nome do arquivo)
    protected $appends = ['logo_url', 'banner_url'];

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
    public function categorias()
    {
        return $this->belongsToMany(Categoria::class, 'tbl_campeonato_categoria', 'id_campeonato', 'id_categoria')
            ->withPivot('classificados_campeonato_categoria')
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
     * Times da AACJ (INTERNO) marcados em mais de uma categoria ($participantes: [id_categoria => [id_time]]):
     * ["Time Azul", ...]. Cada time da AACJ joga numa categoria só do campeonato (Fase H, Etapa H2): para outra
     * categoria, a AACJ forma outro time ("AACJ Sub-17"). O externo pode estar em várias.
     */
    public static function internosEmVariasCategorias(array $participantes): array
    {
        $vezes = array_count_values(array_merge([], ...array_map(
            fn ($ids) => array_values(array_unique(array_map('intval', (array) $ids))), array_values($participantes))));
        $repetidos = array_keys(array_filter($vezes, fn ($n) => $n > 1));

        return $repetidos
            ? Time::whereIn('id_time', $repetidos)->where('tipo_time', 'INTERNO')->orderBy('nome_time')->pluck('nome_time')->all()
            : [];
    }

    // Categoria do time no campeonato (o time interno está numa só; null se não participa), guardada por time
    private array $categoriasDosTimes = [];

    public function categoriaDoTime(Time $time): ?Categoria
    {
        if (! array_key_exists($time->id_time, $this->categoriasDosTimes)) {
            $idCategoria = DB::table('tbl_campeonato_time')->where('id_campeonato', $this->id_campeonato)
                ->where('id_time', $time->id_time)->orderBy('id_categoria')->value('id_categoria');
            $this->categoriasDosTimes[$time->id_time] = $idCategoria ? Categoria::find($idCategoria) : null;
        }

        return $this->categoriasDosTimes[$time->id_time];
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

    // Times que convocam: os participantes INTERNOS (o externo não tem atletas da escolinha)
    public function timesConvocaveis()
    {
        return $this->times()->where('tipo_time', 'INTERNO');
    }

    /**
     * Ano que vale para a idade na convocação: o de início do campeonato (dono do projeto, 14/10/2026). Quando o
     * campeonato começa, o atleta precisa caber na faixa que ele exige; o jogo continua conferindo pelo ano dele.
     */
    public function anoDaIdade(): int
    {
        return (int) ($this->data_inicio_campeonato ?? now())->format('Y');
    }

    // Convocáveis que passam da idade da categoria do time no campeonato (Etapa H2): [id_atleta => "Nome (16 anos)"];
    // sem categoria, sem limite
    public function acimaDaIdade(Time $time, array $idsAtletas): array
    {
        return $this->categoriaDoTime($time)?->atletasAcimaDaIdade($idsAtletas, $this->anoDaIdade()) ?? [];
    }

    // Os mesmos atletas, só com a idade: [id_atleta => 16]
    public function idadesAcimaDaIdade(Time $time, array $idsAtletas): array
    {
        return $this->categoriaDoTime($time)?->idadesAcimaDoLimite($idsAtletas, $this->anoDaIdade()) ?? [];
    }

    // "Sub-15 Masculino, até 15 anos" (null sem limite de idade)
    public function limiteDeIdade(Time $time): ?string
    {
        return $this->categoriaDoTime($time)?->limite_de_idade;
    }

    // Por que o time não convoca neste campeonato (ou null): só time interno e participante
    public function erroDoTimeNaConvocacao(Time $time): ?string
    {
        if ($time->tipo_time !== 'INTERNO') {
            return 'Times externos não possuem atletas da associação: não há convocação.';
        }

        return $this->times()->where('tbl_time.id_time', $time->id_time)->exists()
            ? null
            : "O {$time->nome_time} não participa do campeonato {$this->nome_campeonato}.";
    }

    /**
     * Confere uma convocação antes de gravar ($camisas: [id_atleta => número da camisa]). Tudo ou nada: devolve
     * as mensagens de erro (vazio = pode gravar). Regras: atleta ATIVO; um time por atleta em cada categoria do
     * campeonato (o do Sub-15 pode ser convocado também no Sub-17: Etapa H2); idade da categoria do time; camisa
     * sem repetir no pedido nem no time (contando os convocados inativos, que guardam o número). A faixa do
     * número é conferida na validação do formulário.
     */
    public function errosDaConvocacao(Time $time, array $camisas): array
    {
        $ids       = array_map('intval', array_keys($camisas));
        $categoria = $this->categoriaDoTime($time);
        $atletas   = Atleta::whereIn('id_atleta', $ids)->pluck('nome_atleta', 'id_atleta');
        $ativos    = Atleta::whereIn('id_atleta', $ids)->where('status_atleta', 'ATIVO')->pluck('id_atleta')->all();
        $jaConvocados = $this->convocacoes()->where('id_categoria', $categoria?->id_categoria)
            ->with('time:id_time,nome_time')->whereIn('id_atleta', $ids)->get()->keyBy('id_atleta');
        $camisasDoTime = $this->convocacoes()->where('id_time', $time->id_time)->with('atleta:id_atleta,nome_atleta')
            ->get()->keyBy('camisa_convocacao');

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

        if ($acima = $this->acimaDaIdade($time, $ids)) {
            $erros[] = implode(', ', $acima) . " passa(m) da idade da categoria do {$time->nome_time} ({$this->limiteDeIdade($time)}): "
                . 'atleta mais velho não joga em categoria abaixo da idade dele.';
        }

        return array_values(array_unique($erros));
    }

    // Grava a convocação já conferida (errosDaConvocacao), numa transação, na categoria do time no campeonato;
    // devolve quantos foram convocados
    public function convocar(Time $time, array $camisas, ?int $idUsuario): int
    {
        $idCategoria = $this->categoriaDoTime($time)->id_categoria;

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

    // Por que a camisa não pode ser trocada para este número (ou null): já é de outro convocado do time
    public function erroDaCamisa(Convocacao $convocacao, int $camisa): ?string
    {
        $dono = $this->convocacoes()->where('id_time', $convocacao->id_time)->where('camisa_convocacao', $camisa)
            ->where('id_convocacao', '<>', $convocacao->id_convocacao)->with('atleta:id_atleta,nome_atleta')->first();

        return $dono ? "A camisa {$camisa} do {$convocacao->time->nome_time} já é de {$dono->atleta->nome_atleta}." : null;
    }

    /**
     * Aviso (sem bloquear) para quem é de outro sexo que o da categoria do time no campeonato (CLAUDE.md, seção 8,
     * pergunta 14): ["Ana é do sexo feminino; o AACJ Sub-15 joga no Sub-15 Masculino.", ...]. Sem categoria, nada.
     */
    public function avisosDeSexo(Time $time, array $idsAtletas): array
    {
        $categoria = $this->categoriaDoTime($time);
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
