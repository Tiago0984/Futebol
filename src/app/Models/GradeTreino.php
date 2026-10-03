<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradeTreino extends Model
{
    protected $table      = 'tbl_grade_treino';
    protected $primaryKey = 'id_grade_treino';
    public    $timestamps = false;

    public const TIPOS = ['TREINO', 'JOGO', 'LIVRE'];

    protected $fillable = [
        'dia_semana_grade_treino',
        'id_categoria',
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

    // Categoria da linha da grade; null nos itens gerais (Integrado, Treino Livre, Jogos)
    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'id_categoria', 'id_categoria');
    }

    // Texto exibido: "Sub-13 Masculino" quando há categoria; senão, o rótulo livre do item geral
    public function getRotuloAttribute(): string
    {
        return $this->categoria?->rotulo ?? $this->categoria_grade_treino;
    }

    // Cor do item no site: pelo nome da categoria (cat-sub13), igual para M e F
    public function getCatClassAttribute(): string
    {
        $nome = $this->categoria?->nome_categoria ?? $this->categoria_grade_treino;

        return match($this->tipo_grade_treino) {
            'JOGO'  => 'cat-jogo',
            'LIVRE' => 'cat-livre',
            default => 'cat-' . strtolower(str_replace(['-', ' '], '', $nome)),
        };
    }

    public function getDiaLabelAttribute(): string
    {
        return self::DIAS_SEMANA[$this->dia_semana_grade_treino] ?? $this->dia_semana_grade_treino;
    }
}
