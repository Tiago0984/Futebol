<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Jogo = evento do tipo JOGO + times, campeonato (vazio no amistoso) e placar (vazio = não jogado).
 * Data, horário, local e status ficam no evento (CLAUDE.md, seção 4, "Jogos").
 */
class Jogo extends Model
{
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
