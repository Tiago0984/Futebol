<?php

namespace App\Models;

use App\Models\Concerns\SerializaDatasComFuso;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Jogo = evento do tipo JOGO + times, campeonato (vazio no amistoso) e placar (vazio = não jogado).
 * Data, horário, local e status ficam no evento (CLAUDE.md, seção 4, "Jogos").
 */
class Jogo extends Model
{
    use SerializaDatasComFuso; // data_jogo no JSON da API: 2099-05-01T19:00:00-03:00

    protected $table = 'tbl_jogos';
    protected $primaryKey = 'id_jogo';
    public $timestamps = false;

    // Fase do jogo de campeonato (Fase H, Etapa H4; valores sem acento, rótulo aqui), na ordem do campeonato (a
    // repescagem entrou depois no ENUM, no fim). A classificação conta só a fase de grupos; o mata-mata é
    // opcional. Amistoso: sem fase (NULL)
    public const FASES = [
        'GRUPOS'         => 'Fase de grupos',
        'REPESCAGEM'     => 'Repescagem',
        'DEZESSEIS_AVOS' => '16 avos de final',
        'OITAVAS'        => 'Oitavas de final',
        'QUARTAS'        => 'Quartas de final',
        'SEMIFINAL'      => 'Semifinal',
        'TERCEIRO'       => 'Disputa de 3º lugar',
        'FINAL'          => 'Final',
    ];
    public const FASE_PADRAO = 'GRUPOS';

    // Rodada da fase de grupos: 1 a RODADA_MAX (Fase I, Etapa I1b)
    public const RODADA_MAX = 99;

    protected $fillable = [
        'id_evento',
        'id_campeonato',
        'fase_jogo',
        'rodada_jogo',
        'id_jogo_ida',
        'id_time_casa',
        'id_time_visitante',
        'placar_time_casa_jogos',
        'placar_time_visitante_jogos',
        'prorrogacao_time_casa_jogos',
        'prorrogacao_time_visitante_jogos',
        'penaltis_time_casa_jogos',
        'penaltis_time_visitante_jogos',
    ];

    // No JSON da API, os dados do evento vêm achatados (data_jogo e status_jogo mantêm os nomes de antes,
    // para não quebrar o app); o evento em si não vai
    // Fase H, Etapa H5: a fase (rótulo; fase_jogo é o valor gravado) e a categoria do jogo (a do evento)
    // Fase I, Etapa I1b: confronto (quem passa no mata-mata); a ida e a volta em si não vão (só id_jogo_ida)
    protected $appends = ['data_jogo', 'horario_jogo', 'local_jogo', 'status_jogo', 'fase', 'id_categoria', 'confronto'];
    protected $hidden  = ['evento', 'jogoIda', 'jogoVolta'];

    public function evento()
    {
        return $this->belongsTo(EventoCalendario::class, 'id_evento', 'id_evento_calendario');
    }

    // "Semifinal" (null no amistoso)
    public function getFaseLabelAttribute(): ?string
    {
        return $this->fase_jogo ? (self::FASES[$this->fase_jogo] ?? $this->fase_jogo) : null;
    }

    // Conta na classificação: fase de grupos (jogo de antes da Etapa H4 sem fase também)
    public function contaNaClassificacao(): bool
    {
        return $this->fase_jogo === null || $this->fase_jogo === self::FASE_PADRAO;
    }

    // Mata-mata: toda fase de campeonato que não é a de grupos (repescagem também; Fase I, Etapa I1b)
    public function ehMataMata(): bool
    {
        return $this->fase_jogo !== null && $this->fase_jogo !== self::FASE_PADRAO;
    }

    // No jogo de volta do mata-mata, o de ida; na ida, a volta (no máximo uma: único no banco)
    public function jogoIda()
    {
        return $this->belongsTo(self::class, 'id_jogo_ida', 'id_jogo');
    }

    public function jogoVolta()
    {
        return $this->hasOne(self::class, 'id_jogo_ida', 'id_jogo');
    }

    public function campeonato()
    {
        return $this->belongsTo(Campeonato::class, 'id_campeonato', 'id_campeonato');
    }

    public function timeCasa()
    {
        return $this->belongsTo(Time::class, 'id_time_casa', 'id_time');
    }

    public function timeVisitante()
    {
        return $this->belongsTo(Time::class, 'id_time_visitante', 'id_time');
    }

    public function cartoes()
    {
        return $this->hasMany(Cartao::class, 'id_jogo', 'id_jogo');
    }

    // Súmula (Etapa S1): quem fez cada gol dos times da AACJ (e os gols contra)
    public function gols()
    {
        return $this->hasMany(JogoGol::class, 'id_jogo', 'id_jogo');
    }

    // ── Consultas ───────────────────────────────────────────────────────────

    // Jogos que o site e a API de campeonatos mostram: evento publicado, ativo ou cancelado (oculto e
    // rascunho somem, como no calendário do site)
    public function scopeVisiveis($query)
    {
        return $query->whereHas('evento', fn ($q) => $q->publicados()->whereIn('status_evento_calendario', EventoCalendario::STATUS_VISIVEIS));
    }

    // Jogos que a agenda do site mostra (hoje, só os de campeonato): a regra é a do evento,
    // EventoCalendario::daAgendaPublica(), para não ficar em dois lugares. Usado no destaque da home
    public function scopeDaAgendaPublica($query)
    {
        return $query->whereHas('evento', fn ($q) => $q->daAgendaPublica());
    }

    // Quantos jogos já realizados aparecem abaixo dos próximos nas telas de times por jogo
    public const REALIZADOS_NA_TELA = 10;

    // O atleta chega antes do jogo (professor, 09/10/2026: "sempre com uma hora de antecedência")
    public const ANTECEDENCIA_MINUTOS = 60;
    public const ANTECEDENCIA_TEXTO   = '1 hora';

    // Titulares por modalidade (Fase D, Etapa D2): o contador da escalação informa quando bate o número de uma
    // delas e avisa acima do maior (só aviso: a modalidade do jogo não é cadastrada)
    public const TITULARES_POR_MODALIDADE = [5 => 'futsal', 7 => 'society', 11 => 'campo'];

    /**
     * Resumo da escalação de um time (inscrições escaladas nele): quantos titulares, reservas e a definir, e os
     * avisos (sem goleiro titular; mais titulares que o campo). 'modalidade' = a de quem tem exatamente esse
     * número de titulares ("número do futsal"), ou null.
     */
    public static function resumoDaEscalacao(Collection $inscricoes): array
    {
        $titulares = $inscricoes->where('titular_evento_atleta', 'TITULAR');
        $maximo    = max(array_keys(self::TITULARES_POR_MODALIDADE));
        $avisos    = [];

        if ($titulares->isNotEmpty() && ! $titulares->contains('posicao_evento_atleta', 'GOLEIRO')) {
            $avisos[] = 'Sem goleiro titular.';
        }
        if ($titulares->count() > $maximo) {
            $avisos[] = "Mais de {$maximo} titulares (o número do " . self::TITULARES_POR_MODALIDADE[$maximo] . ').';
        }

        return [
            'titulares'  => $titulares->count(),
            'reservas'   => $inscricoes->where('titular_evento_atleta', 'RESERVA')->count(),
            'a_definir'  => $inscricoes->whereNull('titular_evento_atleta')->count(),
            'modalidade' => self::TITULARES_POR_MODALIDADE[$titulares->count()] ?? null,
            'avisos'     => $avisos,
        ];
    }

