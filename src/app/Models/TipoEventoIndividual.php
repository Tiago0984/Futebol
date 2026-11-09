<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tipo de evento individual cadastrado pelo painel (09/11/2026), como "Fisioterapia" ou "Entrevista com a família".
 * O evento continua com um dos três tipos do ENUM (a base: Avaliação, Reunião ou Evento), que decide o app, o
 * conflito de horário e o ramo do menu; o nome do tipo vai para o subtipo do evento (o app já mostra).
 * Prefixo VALOR_NO_SELECT: o select "Tipo" do evento individual manda "tipo:ID" quando o escolhido é um destes.
 */
class TipoEventoIndividual extends Model
{
    protected $table = 'tbl_tipo_evento_individual';
    protected $primaryKey = 'id_tipo_evento_individual';
    public $timestamps = false;

    protected $fillable = [
        'nome_tipo_evento_individual',
        'base_tipo_evento_individual',
    ];

    public const NOME_MAX = 60;

    // Base de todo tipo novo: EVENTO, o tipo genérico (o tipo pode ser qualquer coisa, como "Viagem"; o
    // "Funciona como" saiu do formulário, 09/11/2026). Para o app e o conflito de horário (2 h), é um evento
    public const BASE = 'EVENTO';

    // Valor do select "Tipo" para um tipo cadastrado: "tipo:12"
    public const PREFIXO_NO_SELECT = 'tipo:';

    // Ativos para o select, pelo nome
    public static function paraEscolher()
    {
        return static::where('status_tipo_evento_individual', 'ATIVO')->orderBy('nome_tipo_evento_individual')->get();
    }

    // "tipo:12" => o tipo ATIVO 12; qualquer outro valor (inclusive um dos três de sempre) => null
    public static function doValorDoSelect(?string $valor): ?self
    {
        if (! is_string($valor) || ! str_starts_with($valor, self::PREFIXO_NO_SELECT)) {
            return null;
        }

        $id = substr($valor, strlen(self::PREFIXO_NO_SELECT));

        return ctype_digit($id)
            ? static::where('status_tipo_evento_individual', 'ATIVO')->find((int) $id)
            : null;
    }

    public function valorNoSelect(): string
    {
        return self::PREFIXO_NO_SELECT . $this->id_tipo_evento_individual;
    }

    public function getRotuloAttribute(): string
    {
        return $this->nome_tipo_evento_individual;
    }
}
