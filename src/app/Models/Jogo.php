<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Jogo = evento do tipo JOGO + times, campeonato (vazio no amistoso) e placar (vazio = não jogado).
 * Data, horário, local e status ficam no evento (CLAUDE.md, seção 4, "Jogos").
 */
class Jogo extends Model
{
    protected $table = 'tbl_jogos';
    protected $primaryKey = 'id_jogo';
    public $timestamps = false;

    // data_jogo e status_jogo: provisórios até a Etapa 2 da Fase 6 (o site ainda lê)
    protected $fillable = [
        'id_evento',
        'id_campeonato',
        'id_time_casa',
        'id_time_visitante',
        'placar_time_casa_jogos',
        'placar_time_visitante_jogos',
        'data_jogo',
        'status_jogo',
    ];

    protected $casts = [
        'data_jogo' => 'datetime',
    ];

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

    public function ehAmistoso(): bool
    {
        return $this->id_campeonato === null;
    }

    // Título do evento do jogo, gerado pelos times (decisão 5 da Fase 6)
    public static function tituloPara(int $idTimeCasa, int $idTimeVisitante): string
    {
        $nomes = Time::whereIn('id_time', [$idTimeCasa, $idTimeVisitante])->pluck('nome_time', 'id_time');

        return ($nomes[$idTimeCasa] ?? '?') . ' x ' . ($nomes[$idTimeVisitante] ?? '?');
    }
}