    // Horário de chegada ("12:00") para o jogo que começa em $inicio ("13:00:00"); null sem horário de início
    public static function chegadaPara(?string $inicio): ?string
    {
        return $inicio ? Carbon::parse($inicio)->subMinutes(self::ANTECEDENCIA_MINUTOS)->format('H:i') : null;
    }

    // Frase dos avisos do jogo: "Chegue às 12:00 (1 hora antes)." ('' sem horário de início)
    public static function fraseDaChegada(?string $inicio): string
    {
        $chegada = self::chegadaPara($inicio);

        return $chegada ? "Chegue às {$chegada} (" . self::ANTECEDENCIA_TEXTO . ' antes).' : '';
    }

    /**
     * Jogos para as telas de times por jogo (Times dos amistosos e Times do campeonato): só os não ocultos,
     * com o evento (e os inscritos ativos, para as ações do jogo), o campeonato e os dois times (com a categoria) de uma vez, e, em $jogo->escalados, quantos atletas
     * ativos cada time tem escalados no jogo ([id_time => n], numa consulta para todos). Devolve
     * [próximos, realizados]: próximos (não concluídos) do mais perto ao mais longe; realizados (concluídos)
     * do mais recente para trás, no máximo REALIZADOS_NA_TELA.
     */
    public static function separadosParaTelaDeTimes($query): array
    {
        $jogos = $query
            ->whereHas('evento', fn ($q) => $q->where('status_evento_calendario', '<>', 'INATIVO'))
            ->with(['evento' => fn ($q) => $q->comInscritosAtivos(), 'campeonato', 'evento.categoria', 'timeCasa.categorias', 'timeVisitante.categorias'])
            ->get();

        $escalados = $jogos->isEmpty() ? collect() : DB::table('tbl_evento_atleta as ea')
            ->join('tbl_atletas as a', 'a.id_atleta', '=', 'ea.id_atleta')
            ->where('a.status_atleta', 'ATIVO')
            ->whereIn('ea.id_evento_calendario', $jogos->pluck('id_evento'))
            ->whereNotNull('ea.id_time')
            ->groupBy('ea.id_evento_calendario', 'ea.id_time')
            ->get(['ea.id_evento_calendario', 'ea.id_time', DB::raw('COUNT(*) as total')])
            ->groupBy('id_evento_calendario');
        foreach ($jogos as $jogo) {
            $jogo->setAttribute('escalados', ($escalados[$jogo->id_evento] ?? collect())
                ->mapWithKeys(fn ($linha) => [(int) $linha->id_time => (int) $linha->total])->all());
        }

        [$realizados, $proximos] = $jogos->partition(fn ($jogo) => $jogo->evento->estaConcluido());

        return [
            $proximos->sortBy(fn ($jogo) => $jogo->evento->dataHoraDoJogo())->values(),
            $realizados->sortByDesc(fn ($jogo) => $jogo->evento->dataHoraDoJogo())->take(self::REALIZADOS_NA_TELA)->values(),
        ];
    }

    /**
     * Atletas ativos escalados por um time neste jogo (tela "jogadores do time no jogo"), pela camisa da
     * convocação e pelo nome (amistoso: pelo nome), com, no jogo de campeonato, a convocação por esse time
     * (id e camisa; null = não convocado). Uma consulta.
     */
    public function escaladosDoTime(int $idTime): Collection
    {
        return DB::table('tbl_evento_atleta as ea')
            ->join('tbl_atletas as a', 'a.id_atleta', '=', 'ea.id_atleta')
            // Jogo de campeonato: a convocação do atleta por este time (camisa e "Não convocado"); amistoso: nenhuma
            ->leftJoin('tbl_convocacao as cv', fn ($j) => $j->on('cv.id_atleta', '=', 'ea.id_atleta')
                ->where('cv.id_time', $idTime)->where('cv.id_campeonato', (int) $this->id_campeonato))
            ->where('ea.id_evento_calendario', $this->id_evento)
            ->where('ea.id_time', $idTime)
            ->where('a.status_atleta', 'ATIVO')
            // Titulares, reservas e a definir (Fase D, Etapa D2); em cada grupo, pela posição no jogo (do goleiro
            // ao ataque, sem posição no fim), pela camisa da convocação (sem camisa no fim) e pelo nome
            ->orderByRaw('ea.titular_evento_atleta IS NULL')
            ->orderByRaw("ea.titular_evento_atleta = 'RESERVA'")
            ->orderByRaw('ea.posicao_evento_atleta IS NULL')
            ->orderByRaw('FIELD(ea.posicao_evento_atleta, ' . implode(', ', array_map(fn ($p) => "'{$p}'", array_keys(Atleta::POSICOES))) . ')')
            ->orderByRaw('cv.camisa_convocacao IS NULL')
            ->orderBy('cv.camisa_convocacao')
            ->orderBy('a.nome_atleta')
            ->get([
                'a.id_atleta', 'a.nome_atleta', 'ea.origem_evento_atleta', 'ea.posicao_evento_atleta', 'ea.titular_evento_atleta',
                'cv.id_convocacao', 'cv.camisa_convocacao',
            ]);
    }

    // Ordena pela data e horário do evento (join; as colunas do jogo continuam sendo as do resultado)
    public function scopeOrdenadosPelaData($query, string $direcao = 'asc')
    {
        return $query->select('tbl_jogos.*')
            ->join('tbl_evento_calendario as ev_ordem', 'ev_ordem.id_evento_calendario', '=', 'tbl_jogos.id_evento')
            ->orderBy('ev_ordem.data_evento_calendario', $direcao)
            ->orderBy('ev_ordem.horario_inicio_evento_calendario', $direcao);
    }

    /**
     * Classificação: conta só jogos da fase de grupos (Fase H, Etapa H4) com placar e evento publicado e ATIVO
     * (não rascunho, cancelado nem oculto; jogo futuro sem placar não vira empate 0×0). Vitória 3, empate 1.
     * Ordem do Brasileirão (dono do projeto, 21/10/2026; seção 8, pergunta 13): ver ordenarClassificacao().
     * Devolve linhas com nome, logo, pj, v, e, d, gm, gc, pontos, sorteio (e o id_time). Com $times (os de um
     * grupo: id_time, nome_time, logo_time), a tabela tem todos eles, os sem jogo com zeros, e só eles; sem
     * $times, quem jogou.
     */
    public static function classificacao(Collection $jogos, ?Collection $times = null): array
    {
        $linhaVazia = fn ($id, $nome, $logo) => [
            'id_time' => $id === null ? null : (int) $id,
            'nome' => $nome ?? '-', 'logo' => $logo,
            'pj' => 0, 'v' => 0, 'e' => 0, 'd' => 0, 'gm' => 0, 'gc' => 0, 'pontos' => 0, 'sorteio' => false,
        ];
        $contados = [];
        $tabela = [];
        foreach ($times ?? [] as $time) {
            $tabela[(int) $time->id_time] = $linhaVazia($time->id_time, $time->nome_time, $time->logo_time);
        }

        foreach ($jogos as $jogo) {
            if (! $jogo->contaNaClassificacao() || ! $jogo->temPlacar() || ! $jogo->evento?->estaPublicado()
                || $jogo->evento->status_evento_calendario !== 'ATIVO') {
                continue;
            }
            $contados[] = $jogo;

            $lados = [
                [$jogo->id_time_casa, $jogo->timeCasa, $jogo->placar_time_casa_jogos, $jogo->placar_time_visitante_jogos],
                [$jogo->id_time_visitante, $jogo->timeVisitante, $jogo->placar_time_visitante_jogos, $jogo->placar_time_casa_jogos],
            ];

            foreach ($lados as [$id, $time, $pro, $contra]) {
                // Com os times do grupo, o adversário de outro grupo não entra nesta tabela
                if ($times !== null && ! isset($tabela[(int) $id])) {
                    continue;
                }
                $tabela[$id] ??= $linhaVazia($id, $time->nome_time ?? null, $time->logo_time ?? null);

                $linha = &$tabela[$id];
                $linha['pj']++;
                $linha['gm'] += $pro;
                $linha['gc'] += $contra;

                if ($pro > $contra) {
                    $linha['v']++;
                    $linha['pontos'] += 3;
                } elseif ((int) $pro === (int) $contra) {
                    $linha['e']++;
                    $linha['pontos']++;
                } else {
                    $linha['d']++;
                }
                unset($linha);
            }
        }

        return self::ordenarClassificacao($tabela, $contados);
    }

