<?php

namespace App\Services;

use App\Models\Atleta;
use App\Models\EventoAtleta;
use App\Models\EventoCalendario;
use App\Models\Jogo;
use App\Models\Notificacao;
use App\Models\NotificacaoLeitura;
use App\Models\Responsavel;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * O que o app mostra de um atleta (Fase 9): dados, agenda e avisos. Uma classe só para os dois perfis:
 * o atleta vê os dele; o responsável vê os do filho, em modo leitura, com a própria marca de leitura
 * dos avisos (tbl_notificacao_leitura). Quem chama já conferiu o acesso (atleta ATIVO e, no responsável,
 * filho dele: ver AppDoAtletaController).
 */
class AppDoAtleta
{
    public const POR_PAGINA = 20;
    public const PASSADOS = 3;

    // Formato das datas do JSON (o mesmo do trait SerializaDatasComFuso)
    private const FORMATO_DATA = 'Y-m-d\TH:i:sP';

    /**
     * @param ?Responsavel $responsavel quem lê, quando não é o próprio atleta
     */
    public function __construct(
        private Atleta $atleta,
        private ?Responsavel $responsavel = null,
    ) {
    }

    // ── Dados ───────────────────────────────────────────────────────────────

    // GET /atleta (e o mesmo para o responsável): só a categoria atual; as chaves do JSON não mudam
    public function dados(): Atleta
    {
        return $this->atleta->load([
            'endereco',
            'categorias' => fn ($q) => $q->select('tbl_categoria.id_categoria', 'nome_categoria')
                ->wherePivot('status_categoria_atleta', Atleta::CATEGORIA_ATIVA),
            'times:tbl_time.id_time,nome_time,logo_time',
        ]);
    }

    // ── Agenda ──────────────────────────────────────────────────────────────

    /**
     * Só eventos em que o atleta está inscrito, ATIVO ou CANCELADO (oculto fica de fora). "proximos": os
     * que ainda não terminaram, paginados, do mais perto ao mais longe (cancelado aparece com a situação
     * CANCELADO). "passados": os 3 últimos concluídos e não cancelados. Nunca "Alterado": a alteração chega
     * pelos avisos.
     */
    public function agenda(int $pagina = 1): array
    {
        $proximos = $this->eventosInscritos()
            ->naoConcluidos()
            ->orderBy('data_evento_calendario')
            ->orderByRaw('horario_inicio_evento_calendario IS NULL')   // sem horário: no fim do dia
            ->orderBy('horario_inicio_evento_calendario')
            ->orderBy('id_evento_calendario')
            ->paginate(self::POR_PAGINA, ['*'], 'page', $pagina);

        $passados = $this->eventosInscritos()
            ->concluidos()
            ->where('status_evento_calendario', 'ATIVO')
            ->orderByDesc('data_evento_calendario')
            ->orderByRaw('horario_inicio_evento_calendario IS NULL DESC')
            ->orderByDesc('horario_inicio_evento_calendario')
            ->orderByDesc('id_evento_calendario')
            ->limit(self::PASSADOS)
            ->get();

        // Time do atleta em cada jogo (escalação), numa consulta só
        $times = EventoAtleta::where('id_atleta', $this->atleta->id_atleta)
            ->whereIn('id_evento_calendario', $proximos->getCollection()->concat($passados)->pluck('id_evento_calendario'))
            ->pluck('id_time', 'id_evento_calendario');

        return [
            'proximos'  => $proximos->getCollection()->map(fn ($evento) => $this->evento($evento, $times[$evento->id_evento_calendario] ?? null))->all(),
            'paginacao' => self::paginacao($proximos),
            'passados'  => $passados->map(fn ($evento) => $this->evento($evento, $times[$evento->id_evento_calendario] ?? null))->values()->all(),
        ];
    }

    private function eventosInscritos()
    {
        return EventoCalendario::query()
            ->with(['categoria', 'jogo.campeonato', 'jogo.timeCasa', 'jogo.timeVisitante'])
            ->publicados() // rascunho (jogo sendo montado) fica fora até publicar
            ->whereIn('status_evento_calendario', ['ATIVO', 'CANCELADO'])
            ->whereHas('inscricoes', fn ($q) => $q->where('id_atleta', $this->atleta->id_atleta));
    }

    private function evento(EventoCalendario $evento, ?int $idTimeDoAtleta): array
    {
        $dia = $evento->data_evento_calendario->format('Y-m-d');
        $momento = fn (?string $hora) => $hora ? Carbon::parse("{$dia} {$hora}")->format(self::FORMATO_DATA) : null;
        $hora = fn (?string $hora) => $hora ? substr($hora, 0, 5) : null;

        return [
            'id_evento'      => $evento->id_evento_calendario,
            'titulo'         => $evento->titulo_evento_calendario,
            'tipo'           => $evento->tipo_evento_calendario,
            'tipo_label'     => $evento->tipo_label,
            'subtipo'        => $evento->subtipo_evento_calendario,
            'descricao'      => $evento->descricao_evento_calendario,
            'categoria'      => $evento->categoria?->rotulo,
            'data'           => Carbon::parse($dia)->format(self::FORMATO_DATA),
            'horario_inicio' => $hora($evento->horario_inicio_evento_calendario),
            'horario_fim'    => $hora($evento->horario_fim_evento_calendario),
            'inicio'         => $momento($evento->horario_inicio_evento_calendario),
            'fim'            => $momento($evento->horario_fim_evento_calendario),
            'local'          => $evento->local_evento_calendario,
            'situacao'       => $evento->estaCancelado() ? 'CANCELADO' : 'CONFIRMADO',
            'jogo'           => $evento->jogo ? $this->jogo($evento->jogo, $idTimeDoAtleta) : null,
        ];
    }

