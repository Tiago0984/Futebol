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

    protected $fillable = [
        'id_evento',
        'id_campeonato',
        'id_time_casa',
        'id_time_visitante',
        'placar_time_casa_jogos',
        'placar_time_visitante_jogos',
    ];

    // No JSON da API, os dados do evento vêm achatados (data_jogo e status_jogo mantêm os nomes de antes,
    // para não quebrar o app); o evento em si não vai
    protected $appends = ['data_jogo', 'horario_jogo', 'local_jogo', 'status_jogo'];
    protected $hidden  = ['evento'];

    public function evento()
    {
        return $this->belongsTo(EventoCalendario::class, 'id_evento', 'id_evento_calendario');
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

    // ── Consultas ───────────────────────────────────────────────────────────

    // Jogos que o site mostra: evento ativo ou cancelado (oculto some, como no calendário do site)
    public function scopeVisiveis($query)
    {
        return $query->whereHas('evento', fn ($q) => $q->whereIn('status_evento_calendario', EventoCalendario::STATUS_VISIVEIS));
    }

    // Jogos que a agenda do site mostra (hoje, só os de campeonato): a regra é a do evento,
    // EventoCalendario::daAgendaPublica(), para não ficar em dois lugares. Usado no destaque da home
    public function scopeDaAgendaPublica($query)
    {
        return $query->whereHas('evento', fn ($q) => $q->daAgendaPublica());
    }

    // Quantos jogos já realizados aparecem abaixo dos próximos nas telas de times por jogo
    public const REALIZADOS_NA_TELA = 10;

    /**
     * Jogos para as telas de times por jogo (Times dos amistosos e Times do campeonato): só os não ocultos,
     * com o evento e os dois times (com a categoria) de uma vez, e, em $jogo->escalados, quantos atletas
     * ativos cada time tem escalados no jogo ([id_time => n], numa consulta para todos). Devolve
     * [próximos, realizados]: próximos (não concluídos) do mais perto ao mais longe; realizados (concluídos)
     * do mais recente para trás, no máximo REALIZADOS_NA_TELA.
     */
    public static function separadosParaTelaDeTimes($query): array
    {
        $jogos = $query
            ->whereHas('evento', fn ($q) => $q->where('status_evento_calendario', '<>', 'INATIVO'))
            ->with(['evento', 'timeCasa.categoria', 'timeVisitante.categoria'])
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
     * Atletas ativos escalados por um time neste jogo (tela "jogadores do time no jogo"), por nome, com os
     * dados do elenco desse time quando o atleta está nele (camisa, posição, titular/reserva; null fora do
     * elenco). Uma consulta.
     */
    public function escaladosDoTime(int $idTime): Collection
    {
        return DB::table('tbl_evento_atleta as ea')
            ->join('tbl_atletas as a', 'a.id_atleta', '=', 'ea.id_atleta')
            ->leftJoin('tbl_atleta_time as at', fn ($j) => $j->on('at.id_atleta', '=', 'ea.id_atleta')->where('at.id_time', $idTime))
            ->where('ea.id_evento_calendario', $this->id_evento)
            ->where('ea.id_time', $idTime)
            ->where('a.status_atleta', 'ATIVO')
            ->orderBy('a.nome_atleta')
            ->get([
                'a.id_atleta', 'a.nome_atleta', 'ea.origem_evento_atleta', 'at.id_atleta_time',
                'at.camisa_atleta_time', 'at.posicao_atleta_time', 'at.status_atleta_time',
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
     * Classificação: conta só jogos com placar e evento ATIVO (não cancelado nem oculto; jogo futuro
     * sem placar não vira empate 0×0). Vitória 3, empate 1. Ordem: pontos, vitórias, saldo, gols marcados, nome.
     * Devolve linhas com nome, logo, pj, v, e, d, gm, gc e pontos.
     */
    public static function classificacao(Collection $jogos): array
    {
        $tabela = [];

        foreach ($jogos as $jogo) {
            if (! $jogo->temPlacar() || $jogo->evento?->status_evento_calendario !== 'ATIVO') {
                continue;
            }

            $lados = [
                [$jogo->id_time_casa, $jogo->timeCasa, $jogo->placar_time_casa_jogos, $jogo->placar_time_visitante_jogos],
                [$jogo->id_time_visitante, $jogo->timeVisitante, $jogo->placar_time_visitante_jogos, $jogo->placar_time_casa_jogos],
            ];

            foreach ($lados as [$id, $time, $pro, $contra]) {
                $tabela[$id] ??= [
                    'nome' => $time->nome_time ?? '-', 'logo' => $time->logo_time ?? null,
                    'pj' => 0, 'v' => 0, 'e' => 0, 'd' => 0, 'gm' => 0, 'gc' => 0, 'pontos' => 0,
                ];

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

        // Desempate PROVISÓRIO (CLAUDE.md, seção 8, pergunta 13): pontos, vitórias, saldo de gols,
        // gols marcados e, por fim, nome do time (assim a ordem nunca fica ao acaso). O nome é comparado
        // sem acentos e sem maiúsculas: "Águias" fica antes de "Zebra"
        $nome = fn (array $linha) => mb_strtolower(Str::ascii($linha['nome']));
        usort($tabela, fn ($a, $b) => [$b['pontos'], $b['v'], $b['gm'] - $b['gc'], $b['gm'], $nome($a)]
            <=> [$a['pontos'], $a['v'], $a['gm'] - $a['gc'], $a['gm'], $nome($b)]);

        return $tabela;
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

    /**
     * Atletas do elenco (tbl_atleta_time) dos times escaláveis deste jogo, só atletas ATIVO:
     * [id_atleta => [id_time, ...]]. Quem está nos dois elencos aparece com os dois times.
     */
    public function elencosDoJogo(): array
    {
        return self::elencoDosTimes($this->timesEscalaveis()->pluck('id_time')->all());
    }

    /**
     * Elenco (tbl_atleta_time) dos times informados: [id_atleta => [id_time, ...]]. Só atletas ATIVO,
     * salvo $soAtivos = false. Usado também antes de o jogo existir (conflito na criação e na troca de time).
     */
    public static function elencoDosTimes(array $idsTimes, bool $soAtivos = true): array
    {
        if (! $idsTimes) {
            return [];
        }

        return DB::table('tbl_atleta_time as at')
            ->join('tbl_atletas as a', 'a.id_atleta', '=', 'at.id_atleta')
            ->when($soAtivos, fn ($q) => $q->where('a.status_atleta', 'ATIVO'))
            ->whereIn('at.id_time', $idsTimes)
            ->orderBy('at.id_atleta')
            ->get(['at.id_atleta', 'at.id_time'])
            ->groupBy('id_atleta')
            ->map(fn ($linhas) => $linhas->pluck('id_time')->map(fn ($id) => (int) $id)->unique()->sort()->values()->all())
            ->all();
    }

    // Times internos (só eles têm elenco) entre os informados
    public static function idsInternos(array $idsTimes): array
    {
        return Time::whereIn('id_time', $idsTimes)->where('tipo_time', 'INTERNO')->pluck('id_time')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Inscreve o elenco ativo dos times internos do jogo (origem ELENCO, com INSCRICAO para cada um, pelo
     * inscrever()): quem é de um time só entra escalado nele; quem está nos dois elencos entra sem time, para
     * o admin escolher. Quem já está inscrito sem time e é de um time só é escalado. Ninguém sai.
     * Usado ao criar o jogo, no "Preencher pelo elenco" e na troca de time. Devolve os números para a
     * mensagem e quem entrou (para não receber também a ALTERACAO da mesma edição).
     */
    public function inscreverElenco(?int $idUsuario): array
    {
        return DB::transaction(function () use ($idUsuario) {
            $evento     = $this->evento;
            $elencos    = $this->elencosDoJogo();
            $inscricoes = $evento->inscricoes()->get(['id_atleta', 'id_time'])->keyBy('id_atleta');

            $resultado = ['inscritos_com_time' => 0, 'inscritos_sem_time' => 0, 'escalados' => 0,
                'nos_dois' => 0, 'ids_entraram' => [], 'ids_mexidos' => []];

            foreach ($elencos as $idAtleta => $times) {
                $idTime = count($times) === 1 ? $times[0] : null;
                $resultado['nos_dois'] += $idTime === null ? 1 : 0;
                $inscricao = $inscricoes->get($idAtleta);

                if (! $inscricao) {
                    if ($evento->inscrever($idAtleta, 'ELENCO', $idUsuario, $idTime)) {
                        $resultado[$idTime === null ? 'inscritos_sem_time' : 'inscritos_com_time']++;
                        $resultado['ids_entraram'][] = $idAtleta;
                        $resultado['ids_mexidos'][]  = $idAtleta;
                    }
                } elseif ($inscricao->id_time === null && $idTime !== null) {
                    $resultado['escalados'] += $evento->inscricoes()->where('id_atleta', $idAtleta)->update(['id_time' => $idTime]);
                    $resultado['ids_mexidos'][] = $idAtleta;
                }
            }

            return $resultado;
        });
    }

    // Atletas ativos do elenco dos times do jogo que ainda não estão inscritos (para a tela do jogo)
    public function idsFaltantesDoElenco(): array
    {
        $inscritos = $this->evento->inscricoes()->pluck('id_atleta')->map(fn ($id) => (int) $id)->all();

        return array_values(array_diff(array_keys($this->elencosDoJogo()), $inscritos));
    }

    // Times internos do jogo sem nenhum atleta ativo no elenco (o jogo fica sem inscritos daquele lado)
    public function timesSemElenco(): Collection
    {
        $comElenco = collect($this->elencosDoJogo())->flatten()->unique();

        return $this->timesEscalaveis()->reject(fn ($time) => $comElenco->contains($time->id_time))->values();
    }

    /**
     * Troca de time (jogo ainda não concluído; quem chama confere): sai quem veio pelo elenco (origem ELENCO)
     * de um time que saiu do jogo, a não ser que também seja do elenco de um time que ficou; as INDIVIDUAL
     * ficam (se estavam num time que saiu, ficam sem time); entra o elenco do time novo. Quem sai recebe a
     * REMOCAO; quem entra, a INSCRICAO. Devolve inscreverElenco() + 'sairam' e 'sem_time'.
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
     * Inscritos que saem na troca de time: origem ELENCO, do elenco de um time que saiu e de nenhum time que
     * fica (qualquer status do atleta, como na sincronização pela categoria). Regra única, usada pela
     * sincronização e pelo alerta de conflito antes de salvar.
     */
    public function idsQueSaemNaTroca(array $idsTimesQueSairam, array $idsTimesQueFicam): array
    {
        $doQueSaiu  = array_keys(self::elencoDosTimes(self::idsInternos($idsTimesQueSairam), soAtivos: false));
        $candidatos = array_values(array_diff($doQueSaiu, array_keys(self::elencoDosTimes(self::idsInternos($idsTimesQueFicam), soAtivos: false))));

        return ! $candidatos ? [] : $this->evento->inscricoes()
            ->where('origem_evento_atleta', 'ELENCO')
            ->whereIn('id_atleta', $candidatos)
            ->pluck('id_atleta')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Quem ficará inscrito se o jogo passar a ser entre $idsTimesNovos (para o alerta de conflito antes de
     * salvar a troca): os inscritos de hoje, menos quem sai, mais o elenco ativo dos times internos novos.
     */
    public function idsInscritosDepoisDaTroca(array $idsTimesNovos): array
    {
        $atuais = [(int) $this->id_time_casa, (int) $this->id_time_visitante];
        $sairam = array_values(array_diff($atuais, $idsTimesNovos));
        $hoje   = $this->evento->inscricoes()->pluck('id_atleta')->map(fn ($id) => (int) $id)->all();

        $ficam = array_diff($hoje, $this->idsQueSaemNaTroca($sairam, $idsTimesNovos));

        return array_values(array_unique([...$ficam, ...array_keys(self::elencoDosTimes(self::idsInternos($idsTimesNovos)))]));
    }

    // Depois de trocar os times: quem estava escalado num time que não está mais no jogo (ou não é
    // escalável) fica sem time. A inscrição continua. Devolve quantos saíram da escalação.
    public function limparEscalacaoForaDosTimes(): int
    {
        return EventoAtleta::where('id_evento_calendario', $this->id_evento)
            ->whereNotNull('id_time')
            ->whereNotIn('id_time', $this->timesEscalaveis()->pluck('id_time'))
            ->update(['id_time' => null]);
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

    // Título do evento do jogo, gerado pelos times (decisão 5 da Fase 6)
    public static function tituloPara(int $idTimeCasa, int $idTimeVisitante): string
    {
        $nomes = Time::whereIn('id_time', [$idTimeCasa, $idTimeVisitante])->pluck('nome_time', 'id_time');

        return ($nomes[$idTimeCasa] ?? '?') . ' x ' . ($nomes[$idTimeVisitante] ?? '?');
    }
}