    /**
     * Desempate do Brasileirão (dono do projeto, 21/10/2026): pontos, vitórias, saldo de gols, gols marcados e,
     * com só DOIS times empatados nisso tudo, o confronto direto (pontos nos jogos entre eles, de $jogos: os que
     * contaram na tabela). Os cartões ficam de fora (só os atletas da AACJ têm cartão; os adversários, nunca).
     * Empatados em tudo (três ou mais, ou dois sem vencedor no confronto direto) e que já jogaram ficam com
     * 'sorteio' = true (no fim, a ordem sai por sorteio, fora do sistema) e, enquanto isso, pelo nome (sem acentos
     * e sem maiúsculas: "Águias" antes de "Zebra"), para a ordem nunca ficar ao acaso.
     */
    private static function ordenarClassificacao(array $tabela, array $jogos): array
    {
        $chave = fn (array $linha) => [$linha['pontos'], $linha['v'], $linha['gm'] - $linha['gc'], $linha['gm']];
        $nome  = fn (array $linha) => mb_strtolower(Str::ascii($linha['nome']));

        usort($tabela, fn ($a, $b) => [...$chave($b), $nome($a)] <=> [...$chave($a), $nome($b)]);

        // Blocos de empatados nos quatro critérios (já vizinhos, pela ordenação acima)
        $blocos = [];
        foreach ($tabela as $linha) {
            $blocos[implode('|', $chave($linha))][] = $linha;
        }

        $ordenada = [];
        foreach ($blocos as $bloco) {
            if (count($bloco) === 2) {
                $pontos = self::pontosNoConfrontoDireto($bloco[0]['id_time'], $bloco[1]['id_time'], $jogos);
                if ($pontos[0] !== $pontos[1]) {
                    $ordenada = [...$ordenada, ...($pontos[0] > $pontos[1] ? $bloco : array_reverse($bloco))];
                    continue;
                }
            }
            if (count($bloco) > 1 && $bloco[0]['pj'] > 0) {
                $bloco = array_map(fn ($linha) => [...$linha, 'sorteio' => true], $bloco);
            }
            $ordenada = [...$ordenada, ...$bloco];
        }

        return $ordenada;
    }

    // Pontos de cada um dos dois times ([a, b]) nos jogos entre eles (vitória 3, empate 1)
    private static function pontosNoConfrontoDireto(?int $idA, ?int $idB, array $jogos): array
    {
        $pontos = [0, 0];
        foreach ($jogos as $jogo) {
            $casa = (int) $jogo->id_time_casa;
            $fora = (int) $jogo->id_time_visitante;
            if (! (($casa === $idA && $fora === $idB) || ($casa === $idB && $fora === $idA))) {
                continue;
            }

            $golsA = $casa === $idA ? $jogo->placar_time_casa_jogos : $jogo->placar_time_visitante_jogos;
            $golsB = $casa === $idA ? $jogo->placar_time_visitante_jogos : $jogo->placar_time_casa_jogos;
            if ((int) $golsA > (int) $golsB) {
                $pontos[0] += 3;
            } elseif ((int) $golsA < (int) $golsB) {
                $pontos[1] += 3;
            } else {
                $pontos[0]++;
                $pontos[1]++;
            }
        }

        return $pontos;
    }

    // ── Escalação (tbl_evento_atleta.id_time) ───────────────────────────────

    /**
     * Times em que se pode escalar: o mandante e o visitante, só INTERNO (time externo não tem atleta
     * da escolinha). O atleta fica num time só: a inscrição é única por (evento, atleta), com um id_time.
     */
    public function timesEscalaveis(): Collection
    {
        return collect([$this->timeCasa, $this->timeVisitante])
            ->filter(fn ($time) => $time && $time->tipo_time === 'INTERNO')
            ->values();
    }

    // Motivo para não escalar no time, ou null se pode (null = tirar da escalação, sempre pode)
    public function erroDeEscalacao(?int $idTime): ?string
    {
        if ($idTime === null) {
            return null;
        }

        if (! in_array($idTime, [(int) $this->id_time_casa, (int) $this->id_time_visitante], true)) {
            return 'Escolha o mandante ou o visitante deste jogo.';
        }

        return $this->timesEscalaveis()->contains('id_time', $idTime)
            ? null
            : 'Time externo não tem atletas da escolinha: não dá para escalar nele.';
    }

    // ── Idade no campeonato ─────────────────────────────────────────────────

    /**
     * Campeonato é por faixa de idade (professor, 09/10/2026): o mais novo pode jogar numa categoria acima
     * (Sub-13 no Sub-15), o mais velho nunca joga abaixo. Devolve [id_atleta => "Nome (16 anos)"] dos atletas
     * informados que passam da idade máxima da categoria do jogo (Fase H, Etapa H3; antes, a do campeonato),
     * com a idade pelo ano do jogo (Categoria::idadeNoAno). Amistoso e jogo sem categoria não têm limite: [].
     */
    public function acimaDaIdade(array $idsAtletas): array
    {
        $ano = (int) ($this->evento?->data_evento_calendario ?? now())->format('Y');

        return $this->categoriaDoJogo()?->atletasAcimaDaIdade($idsAtletas, $ano) ?? [];
    }

    // Faixa do jogo para as mensagens: "Sub-15 Masculino, até 15 anos" (null sem limite de idade)
    public function limiteDeIdade(): ?string
    {
        return $this->categoriaDoJogo()?->limite_de_idade;
    }

    /**
     * Categoria do jogo de campeonato (Fase H, Etapa H3): a do evento, uma das categorias do campeonato,
     * escolhida no formulário. Vale para a idade, a convocação e os times. Amistoso: null (a categoria dele
     * é só exibição, sem limite de idade).
     */
    public function categoriaDoJogo(): ?Categoria
    {
        return $this->id_campeonato ? $this->evento?->categoria : null;
    }

