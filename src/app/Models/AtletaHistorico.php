<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

// Uma alteração de um campo do atleta: valor antigo, valor novo, quem (NULL = o próprio atleta, pelo app) e quando.
// Gravam: o PATCH /api/v1/atleta (nome, CPF e data de nascimento; 22/10/2026) e a foto (removida pelo app ou
// aprovada pela secretaria; 29/10/2026)
class AtletaHistorico extends Model
{
    protected $table      = 'tbl_atleta_historico';
    protected $primaryKey = 'id_atleta_historico';
    public    $timestamps = false;

    // Campos que o PATCH /api/v1/atleta compara e registra, com o nome de cada um na tela
    public const CAMPOS = [
        'nome_atleta'      => 'Nome',
        'cpf_atleta'       => 'CPF',
        'data_nasc_atleta' => 'Data de nascimento',
    ];

    // A foto: o histórico guarda os nomes dos arquivos, mas a tela diz só o que aconteceu
    public const CAMPO_FOTO = 'foto_atleta';

    protected $fillable = [
        'id_atleta',
        'campo_atleta_historico',
        'valor_antigo_atleta_historico',
        'valor_novo_atleta_historico',
        'id_usuario',
    ];

    protected $casts = [
        'data_atleta_historico' => 'datetime',
    ];

    public function atleta()
    {
        return $this->belongsTo(Atleta::class, 'id_atleta', 'id_atleta');
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

    // Texto da alteração para a tela: "Data de nascimento: 14/05/2014 → 20/05/2014"
    public function getResumoAttribute(): string
    {
        if ($this->campo_atleta_historico === self::CAMPO_FOTO) {
            return 'Foto: ' . ($this->valor_novo_atleta_historico === null ? 'removida' : 'trocada');
        }

        return (self::CAMPOS[$this->campo_atleta_historico] ?? $this->campo_atleta_historico) . ': '
            . $this->valorExibido($this->valor_antigo_atleta_historico)
            . ' → '
            . $this->valorExibido($this->valor_novo_atleta_historico);
    }

    private function valorExibido(?string $valor): string
    {
        if ($valor === null || $valor === '') {
            return '(vazio)';
        }

        return $this->campo_atleta_historico === 'data_nasc_atleta' ? Carbon::parse($valor)->format('d/m/Y') : $valor;
    }
}
