<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

// Uma alteração de um campo de um evento: valor antigo, valor novo, quem e quando
class EventoHistorico extends Model
{
    protected $table      = 'tbl_evento_historico';
    protected $primaryKey = 'id_evento_historico';
    public    $timestamps = false;

    protected $fillable = [
        'id_evento_calendario',
        'campo_evento_historico',
        'valor_antigo_evento_historico',
        'valor_novo_evento_historico',
        'id_usuario',
        'data_evento_historico',
    ];

    protected $casts = [
        'data_evento_historico' => 'datetime',
    ];

    public function evento()
    {
        return $this->belongsTo(EventoCalendario::class, 'id_evento_calendario', 'id_evento_calendario');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }

    // Nome do campo para a tela ("Local", "Horário de início"...)
    public function getCampoLabelAttribute(): string
    {
        return EventoCalendario::CAMPOS_HISTORICO[$this->campo_evento_historico] ?? $this->campo_evento_historico;
    }

    // Texto da alteração para a tela: "Local: Campo A → Campo B"
    public function getResumoAttribute(): string
    {
        return $this->campo_label . ': '
            . $this->valorExibido($this->valor_antigo_evento_historico)
            . ' → '
            . $this->valorExibido($this->valor_novo_evento_historico);
    }

    // Valor gravado em formato legível (data dd/mm/aaaa, rótulos de status/tipo, nome da categoria)
    private function valorExibido(?string $valor): string
    {
        if ($valor === null) {
            return '(vazio)';
        }

        return match ($this->campo_evento_historico) {
            'data_evento_calendario'   => Carbon::parse($valor)->format('d/m/Y'),
            'status_evento_calendario' => EventoCalendario::STATUS[$valor] ?? $valor,
            'tipo_evento_calendario'   => EventoCalendario::TIPOS[$valor] ?? $valor,
            'id_categoria'             => Categoria::find($valor)?->rotulo ?? "#{$valor}",
            default                    => $valor,
        };
    }
}