    private function idCategoriaDoJogo(): ?int
    {
        return $this->id_campeonato && $this->evento?->id_categoria ? (int) $this->evento->id_categoria : null;
    }

    // Motivo para o atleta não jogar este jogo por passar da idade do campeonato, ou null se pode
    public function erroDeIdade(int $idAtleta): ?string
    {
        $acima = $this->acimaDaIdade([$idAtleta]);

        return $acima ? "{$acima[$idAtleta]} passa da idade do campeonato ({$this->limiteDeIdade()}): "
            . 'atleta mais velho não joga em categoria abaixo da idade dele.' : null;
    }

    // Trecho da mensagem para quem ficou de fora de uma inscrição em massa ($quem: "da convocação", "da categoria")
    public function textoAcimaDaIdade(array $acima, string $quem = 'da convocação'): string
    {
        return $acima ? ' ' . count($acima) . " atleta(s) {$quem} ficaram de fora por passar da idade do campeonato"
            . " ({$this->limiteDeIdade()}): " . implode(', ', $acima) . '.' : '';
    }

    // ── Quem joga por qual time (convocação no campeonato, escolha no amistoso) ──

    /**
     * Fase G: o jogo de campeonato tira os atletas da convocação daquele campeonato (tbl_convocacao, Etapa 2);
     * o amistoso não tem lista automática: o admin escolhe os atletas no próprio jogo (Etapa 3), e todos jogam
     * pelo único time da AACJ dele. O elenco fixo (tbl_atleta_time) não inscreve mais em jogo nenhum.
     */
    public function pelaConvocacao(): bool
    {
        return ! $this->ehAmistoso();
    }

    // Amistoso: os atletas são escolhidos na tela do jogo ("Escolher atletas"), sem lista automática
    public function escolheAtletas(): bool
    {
        return $this->ehAmistoso();
    }

    /**
     * Atletas que jogam pelos times escaláveis deste jogo, só ATIVO: [id_atleta => [id_time]]. No campeonato,
     * os convocados (um time por atleta); no amistoso, nenhum (escolhidos no jogo).
     */
    public function atletasPorTime(): array
    {
        return self::atletasDosTimes($this->timesEscalaveis()->pluck('id_time')->all(), $this->id_campeonato, idCategoria: $this->idCategoriaDoJogo());
    }

    /**
     * Regra única da fonte, usada também antes de o jogo existir (conflito na criação e na troca de time):
     * com campeonato, a convocação dele na categoria do jogo (Fase H, Etapa H3; sem categoria, todas); sem
     * campeonato (amistoso), ninguém entra automaticamente.
     */
    public static function atletasDosTimes(array $idsTimes, $idCampeonato, bool $soAtivos = true, ?int $idCategoria = null): array
    {
        return $idCampeonato ? self::convocadosDosTimes((int) $idCampeonato, $idsTimes, $soAtivos, $idCategoria) : [];
    }

    // ── Amistoso (Fase G, Etapa 3) ──────────────────────────────────────────

    /**
     * Amistoso é um time da AACJ (interno) contra um adversário (externo): AACJ contra AACJ é treino, e dois
     * externos não têm atletas da escolinha. Motivo para não salvar, ou null se pode.
     */
    public static function erroDosTimesDoAmistoso(int $idCasa, int $idVisitante): ?string
    {
        $internos = count(self::idsInternos([$idCasa, $idVisitante]));

        return match ($internos) {
            1       => null,
            2       => 'Amistoso é entre um time da AACJ e um adversário externo: AACJ contra AACJ é treino (crie pelo Calendário).',
            default => 'Amistoso precisa de um time da AACJ (interno): dois times externos não têm atletas da escolinha.',
        };
    }

    // O time da AACJ do amistoso (o único interno do jogo); null se não houver
    public function timeDaAcj(): ?Time
    {
        return $this->timesEscalaveis()->first();
    }

    /**
     * Amistoso: todo inscrito joga pelo time da AACJ do jogo. Depois de trocar esse time (ou para quem ficou
     * sem time), passa os inscritos para ele. Devolve quantos mudaram de time.
     */
    public function escalarNoTimeDaAcj(): int
    {
        $time = $this->timeDaAcj();

        return ! $time ? 0 : EventoAtleta::where('id_evento_calendario', $this->id_evento)
            ->where(fn ($q) => $q->whereNull('id_time')->orWhere('id_time', '<>', $time->id_time))
            ->update(EventoAtleta::aoMudarDeTime($time->id_time));
    }

    /**
     * Convocados (tbl_convocacao) do campeonato pelos times informados, na categoria informada (a do jogo):
     * [id_atleta => [id_time]]. Só atletas ATIVO, salvo $soAtivos = false. A convocação já garante um time por
     * atleta em cada categoria do campeonato.
     */
    public static function convocadosDosTimes(int $idCampeonato, array $idsTimes, bool $soAtivos = true, ?int $idCategoria = null): array
    {
        if (! $idsTimes) {
            return [];
        }

        return DB::table('tbl_convocacao as cv')
            ->join('tbl_atletas as a', 'a.id_atleta', '=', 'cv.id_atleta')
            ->when($soAtivos, fn ($q) => $q->where('a.status_atleta', 'ATIVO'))
            ->when($idCategoria, fn ($q) => $q->where('cv.id_categoria', $idCategoria))
            ->where('cv.id_campeonato', $idCampeonato)
            ->whereIn('cv.id_time', $idsTimes)
            ->orderBy('cv.id_atleta')
            ->get(['cv.id_atleta', 'cv.id_time'])
            ->mapWithKeys(fn ($linha) => [(int) $linha->id_atleta => [(int) $linha->id_time]])
            ->all();
    }

    // Camisa da convocação de cada atleta pelos times deste jogo: [id_atleta => 10] (amistoso: []). Só os times do
    // jogo: o mesmo atleta pode ter outra camisa no time de outra categoria do campeonato (Fase H, Etapa H2)
    public function camisas(): array
    {
        return ! $this->pelaConvocacao() ? [] : Convocacao::where('id_campeonato', $this->id_campeonato)
            ->whereIn('id_time', [$this->id_time_casa, $this->id_time_visitante])
            ->pluck('camisa_convocacao', 'id_atleta')
            ->mapWithKeys(fn ($camisa, $idAtleta) => [(int) $idAtleta => (int) $camisa])
            ->all();
    }

