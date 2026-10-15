<?php

namespace App\Models;

use App\Models\Concerns\SerializaDatasComFuso;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Models\Time;
use App\Models\Jogo;

class Campeonato extends Model
{
    use SerializaDatasComFuso; // datas no JSON da API: 2025-01-01T00:00:00-03:00

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
     * Categorias do campeonato (tbl_campeonato_categoria), na ordem dos formulários. A principal
     * (tbl_campeonato.id_categoria) está sempre entre elas e é a que vale para a idade, a convocação e os
     * jogos até a Etapa H3, quando o jogo ganha a categoria dele.
     */
    public function categorias()
    {
        return $this->belongsToMany(Categoria::class, 'tbl_campeonato_categoria', 'id_campeonato', 'id_categoria')
            ->orderBy('sexo_categoria', 'desc')->orderBy('idade_min_categoria');
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
     * Grava as categorias ($idsCategorias, já com a principal) e os participantes de cada uma
     * ($participantes: [id_categoria => [id_time, ...]]; categoria fora da lista é ignorada), numa transação.
     * Os participantes são regravados; a FK composta só aceita participante numa categoria do campeonato.
     */
    public function gravarCategoriasEParticipantes(array $idsCategorias, array $participantes): void
    {
        $idsCategorias = array_values(array_unique(array_map('intval', $idsCategorias)));

        DB::transaction(function () use ($idsCategorias, $participantes) {
            DB::table('tbl_campeonato_time')->where('id_campeonato', $this->id_campeonato)->delete();
            DB::table('tbl_campeonato_categoria')->where('id_campeonato', $this->id_campeonato)
                ->whereNotIn('id_categoria', $idsCategorias)->delete();
            DB::table('tbl_campeonato_categoria')->insertOrIgnore(array_map(
                fn ($idCategoria) => ['id_campeonato' => $this->id_campeonato, 'id_categoria' => $idCategoria], $idsCategorias));

            $linhas = [];
            foreach ($idsCategorias as $idCategoria) {
                foreach (array_unique(array_map('intval', $participantes[$idCategoria] ?? [])) as $idTime) {
                    $linhas[] = ['id_campeonato' => $this->id_campeonato, 'id_categoria' => $idCategoria, 'id_time' => $idTime];
                }
            }
            DB::table('tbl_campeonato_time')->insert($linhas);
        });

        $this->unsetRelation('categorias')->unsetRelation('times');
    }

    // Tira participantes e categorias (exclusão do campeonato; as FKs são NO ACTION)
    public function apagarCategoriasEParticipantes(): void
    {
        DB::table('tbl_campeonato_time')->where('id_campeonato', $this->id_campeonato)->delete();
        DB::table('tbl_campeonato_categoria')->where('id_campeonato', $this->id_campeonato)->delete();
    }

    public function jogos()
    {
        return $this->hasMany(Jogo::class, 'id_campeonato', 'id_campeonato');
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
     * O evento de cada jogo guarda uma cópia da categoria do campeonato (JogosController), que é só exibição
     * desde a Fase 8. Quando a categoria do campeonato muda, a cópia acompanha em todos os jogos dele (inclusive
     * concluídos, cancelados e ocultos), com histórico e sem avisar nem mexer nas inscrições. Devolve quantos
     * jogos mudaram.
     */
    public function atualizarCategoriaDosJogos(?int $idUsuario): int
    {
        $eventos = EventoCalendario::whereIn('id_evento_calendario', $this->jogos()->select('id_evento'))
            ->where(fn ($q) => $this->id_categoria === null
                ? $q->whereNotNull('id_categoria')
                : $q->whereNull('id_categoria')->orWhere('id_categoria', '<>', $this->id_categoria))
            ->get();

        foreach ($eventos as $evento) {
            $evento->atualizarComHistorico(['id_categoria' => $this->id_categoria], $idUsuario);
        }

        return $eventos->count();
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

    // Convocáveis que passam da idade do campeonato: [id_atleta => "Nome (16 anos)"]; sem categoria, sem limite
    public function acimaDaIdade(array $idsAtletas): array
    {
        return $this->categoria?->atletasAcimaDaIdade($idsAtletas, $this->anoDaIdade()) ?? [];
    }

    // Os mesmos atletas, só com a idade: [id_atleta => 16]
    public function idadesAcimaDaIdade(array $idsAtletas): array
    {
        return $this->categoria?->idadesAcimaDoLimite($idsAtletas, $this->anoDaIdade()) ?? [];
    }

    // "Sub-15 Masculino, até 15 anos" (null sem limite de idade)
    public function limiteDeIdade(): ?string
    {
        return $this->categoria?->limite_de_idade;
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
     * as mensagens de erro (vazio = pode gravar). Regras: atleta ATIVO; um time por atleta no campeonato; idade
     * do campeonato; camisa sem repetir no pedido nem no time (contando os convocados inativos, que guardam o
     * número). A faixa do número é conferida na validação do formulário.
     */
    public function errosDaConvocacao(Time $time, array $camisas): array
    {
        $ids     = array_map('intval', array_keys($camisas));
        $atletas = Atleta::whereIn('id_atleta', $ids)->pluck('nome_atleta', 'id_atleta');
        $ativos  = Atleta::whereIn('id_atleta', $ids)->where('status_atleta', 'ATIVO')->pluck('id_atleta')->all();
        $jaConvocados = $this->convocacoes()->with('time:id_time,nome_time')->whereIn('id_atleta', $ids)->get()->keyBy('id_atleta');
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
                    : "{$nome} já foi convocado pelo {$convocacao->time->nome_time} neste campeonato: é um time por atleta em cada campeonato.";
            }
            if ($dono = $camisasDoTime->get((int) $camisa)) {
                $erros[] = "A camisa {$camisa} do {$time->nome_time} já é de {$dono->atleta->nome_atleta}.";
            }
        }

        $repetidas = array_keys(array_filter(array_count_values(array_map('intval', $camisas)), fn ($n) => $n > 1));
        foreach ($repetidas as $camisa) {
            $erros[] = "A camisa {$camisa} foi escolhida para mais de um atleta.";
        }

        if ($acima = $this->acimaDaIdade($ids)) {
            $erros[] = implode(', ', $acima) . " passa(m) da idade do campeonato ({$this->limiteDeIdade()}): "
                . 'atleta mais velho não joga em categoria abaixo da idade dele.';
        }

        return array_values(array_unique($erros));
    }

    // Grava a convocação já conferida (errosDaConvocacao), numa transação; devolve quantos foram convocados
    public function convocar(Time $time, array $camisas, ?int $idUsuario): int
    {
        DB::transaction(function () use ($time, $camisas, $idUsuario) {
            foreach ($camisas as $idAtleta => $camisa) {
                $convocacao = new Convocacao([
                    'id_campeonato'     => $this->id_campeonato,
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
     * Aviso (sem bloquear) para quem é de outro sexo que o da categoria do campeonato (CLAUDE.md, seção 8,
     * pergunta 14): ["Ana é do sexo feminino; o campeonato é Sub-15 Masculino.", ...]. Sem categoria, nada.
     */
    public function avisosDeSexo(array $idsAtletas): array
    {
        $categoria = $this->categoria;
        if (! $categoria || ! $idsAtletas) {
            return [];
        }

        return Atleta::whereIn('id_atleta', $idsAtletas)->where('sexo_atleta', '<>', $categoria->sexo_categoria)
            ->orderBy('nome_atleta')->get(['nome_atleta', 'sexo_atleta'])
            ->map(fn ($atleta) => "{$atleta->nome_atleta} é do sexo " . mb_strtolower(Categoria::SEXOS[$atleta->sexo_atleta] ?? $atleta->sexo_atleta)
                . "; o campeonato é {$categoria->rotulo}.")
            ->all();
    }

    /**
     * Participantes que sairiam do campeonato com convocados (a edição troca a lista de times com sync):
     * ["Time Azul (3 convocados)", ...]. O time só sai depois de remover a convocação.
     */
    public function timesQueSaemComConvocados(array $idsQueFicam): array
    {
        return $this->convocacoes()
            ->whereNotIn('tbl_convocacao.id_time', array_map('intval', $idsQueFicam))
            ->join('tbl_time', 'tbl_time.id_time', '=', 'tbl_convocacao.id_time')
            ->groupBy('tbl_time.id_time', 'tbl_time.nome_time')
            ->orderBy('tbl_time.nome_time')
            ->selectRaw('tbl_time.nome_time, COUNT(*) AS total')
            ->get()
            ->map(fn ($linha) => "{$linha->nome_time} ({$linha->total} convocado(s))")
            ->all();
    }
}
