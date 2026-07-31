<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradeTreino extends Model
{
    protected $table      = 'tbl_grade_treino';
    protected $primaryKey = 'id_grade_treino';
    public    $timestamps = false;

    protected $fillable = [
        'dia_semana_grade_treino',
        'categoria_grade_treino',
        'tipo_grade_treino',
        'horario_inicio_grade_treino',
        'horario_fim_grade_treino',
        'horario_obs_grade_treino',
        'local_grade_treino',
        'ordem_grade_treino',
        'status_grade_treino',
    ];

    public const DIAS_SEMANA = [
        'segunda_quarta' => 'Segunda/Quarta',
        'terca_quinta'   => 'Terça/Quinta',
        'sexta'          => 'Sexta',
        'sabado'         => 'Sábado',
    ];

    public function getCatClassAttribute(): string
    {
        return match($this->tipo_grade_treino) {
            'JOGO'  => 'cat-jogo',
            'LIVRE' => 'cat-livre',
            default => 'cat-' . strtolower(str_replace(['-', ' '], '', $this->categoria_grade_treino)),
        };
    }

    public function getDiaLabelAttribute(): string
    {
        return self::DIAS_SEMANA[$this->dia_semana_grade_treino] ?? $this->dia_semana_grade_treino;
    }
}
