<?php

namespace App\Models;

use App\Models\Concerns\SerializaDatasComFuso;
use Illuminate\Database\Eloquent\Model;

/**
 * Notificação para o atleta (Fase 8): título e mensagem congelados no envio, gravada na mesma
 * transação da ação (sem fila). Lida = data_leitura_notificacao preenchida.
 * SerializaDatasComFuso: datas no JSON da API (Fase 9) como 2026-12-01T09:00:00-03:00.
 */
class Notificacao extends Model
{
    use SerializaDatasComFuso;

    protected $table      = 'tbl_notificacao';
    protected $primaryKey = 'id_notificacao';
    public    $timestamps = false;

    // Valores do ENUM tipo_notificacao (sem acento) => rótulo. AGENDA = resumo de várias atividades
    // (agenda do mês, mover inscrições), sem evento (id_evento_calendario NULL).
    public const TIPOS = [
        'INSCRICAO'    => 'Inscrição',
        'REMOCAO'      => 'Remoção',
        'ALTERACAO'    => 'Alteração',
        'CANCELAMENTO' => 'Cancelamento',
        'REATIVACAO'   => 'Reativação',
        'AGENDA'       => 'Agenda',
    ];

    // data_leitura_notificacao fica de fora: só marcarComoLida() / marcarTodasComoLidas() preenchem
    protected $fillable = [
        'id_atleta',
        'id_evento_calendario',
        'tipo_notificacao',
        'titulo_notificacao',
        'mensagem_notificacao',
        'dados_notificacao',
        'id_usuario',
        'data_notificacao',
    ];

    protected $casts = [
        'dados_notificacao'        => 'array',
        'data_notificacao'         => 'datetime',
        'data_leitura_notificacao' => 'datetime',
    ];

    public function atleta()
    {
        return $this->belongsTo(Atleta::class, 'id_atleta', 'id_atleta');
    }

    // Evento da notificação; null no resumo (AGENDA)
    public function evento()
    {
        return $this->belongsTo(EventoCalendario::class, 'id_evento_calendario', 'id_evento_calendario');
    }

    // Usuário do admin que fez a ação que gerou a notificação
    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }

    public function scopeNaoLidas($query)
    {
        return $query->whereNull('data_leitura_notificacao');
    }

    public function estaLida(): bool
    {
        return $this->data_leitura_notificacao !== null;
    }

    // Marca como lida; já lida, mantém a data da primeira leitura e devolve false
    public function marcarComoLida(): bool
    {
        if ($this->estaLida()) {
            return false;
        }

        $this->data_leitura_notificacao = now();

        return $this->save();
    }

    // Marca como lidas todas as não lidas do atleta; devolve quantas mudaram
    public static function marcarTodasComoLidas(int $idAtleta): int
    {
        return self::where('id_atleta', $idAtleta)->naoLidas()->update(['data_leitura_notificacao' => now()]);
    }

    public function getTipoLabelAttribute(): string
    {
        return self::TIPOS[$this->tipo_notificacao] ?? $this->tipo_notificacao;
    }
}
