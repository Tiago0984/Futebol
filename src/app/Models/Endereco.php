<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Endereco extends Model
{
    protected $table = 'tbl_endereco';
    protected $primaryKey = 'id_endereco';
    public $timestamps = false;

    protected $fillable = [
        'rua_endereco',
        'numero_endereco',
        'bairro_endereco',
        'complemento_endereco',
        'cep_endereco',
        'cidade_endereco',
        'estado_endereco',
    ];

    // Os campos que o app manda e recebe (o mesmo bloco do GET /api/v1/responsavel)
    public const CAMPOS = [
        'cep_endereco', 'rua_endereco', 'numero_endereco', 'bairro_endereco',
        'complemento_endereco', 'cidade_endereco', 'estado_endereco',
    ];

    // Os campos como o banco grava, sempre na ordem de CAMPOS (para comparar o antigo com o novo): CEP 00000-000, UF
    // em maiúsculas, complemento vazio = null
    public static function normalizar(array $dados): array
    {
        $texto = fn (string $campo) => trim((string) ($dados[$campo] ?? ''));
        $cep = preg_replace('/\D/', '', $texto('cep_endereco'));

        return collect(self::CAMPOS)->mapWithKeys(fn ($campo) => [$campo => match ($campo) {
            'cep_endereco'         => strlen($cep) === 8 ? substr($cep, 0, 5) . '-' . substr($cep, 5) : $cep,
            'estado_endereco'      => mb_strtoupper($texto($campo)),
            'complemento_endereco' => $texto($campo) !== '' ? $texto($campo) : null,
            default                => $texto($campo),
        }])->all();
    }

    // O endereço numa linha, para o histórico: "Rua A, 10, apto 2 – Centro, São Carlos/SP, 13560-000"
    public static function texto(?array $dados): ?string
    {
        if (! $dados) {
            return null;
        }

        return trim(implode(', ', array_filter([$dados['rua_endereco'] ?? null, $dados['numero_endereco'] ?? null, $dados['complemento_endereco'] ?? null])))
            . ' – ' . ($dados['bairro_endereco'] ?? '') . ', ' . ($dados['cidade_endereco'] ?? '') . '/' . ($dados['estado_endereco'] ?? '')
            . ', ' . ($dados['cep_endereco'] ?? '');
    }

    public function atleta()
    {
        return $this->hasOne(Atleta::class, 'id_endereco', 'id_endereco');
    }

    public function responsavel()
    {
        return $this->hasOne(Responsavel::class, 'id_endereco', 'id_endereco');
    }
}