    // Bloco do jogo: campeonato (null no amistoso), times, placar (null = ainda não jogado) e o time em
    // que o atleta está escalado (null = inscrito sem time)
    private function jogo(Jogo $jogo, ?int $idTimeDoAtleta): array
    {
        $time = fn ($t) => $t ? ['id_time' => $t->id_time, 'nome_time' => $t->nome_time, 'logo_time' => $t->logo_time] : null;
        $doAtleta = match ($idTimeDoAtleta) {
            null                     => null,
            $jogo->id_time_casa      => $jogo->timeCasa,
            $jogo->id_time_visitante => $jogo->timeVisitante,
            default                  => null,
        };

        return [
            'id_jogo'        => $jogo->id_jogo,
            'amistoso'       => $jogo->ehAmistoso(),
            'campeonato'     => $jogo->campeonato
                ? ['id_campeonato' => $jogo->campeonato->id_campeonato, 'nome_campeonato' => $jogo->campeonato->nome_campeonato]
                : null,
            'time_casa'      => $time($jogo->timeCasa),
            'time_visitante' => $time($jogo->timeVisitante),
            'placar'         => $jogo->temPlacar()
                ? ['casa' => (int) $jogo->placar_time_casa_jogos, 'visitante' => (int) $jogo->placar_time_visitante_jogos]
                : null,
            'time_do_atleta' => $time($doAtleta),
        ];
    }

    // ── Avisos (notificações) ───────────────────────────────────────────────

    // Avisos do atleta, do mais novo para o mais antigo (no empate da data, o id: relógio do WSL2)
    public function avisos(int $pagina = 1): array
    {
        $avisos = $this->doAtleta()
            ->with('leiturasDosResponsaveis')
            ->orderByDesc('data_notificacao')
            ->orderByDesc('id_notificacao')
            ->paginate(self::POR_PAGINA, ['*'], 'page', $pagina);

        return [
            'notificacoes' => $avisos->getCollection()->map(fn ($aviso) => $this->aviso($aviso))->all(),
            'paginacao'    => self::paginacao($avisos),
        ];
    }

    // Não lidos por quem lê: o atleta pela própria marca; o responsável pela dele
    public function naoLidos(): int
    {
        return $this->naoLidosQuery()->count();
    }

    /**
     * Marca um aviso como lido por quem lê. Aviso de outro atleta: null (o controller responde 404).
     * Já lido: mantém a data da primeira leitura.
     */
    public function marcarLido(int $idNotificacao): ?array
    {
        $aviso = $this->doAtleta()->find($idNotificacao);

        if (! $aviso) {
            return null;
        }

        if ($this->responsavel) {
            NotificacaoLeitura::insertOrIgnore([
                'id_notificacao' => $aviso->id_notificacao, 'id_responsavel' => $this->responsavel->id_responsavel,
                'data_notificacao_leitura' => now(),
            ]);
        } else {
            $aviso->marcarComoLida();
        }

        return $this->aviso($aviso->load('leiturasDosResponsaveis'));
    }

    // Marca todos os avisos não lidos por quem lê; devolve quantos mudaram
    public function marcarTodosLidos(): int
    {
        if (! $this->responsavel) {
            return Notificacao::marcarTodasComoLidas($this->atleta->id_atleta);
        }

        $ids = $this->naoLidosQuery()->pluck('id_notificacao');

        return NotificacaoLeitura::insertOrIgnore($ids->map(fn ($id) => [
            'id_notificacao' => $id, 'id_responsavel' => $this->responsavel->id_responsavel,
            'data_notificacao_leitura' => now(),
        ])->all());
    }

    private function doAtleta()
    {
        return Notificacao::where('id_atleta', $this->atleta->id_atleta);
    }

    private function naoLidosQuery()
    {
        return $this->responsavel
            ? $this->doAtleta()->whereDoesntHave('leiturasDosResponsaveis',
                fn ($q) => $q->where('id_responsavel', $this->responsavel->id_responsavel))
            : $this->doAtleta()->naoLidas();
    }

    private function aviso(Notificacao $aviso): array
    {
        $leitura = $this->responsavel
            ? $aviso->leiturasDosResponsaveis->firstWhere('id_responsavel', $this->responsavel->id_responsavel)?->data_notificacao_leitura
            : $aviso->data_leitura_notificacao;

        return [
            'id_notificacao' => $aviso->id_notificacao,
            'tipo'           => $aviso->tipo_notificacao,
            'tipo_label'     => $aviso->tipo_label,
            'titulo'         => $aviso->titulo_notificacao,
            'mensagem'       => $aviso->mensagem_notificacao,
            'id_evento'      => $aviso->id_evento_calendario,
            'data'           => $aviso->data_notificacao?->copy()->setTimezone(config('app.timezone'))->format(self::FORMATO_DATA),
            'lida'           => $leitura !== null,
            'data_leitura'   => $leitura?->copy()->setTimezone(config('app.timezone'))->format(self::FORMATO_DATA),
        ];
    }

    // ── Comum ───────────────────────────────────────────────────────────────

    private static function paginacao(LengthAwarePaginator $pagina): array
    {
        return [
            'pagina_atual'  => $pagina->currentPage(),
            'ultima_pagina' => $pagina->lastPage(),
            'por_pagina'    => $pagina->perPage(),
            'total'         => $pagina->total(),
        ];
    }
}
