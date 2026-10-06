<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Inscrição de um atleta num evento (sem status: remover apaga a linha)
class EventoAtleta extends Model
{
    protected $table      = 'tbl_evento_atleta';
    protected $primaryKey = 'id_evento_atleta';
    public    $timestamps = false;

    // Valores de origem_evento_atleta => rótulo. CATEGORIA e ELENCO são automáticas (acompanham a categoria
    // do evento ou os times do jogo); INDIVIDUAL é escolha do admin e nenhuma sincronização mexe nela.
    public const ORIGENS = [
        'CATEGORIA'  => 'Pela categoria',
        'INDIVIDUAL' => 'Individual',
        'ELENCO'     => 'Pelo elenco',
    ];

    protected $fillable = [
        'id_evento_calendario',
        'id_atleta',
        'id_time',
        'origem_evento_atleta',
        'id_usuario',
        'data_evento_atleta',
    ];

    protected $casts = [
        'data_evento_atleta' => 'datetime',
    ];

    public function evento()
    {
        return $this->belongsTo(EventoCalendario::class, 'id_evento_calendario', 'id_evento_calendario');
    }

    public function atleta()
    {
        return $this->belongsTo(Atleta::class, 'id_atleta', 'id_atleta');
    }

    // Quem inscreveu
    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }

    public function getOrigemLabelAttribute(): string
    {
        return self::ORIGENS[$this->origem_evento_atleta] ?? $this->origem_evento_atleta;
    }
}
