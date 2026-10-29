<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Uma alteração de um campo do responsável: valor antigo, valor novo, quem (NULL = o próprio responsável, pelo app) e
// quando. Grava o PATCH /api/v1/responsavel (29/10/2026). Mesmo molde do AtletaHistorico
class ResponsavelHistorico extends Model
{
    protected $table      = 'tbl_responsavel_historico';
    protected $primaryKey = 'id_responsavel_historico';
    public    $timestamps = false;

    // Campos registrados e o nome de cada um na tela ("endereco" guarda o endereço inteiro num texto)
    public const CAMPOS = [
        'nome_responsavel'     => 'Nome',
        'cpf_responsavel'      => 'CPF',
        'email_responsavel'    => 'E-mail',
        'telefone_responsavel' => 'Telefone',
        'whatsapp_responsavel' => 'WhatsApp',
        'endereco'             => 'Endereço',
    ];

    protected $fillable = [
        'id_responsavel',
        'campo_responsavel_historico',
        'valor_antigo_responsavel_historico',
        'valor_novo_responsavel_historico',
        'id_usuario',
    ];

    protected $casts = [
        'data_responsavel_historico' => 'datetime',
    ];

    public function responsavel()
    {
        return $this->belongsTo(Responsavel::class, 'id_responsavel', 'id_responsavel');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }

    // Quem fez, para a tela: o usuário do admin ou "Pelo app"
    public function getAutorAttribute(): string
    {
        return $this->id_usuario ? ($this->usuario?->nome_usuario ?? '—') : 'Pelo app';
    }

    // Texto da alteração para a tela: "Nome: Carla → Carla Exemplo"
    public function getResumoAttribute(): string
    {
        return (self::CAMPOS[$this->campo_responsavel_historico] ?? $this->campo_responsavel_historico) . ': '
            . ($this->valor_antigo_responsavel_historico ?: '(vazio)')
            . ' → '
            . ($this->valor_novo_responsavel_historico ?: '(vazio)');
    }
}