    // Times internos (os da AACJ, os únicos com atletas da escolinha) entre os informados
    public static function idsInternos(array $idsTimes): array
    {
        return Time::whereIn('id_time', $idsTimes)->where('tipo_time', 'INTERNO')->pluck('id_time')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Inscreve os convocados dos times internos do jogo de campeonato (atletasPorTime; origem CONVOCACAO,
     * INSCRICAO para cada um, pelo inscrever()), já escalados no time da convocação; quem já está inscrito sem
     * time é escalado. Ninguém sai. No amistoso não faz nada (os atletas são escolhidos no jogo).
     * Quem passa da idade do campeonato fica de fora (nem inscrito nem escalado; 'acima_da_idade', com os
     * nomes de quem não estava inscrito). 'nos_dois' e 'inscritos_sem_time' ficam 0 desde que a convocação
     * garante um time por atleta (antes, com o elenco, o atleta dos dois times entrava sem time).
     * Usado ao criar o jogo, no "Preencher pela convocação" e na troca de time. Devolve os números para a
     * mensagem e quem entrou (para não receber também a ALTERACAO da mesma edição).
     */
    public function inscreverElenco(?int $idUsuario): array
    {
        return DB::transaction(function () use ($idUsuario) {
            $evento     = $this->evento;
            $elencos    = $this->atletasPorTime();
            $origem     = 'CONVOCACAO';
            $inscricoes = $evento->inscricoes()->get(['id_atleta', 'id_time'])->keyBy('id_atleta');
            $acima      = $this->acimaDaIdade(array_keys($elencos));

            $resultado = ['inscritos_com_time' => 0, 'inscritos_sem_time' => 0, 'escalados' => 0,
                'nos_dois' => 0, 'ids_entraram' => [], 'ids_mexidos' => [],
                'acima_da_idade' => array_diff_key($acima, $inscricoes->all())];

            foreach ($elencos as $idAtleta => $times) {
                if (isset($acima[$idAtleta])) {
                    continue;
                }

                $idTime = count($times) === 1 ? $times[0] : null;
                $resultado['nos_dois'] += $idTime === null ? 1 : 0;
                $inscricao = $inscricoes->get($idAtleta);

                if (! $inscricao) {
                    if ($evento->inscrever($idAtleta, $origem, $idUsuario, $idTime)) {
                        $resultado[$idTime === null ? 'inscritos_sem_time' : 'inscritos_com_time']++;
                        $resultado['ids_entraram'][] = $idAtleta;
                        $resultado['ids_mexidos'][]  = $idAtleta;
                    }
                } elseif ($inscricao->id_time === null && $idTime !== null) {
                    $resultado['escalados'] += $evento->inscricoes()->where('id_atleta', $idAtleta)->update(EventoAtleta::aoMudarDeTime($idTime));
                    $resultado['ids_mexidos'][] = $idAtleta;
                }
            }

            return $resultado;
        });
    }

    // Atletas ativos da convocação (ou do elenco, no amistoso) dos times do jogo que ainda não estão inscritos
    // (para a tela do jogo), sem quem passa da idade do campeonato (não entra automaticamente)
    public function idsFaltantesDoElenco(): array
    {
        $inscritos = $this->evento->inscricoes()->pluck('id_atleta')->map(fn ($id) => (int) $id)->all();
        $faltantes = array_values(array_diff(array_keys($this->atletasPorTime()), $inscritos));

        return array_values(array_diff($faltantes, array_keys($this->acimaDaIdade($faltantes))));
    }

    // Times internos do jogo de campeonato sem nenhum atleta ativo convocado: o jogo fica sem inscritos daquele
    // lado (amistoso: nenhum, os atletas são escolhidos no jogo)
    public function timesSemElenco(): Collection
    {
        if ($this->escolheAtletas()) {
            return collect();
        }

        $comElenco = collect($this->atletasPorTime())->flatten()->unique();

        return $this->timesEscalaveis()->reject(fn ($time) => $comElenco->contains($time->id_time))->values();
    }

    /**
     * Troca de time (jogo ainda não concluído; quem chama confere): sai quem entrou automaticamente (origem
     * CONVOCACAO ou ELENCO) por um time que saiu do jogo, a não ser que também jogue por um time que ficou;
     * as INDIVIDUAL ficam (se estavam num time que saiu, ficam sem time); entra a convocação (ou o elenco)
     * do time novo. Quem sai recebe a REMOCAO; quem entra, a INSCRICAO. Devolve inscreverElenco() + 'sairam'
     * e 'sem_time'.
     */
    public function sincronizarPeloElenco(array $idsTimesQueSairam, ?int $idUsuario): array
    {
        return DB::transaction(function () use ($idsTimesQueSairam, $idUsuario) {
            $evento = $this->evento;
            $saindo = $this->idsQueSaemNaTroca($idsTimesQueSairam, $this->timesEscalaveis()->pluck('id_time')->all());

            $sairam = $saindo ? $evento->inscricoes()->whereIn('id_atleta', $saindo)->delete() : 0;
            $evento->atletasNotificados += Notificacao::remocao($evento, $saindo, $idUsuario);

            // Quem fica estava escalado num time que saiu: perde o time; se for do elenco do time novo, o
            // inscreverElenco() escala de novo. 'sem_time' conta só quem terminou sem time
            $escalaveis   = $this->timesEscalaveis()->pluck('id_time')->all();
            $foraDosTimes = $evento->inscricoes()->whereNotNull('id_time')->whereNotIn('id_time', $escalaveis)->pluck('id_atleta')->all();
            $this->limparEscalacaoForaDosTimes();

            $elenco  = $this->inscreverElenco($idUsuario);
            $semTime = $foraDosTimes ? $evento->inscricoes()->whereIn('id_atleta', $foraDosTimes)->whereNull('id_time')->count() : 0;

            return [...$elenco, 'sairam' => $sairam, 'sem_time' => $semTime];
        });
    }

    /**
     * Inscritos que saem na troca de time: entraram automaticamente (CONVOCACAO ou ELENCO), jogam por um time
     * que saiu e por nenhum time que fica (convocação do campeonato do jogo, ou elenco no amistoso; qualquer
     * status do atleta, como na sincronização pela categoria). Regra única, usada pela sincronização e pelo
     * alerta de conflito antes de salvar. Inscrição ELENCO antiga num jogo de campeonato só sai se o atleta
     * for convocado pelo time que saiu (as demais ficam, como as individuais).
     */
    public function idsQueSaemNaTroca(array $idsTimesQueSairam, array $idsTimesQueFicam): array
    {
        $jogam      = fn (array $idsTimes) => array_keys(self::atletasDosTimes(self::idsInternos($idsTimes), $this->id_campeonato, soAtivos: false, idCategoria: $this->idCategoriaDoJogo()));
        $candidatos = array_values(array_diff($jogam($idsTimesQueSairam), $jogam($idsTimesQueFicam)));

        return ! $candidatos ? [] : $this->evento->inscricoes()
            ->whereIn('origem_evento_atleta', ['CONVOCACAO', 'ELENCO'])
            ->whereIn('id_atleta', $candidatos)
            ->pluck('id_atleta')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Quem ficará inscrito se o jogo passar a ser entre $idsTimesNovos (para o alerta de conflito antes de
     * salvar a troca): os inscritos de hoje, menos quem sai, mais os convocados (ou o elenco, no amistoso)
     * ativos dos times internos novos.
     */
    public function idsInscritosDepoisDaTroca(array $idsTimesNovos): array
    {
        $atuais = [(int) $this->id_time_casa, (int) $this->id_time_visitante];
        $sairam = array_values(array_diff($atuais, $idsTimesNovos));
        $hoje   = $this->evento->inscricoes()->pluck('id_atleta')->map(fn ($id) => (int) $id)->all();

        $ficam = array_diff($hoje, $this->idsQueSaemNaTroca($sairam, $idsTimesNovos));

        return array_values(array_unique([...$ficam, ...array_keys(self::atletasDosTimes(self::idsInternos($idsTimesNovos), $this->id_campeonato, idCategoria: $this->idCategoriaDoJogo()))]));
    }

    // Depois de trocar os times: quem estava escalado num time que não está mais no jogo (ou não é
    // escalável) fica sem time. A inscrição continua. Devolve quantos saíram da escalação.
    public function limparEscalacaoForaDosTimes(): int
    {
        return EventoAtleta::where('id_evento_calendario', $this->id_evento)
            ->whereNotNull('id_time')
            ->whereNotIn('id_time', $this->timesEscalaveis()->pluck('id_time'))
            ->update(EventoAtleta::aoMudarDeTime(null));
    }

    // ── Dados do evento ─────────────────────────────────────────────────────

    public function ehAmistoso(): bool
    {
        return $this->id_campeonato === null;
    }

    public function temPlacar(): bool
    {
        return $this->placar_time_casa_jogos !== null && $this->placar_time_visitante_jogos !== null;
    }

    public function temProrrogacao(): bool
    {
        return $this->prorrogacao_time_casa_jogos !== null && $this->prorrogacao_time_visitante_jogos !== null;
    }

    // ── Súmula (Etapa S1, 22/10/2026) ───────────────────────────────────────

    // Gols do time no placar, no tempo normal ou na prorrogação (a disputa de pênaltis não conta)
    public function golsNoPlacar(int $idTime, string $periodo = 'TEMPO_NORMAL'): int
    {
        $casa = $idTime === (int) $this->id_time_casa;

        return (int) ($periodo === 'PRORROGACAO'
            ? ($casa ? $this->prorrogacao_time_casa_jogos : $this->prorrogacao_time_visitante_jogos)
            : ($casa ? $this->placar_time_casa_jogos : $this->placar_time_visitante_jogos));
    }

    // O atleta (ATIVO) está escalado neste time da AACJ, neste jogo
    public function escalouNoTime(int $idAtleta, int $idTime): bool
    {
        return $this->timesEscalaveis()->contains('id_time', $idTime)
            && EventoAtleta::where('id_evento_calendario', $this->id_evento)->where('id_atleta', $idAtleta)->where('id_time', $idTime)
                ->whereHas('atleta', fn ($q) => $q->where('status_atleta', 'ATIVO'))->exists();
    }

    /**
     * Situação da súmula de cada time da AACJ no jogo: gols no placar (tempo normal + prorrogação), lançados e quantos
     * faltam (só aviso: o admin pode deixar sem autor). [id_time => ['nome', 'placar', 'lancados', 'faltam']]
     */
    public function resumoDaSumula(): array
    {
        $lancados = $this->gols()->selectRaw('id_time, COUNT(*) as total')->groupBy('id_time')->pluck('total', 'id_time');

        return $this->timesEscalaveis()->mapWithKeys(function (Time $time) use ($lancados) {
            $placar = $this->golsNoPlacar($time->id_time) + $this->golsNoPlacar($time->id_time, 'PRORROGACAO');
            $feitos = (int) ($lancados[$time->id_time] ?? 0);

            return [$time->id_time => ['nome' => $time->nome_time, 'placar' => $placar, 'lancados' => $feitos, 'faltam' => max(0, $placar - $feitos)]];
        })->all();
    }

    /**
     * Por que a edição do jogo não pode mudar os times ou o placar sem mexer na súmula (null = pode): com gols ou
     * cartões lançados, os times não mudam; o placar de cada time não fica abaixo dos gols já lançados nele (em cada
     * período). Usado pelo placar rápido e pelo formulário do jogo.
     *
     * @param array $dados id_time_casa, id_time_visitante e as colunas de placar e prorrogação (novos valores)
     */
    public function erroDaSumulaNaEdicao(array $dados): ?string
    {
        $gols = $this->gols()->get(['id_time', 'periodo_jogo_gol']);
        if ($gols->isEmpty() && ! $this->cartoes()->exists()) {
            return null;
        }

        $mudouTimes = (int) $dados['id_time_casa'] !== (int) $this->id_time_casa
            || (int) $dados['id_time_visitante'] !== (int) $this->id_time_visitante;
        if ($mudouTimes) {
            return 'Este jogo tem súmula (gols ou cartões lançados): remova-os na tela do jogo antes de trocar os times.';
        }

        $colunas = [
            (int) $this->id_time_casa      => ['TEMPO_NORMAL' => 'placar_time_casa_jogos', 'PRORROGACAO' => 'prorrogacao_time_casa_jogos'],
            (int) $this->id_time_visitante => ['TEMPO_NORMAL' => 'placar_time_visitante_jogos', 'PRORROGACAO' => 'prorrogacao_time_visitante_jogos'],
        ];
        foreach ($gols->groupBy(fn ($g) => $g->id_time . '|' . $g->periodo_jogo_gol) as $chave => $doGrupo) {
            [$idTime, $periodo] = explode('|', $chave);
            $novo = $dados[$colunas[(int) $idTime][$periodo]] ?? null;
            if ($novo === null || (int) $novo < $doGrupo->count()) {
                $time = (int) $idTime === (int) $this->id_time_casa ? $this->timeCasa : $this->timeVisitante;

                return "A súmula já tem {$doGrupo->count()} gol(s) do {$time->nome_time} ("
                    . mb_strtolower(JogoGol::PERIODOS[$periodo]) . '): o placar não pode ficar abaixo disso. Remova gols da súmula antes.';
            }
        }

        return null;
    }

    /**
     * Cria o evento e o jogo, como a tela de Jogos (e o gerador de rodadas, Fase I, Etapa I2): responsável =
     * $idUsuario; a categoria do evento é a do jogo; jogo com time da AACJ nasce RASCUNHO (Fase 10, Etapa 4), com
     * os convocados (ou ninguém, no amistoso) inscritos e escalados, sem avisar ninguém; jogo sem time da AACJ
     * nasce publicado (não há quem avisar, e ele conta na classificação). Chamar dentro de uma transação.
     * Devolve [evento, jogo, resultado de inscreverElenco()].
     */
    public static function criarComEvento(?int $idUsuario, array $dadosJogo, array $dadosEvento): array
    {
        $semAcj = ! self::idsInternos([$dadosJogo['id_time_casa'], $dadosJogo['id_time_visitante']]);
        $evento = EventoCalendario::criarPor($idUsuario, $dadosEvento, inscreverCategoria: false, rascunho: ! $semAcj);

        $jogo = self::create([...$dadosJogo, 'id_evento' => $evento->id_evento_calendario]);
        $jogo->setRelation('evento', $evento); // a mesma instância conta os notificados
        $evento->setRelation('jogo', $jogo);   // o aviso de categoria sabe que é jogo de campeonato

        return [$evento, $jogo, $jogo->inscreverElenco($idUsuario)];
    }

    /**
     * Por que o rascunho não pode ser publicado (null = pode): inscrito ativo que passa da idade do campeonato
     * (remover antes). Usado pelo "Publicar" do jogo e pelo "Publicar a rodada" (Fase I, Etapa I2).
     */
    public function erroParaPublicar(): ?string
    {
        if ($this->evento->estaPublicado()) {
            return null;
        }

        $ativos = $this->evento->inscricoes()->whereHas('atleta', fn ($q) => $q->where('status_atleta', 'ATIVO'))->pluck('id_atleta')->all();
        if (! $acima = $this->acimaDaIdade($ativos)) {
            return null;
        }

        return implode(', ', $acima) . ' ' . (count($acima) === 1 ? 'passa' : 'passam')
            . " da idade do campeonato ({$this->limiteDeIdade()}). Remova a inscrição antes de publicar.";
    }

    // ── Rodada, ida e volta, prorrogação e pênaltis (Fase I, Etapa I1b) ────────
    // Regras do dono do projeto (21/10/2026): ida e volta empatada no agregado vai direto para os pênaltis, sem
    // gol fora e sem prorrogação (CBF desde 2023); jogo único empatado no mata-mata tem prorrogação e depois
    // pênaltis. O placar do jogo é o do tempo normal; a prorrogação e os pênaltis ficam à parte.

    /**
     * Erros do formulário do jogo nas colunas da Etapa I1b ([campo => mensagem]; vazio = tudo certo). $d: os
     * dados já validados no formato do banco (inteiros ou null): id_campeonato, id_categoria, fase_jogo,
     * rodada_jogo, id_jogo_ida, id_time_casa, id_time_visitante, placar_*, prorrogacao_* e penaltis_*.
     * $atual: o jogo editado (null na criação).
     */
    public static function errosDoResultado(array $d, ?self $atual = null): array
    {
        $erros     = [];
        $mataMata  = $d['fase_jogo'] !== null && $d['fase_jogo'] !== self::FASE_PADRAO;
        $soUm      = fn (string $casa, string $fora) => ($d[$casa] === null) !== ($d[$fora] === null);
        $temPlacar = $d['placar_time_casa_jogos'] !== null && $d['placar_time_visitante_jogos'] !== null;
        $volta     = $atual?->jogoVolta;   // este jogo já é a ida de outro
        $ida       = null;

        if ($d['rodada_jogo'] !== null && $d['fase_jogo'] !== self::FASE_PADRAO) {
            $erros['rodada_jogo'] = 'A rodada é só da fase de grupos.';
        }

        // Ida e volta: a volta aponta para a ida, do mesmo campeonato, categoria e fase, com os times invertidos
        if ($d['id_jogo_ida'] !== null) {
            $ida = self::with('evento', 'jogoVolta')->find($d['id_jogo_ida']);
            $erro = match (true) {
                ! $mataMata                                   => 'Jogo de volta só no mata-mata.',
                ! $ida || $ida->id_jogo === $atual?->id_jogo  => 'Escolha o jogo de ida na lista.',
                $volta !== null                               => 'Este jogo já é a ida de outro jogo: não pode ser também uma volta.',
                $ida->id_jogo_ida !== null                    => 'O jogo escolhido já é um jogo de volta.',
                $ida->jogoVolta && $ida->jogoVolta->id_jogo !== $atual?->id_jogo
                                                              => 'O jogo de ida escolhido já tem jogo de volta.',
                default                                       => self::erroDoConfronto(self::dadosDoConfronto($ida), $d),
            };
            if ($erro) {
                $erros['id_jogo_ida'] = $erro;
                $ida = null;
            }
        }
        // Este jogo é a ida de uma volta: a volta precisa continuar combinando com ele
        if ($volta) {
            $erro = $mataMata
                ? self::erroDoConfronto($d, self::dadosDoConfronto($volta))
                : 'Este jogo tem jogo de volta: mantenha a fase de mata-mata (ou tire o vínculo no jogo de volta antes).';
            if ($erro) {
                $erros['fase_jogo'] = $erro;
            }
        }
        $idaOuVolta = $d['id_jogo_ida'] !== null || $volta !== null;

        // Prorrogação: só no jogo único do mata-mata, depois de empate no tempo normal
        if ($soUm('prorrogacao_time_casa_jogos', 'prorrogacao_time_visitante_jogos')) {
            $erros['prorrogacao_time_casa_jogos'] = 'Preencha a prorrogação dos dois times, ou deixe as duas vazias.';
        } elseif ($d['prorrogacao_time_casa_jogos'] !== null) {
            $erro = match (true) {
                ! $mataMata   => 'Prorrogação só no mata-mata.',
                $idaOuVolta   => 'Na ida e volta não há prorrogação: o empate no agregado vai direto para os pênaltis.',
                ! $temPlacar || $d['placar_time_casa_jogos'] !== $d['placar_time_visitante_jogos']
                              => 'Prorrogação só depois de empate no tempo normal.',
                default       => null,
            };
            if ($erro) {
                $erros['prorrogacao_time_casa_jogos'] = $erro;
            }
        }

        // Pênaltis: só no mata-mata, no jogo que decide, com empate (no agregado, ou no tempo normal e na prorrogação)
        if ($soUm('penaltis_time_casa_jogos', 'penaltis_time_visitante_jogos')) {
            $erros['penaltis_time_casa_jogos'] = 'Preencha os pênaltis dos dois times, ou deixe os dois vazios.';
        } elseif ($d['penaltis_time_casa_jogos'] !== null) {
            $erro = match (true) {
                ! $mataMata                                                   => 'Pênaltis só no mata-mata.',
                $d['penaltis_time_casa_jogos'] === $d['penaltis_time_visitante_jogos']
                                                                              => 'A disputa de pênaltis não termina empatada.',
                $volta !== null                                               => 'Na ida e volta, os pênaltis ficam no jogo de volta.',
                ! $temPlacar                                                  => 'Lance o placar antes dos pênaltis.',
                $d['id_jogo_ida'] !== null                                    => self::erroDosPenaltisDaVolta($d, $ida),
                $d['placar_time_casa_jogos'] !== $d['placar_time_visitante_jogos']
                                                                              => 'Pênaltis só com empate.',
                $d['prorrogacao_time_casa_jogos'] === null                    => 'Lance a prorrogação antes dos pênaltis (jogo único: prorrogação e depois pênaltis).',
                $d['prorrogacao_time_casa_jogos'] !== $d['prorrogacao_time_visitante_jogos']
                                                                              => 'Pênaltis só se a prorrogação também terminar empatada.',
                default                                                       => null,
            };
            if ($erro) {
                $erros['penaltis_time_casa_jogos'] = $erro;
            }
        }

        return $erros;
    }

    // Volta: pênaltis só com o placar da ida lançado e empate no agregado
    private static function erroDosPenaltisDaVolta(array $d, ?self $ida): ?string
    {
        if (! $ida) {
            return null;   // o erro já está no id_jogo_ida
        }
        if (! $ida->temPlacar()) {
            return 'Lance o placar do jogo de ida antes dos pênaltis.';
        }

        $agregadoCasa = $d['placar_time_casa_jogos'] + (int) $ida->placar_time_visitante_jogos;
        $agregadoFora = $d['placar_time_visitante_jogos'] + (int) $ida->placar_time_casa_jogos;

        return $agregadoCasa === $agregadoFora ? null : "Pênaltis só com empate no agregado (está {$agregadoCasa} × {$agregadoFora}).";
    }

    // Ida e volta: mesmo campeonato, categoria e fase, e os times invertidos (o mandante da ida é o visitante da volta)
    private static function erroDoConfronto(array $ida, array $volta): ?string
    {
        $combina = (int) $ida['id_campeonato'] === (int) $volta['id_campeonato']
            && (int) $ida['id_categoria'] === (int) $volta['id_categoria']
            && $ida['fase_jogo'] === $volta['fase_jogo']
            && (int) $ida['id_time_casa'] === (int) $volta['id_time_visitante']
            && (int) $ida['id_time_visitante'] === (int) $volta['id_time_casa'];

        return $combina ? null
            : 'A volta precisa ser do mesmo campeonato, categoria e fase da ida, com os times invertidos (o mandante da ida é o visitante da volta).';
    }

    private static function dadosDoConfronto(self $jogo): array
    {
        return [
            'id_campeonato' => $jogo->id_campeonato, 'id_categoria' => $jogo->evento?->id_categoria, 'fase_jogo' => $jogo->fase_jogo,
            'id_time_casa' => $jogo->id_time_casa, 'id_time_visitante' => $jogo->id_time_visitante,
        ];
    }

    /**
     * Quem passa (ou vence, na final e na disputa de 3º) no mata-mata: ['id_time', 'como' (tempo normal,
     * prorrogação, agregado ou pênaltis), 'agregado' ([mandante, visitante] deste jogo, só na volta) e 'texto'
     * ("Botafogo passa nos pênaltis (4 × 3)")]. null fora do mata-mata, na ida (decide na volta), sem placar
     * ou empatado sem o desempate lançado. Na volta, pênaltis lançados com o agregado já decidido não contam.
     */
    public function resultadoDoConfronto(): ?array
    {
        if (! $this->ehMataMata() || ! $this->temPlacar() || $this->jogoVolta) {
            return null;
        }

        $casa = (int) $this->placar_time_casa_jogos;
        $fora = (int) $this->placar_time_visitante_jogos;
        $agregado = null;
        $como = 'tempo normal';

        if ($this->id_jogo_ida) {
            if (! $this->jogoIda?->temPlacar()) {
                return null;
            }
            $casa += (int) $this->jogoIda->placar_time_visitante_jogos;
            $fora += (int) $this->jogoIda->placar_time_casa_jogos;
            $agregado = [$casa, $fora];
            $como = 'agregado';
        } elseif ($casa === $fora && $this->prorrogacao_time_casa_jogos !== null) {
            $casa += (int) $this->prorrogacao_time_casa_jogos;
            $fora += (int) $this->prorrogacao_time_visitante_jogos;
            $como = 'prorrogação';
        }

        $penaltis = null;
        if ($casa === $fora) {
            if ($this->penaltis_time_casa_jogos === null || $this->penaltis_time_casa_jogos === $this->penaltis_time_visitante_jogos) {
                return null;
            }
            $penaltis = [(int) $this->penaltis_time_casa_jogos, (int) $this->penaltis_time_visitante_jogos];
            [$casa, $fora] = $penaltis;
            $como = 'pênaltis';
        }

        $time  = $casa > $fora ? $this->timeCasa : $this->timeVisitante;
        $verbo = in_array($this->fase_jogo, ['FINAL', 'TERCEIRO'], true) ? 'vence' : 'passa';
        $texto = ($time?->nome_time ?? 'Time') . " {$verbo}" . match ($como) {
            'tempo normal' => '',
            'prorrogação'  => ' na prorrogação',
            'agregado'     => " no agregado ({$agregado[0]} × {$agregado[1]})",
            'pênaltis'     => ($agregado ? " nos pênaltis ({$penaltis[0]} × {$penaltis[1]}), depois do agregado {$agregado[0]} × {$agregado[1]}"
                                         : " nos pênaltis ({$penaltis[0]} × {$penaltis[1]})"),
        };

        return ['id_time' => (int) $time?->id_time, 'como' => $como, 'agregado' => $agregado, 'texto' => $texto];
    }

    /**
     * Opções do select "Jogo de volta de…" do formulário do jogo: os jogos de mata-mata que ainda não são volta,
     * com o campeonato, a categoria, a fase e os times (o JS mostra só os que combinam com o jogo e já tenham a
     * ida com os times invertidos).
     */
    public static function opcoesDeIda(): Collection
    {
        return self::with(['evento', 'timeCasa:id_time,nome_time', 'timeVisitante:id_time,nome_time', 'jogoVolta:id_jogo,id_jogo_ida'])
            ->whereNotNull('tbl_jogos.id_campeonato')
            ->where('tbl_jogos.fase_jogo', '<>', self::FASE_PADRAO)
            ->whereNull('tbl_jogos.id_jogo_ida')
            ->ordenadosPelaData()
            ->get();
    }

    // No JSON da API: quem passa no mata-mata (resultadoDoConfronto) ou null
    public function getConfrontoAttribute(): ?array
    {
        return $this->resultadoDoConfronto();
    }

    // O que vem depois do placar do tempo normal: "prorr. 1 × 1 · pên. 4 × 3" (vazio sem prorrogação nem pênaltis)
    public function getPlacarExtraAttribute(): string
    {
        return collect([
            $this->prorrogacao_time_casa_jogos !== null ? "prorr. {$this->prorrogacao_time_casa_jogos} × {$this->prorrogacao_time_visitante_jogos}" : null,
            $this->penaltis_time_casa_jogos !== null ? "pên. {$this->penaltis_time_casa_jogos} × {$this->penaltis_time_visitante_jogos}" : null,
        ])->filter()->join(' · ');
    }

    // Fase com a rodada ou a perna: "Fase de grupos · Rodada 5", "Oitavas de final · volta" (null no amistoso)
    public function getFaseCompletaAttribute(): ?string
    {
        $perna = match (true) {
            $this->rodada_jogo !== null => 'Rodada ' . $this->rodada_jogo,
            $this->id_jogo_ida !== null => 'volta',
            $this->ehMataMata() && $this->jogoVolta !== null => 'ida',
            default => null,
        };

        return $this->fase_label === null ? null : collect([$this->fase_label, $perna])->filter()->join(' · ');
    }

    public function estaCancelado(): bool
    {
        return (bool) $this->evento?->estaCancelado();
    }

    // Data e horário de início do evento (sem horário: meia-noite, só para ordenar e mostrar a data)
    public function getDataJogoAttribute(): ?Carbon
    {
        return $this->evento ? Carbon::parse($this->evento->dataHoraDoJogo()) : null;
    }

    // "19:00", "19:00 às 21:00" ou "A definir"
    public function getHorarioJogoAttribute(): ?string
    {
        return $this->evento?->horario_texto;
    }

    public function getLocalJogoAttribute(): ?string
    {
        return $this->evento?->local_evento_calendario;
    }

    // Status do evento: ATIVO, CANCELADO ou INATIVO (oculto)
    public function getStatusJogoAttribute(): ?string
    {
        return $this->evento?->status_evento_calendario;
    }

    // API: "Semifinal" (null no amistoso)
    public function getFaseAttribute(): ?string
    {
        return $this->fase_label;
    }

    // API: a categoria do jogo (a do evento; no campeonato, uma das categorias dele)
    public function getIdCategoriaAttribute(): ?int
    {
        return $this->evento?->id_categoria === null ? null : (int) $this->evento->id_categoria;
    }

    // Título do evento do jogo, gerado pelos times (decisão 5 da Fase 6)
    public static function tituloPara(int $idTimeCasa, int $idTimeVisitante): string
    {
        $nomes = Time::whereIn('id_time', [$idTimeCasa, $idTimeVisitante])->pluck('nome_time', 'id_time');

        return ($nomes[$idTimeCasa] ?? '?') . ' x ' . ($nomes[$idTimeVisitante] ?? '?');
    }
}
