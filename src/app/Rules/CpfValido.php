<?php

namespace App\Rules;

use App\Support\Cpf;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * CPF válido pelos dígitos verificadores (App\Support\Cpf). Usada no cadastro do site e no admin, para o atleta e
 * para o responsável. $mensagem: o texto do erro (ex.: "Informe um CPF válido para o atleta.").
 */
class CpfValido implements ValidationRule
{
    public function __construct(private string $mensagem = 'Informe um CPF válido.')
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Cpf::valido(is_string($value) ? $value : null)) {
            $fail($this->mensagem);
        }
    }
}
