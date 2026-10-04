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

    /**
     * Ordem de exibição (site e admin): dia da semana, depois horário de início, depois o campo "ordem".
     * Sem horário (ex.: "Jogos", que usa a observação) vai para o fim do dia.
     */
    public function scopeOrdenada($query)
    {
        return $query
            ->orderByRaw("FIELD(dia_semana_grade_treino, 'segunda_quarta', 'terca_quinta', 'sexta', 'sabado')")
            ->orderByRaw('horario_inicio_grade_treino IS NULL')
            ->orderBy('horario_inicio_grade_treino')
            ->orderBy('ordem_grade_treino');
    }

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

    // Horário sem segundos, como na lista de eventos: "08:00 às 09:30"; sem início (ex.: "Jogos"), "—"
    public function getHorarioTextoAttribute(): string
    {
        $formatar = fn ($hora) => substr((string) $hora, 0, 5);

        if (! $this->horario_inicio_grade_treino) {
            return '—';
        }

        return $this->horario_fim_grade_treino
            ? $formatar($this->horario_inicio_grade_treino) . ' às ' . $formatar($this->horario_fim_grade_treino)
            : $formatar($this->horario_inicio_grade_treino);
    }

    public function getDiaLabelAttribute(): string
    {
        return self::DIAS_SEMANA[$this->dia_semana_grade_treino] ?? $this->dia_semana_grade_treino;
    }
}
