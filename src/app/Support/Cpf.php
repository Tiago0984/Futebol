<?php

namespace App\Support;

/**
 * CPF num lugar só (21/10/2026; débito "CPF único" da seção 7 do CLAUDE.md): os dígitos, o formato gravado no
 * banco (sempre 000.000.000-00, decisão do dono do projeto) e a validação de verdade, pelos dígitos
 * verificadores (sequências como 111.111.111-11 são recusadas). Usado pela regra App\Rules\CpfValido, pelos
 * mutators do atleta e do responsável e pela migration que formata os existentes.
 */
final class Cpf
{
    public static function digitos(?string $cpf): string
    {
        return preg_replace('/\D/', '', (string) $cpf);
    }

    // Com 11 dígitos, 000.000.000-00; senão, como veio (sem os espaços das pontas). null continua null
    public static function formatar(?string $cpf): ?string
    {
        if ($cpf === null) {
            return null;
        }
        $digitos = self::digitos($cpf);

        return strlen($digitos) === 11
            ? substr($digitos, 0, 3) . '.' . substr($digitos, 3, 3) . '.' . substr($digitos, 6, 3) . '-' . substr($digitos, 9, 2)
            : trim($cpf);
    }

    public static function valido(?string $cpf): bool
    {
        $cpf = self::digitos($cpf);
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        return substr($cpf, 9) === self::verificadores(substr($cpf, 0, 9));
    }

    // Os dois dígitos verificadores dos 9 primeiros (também para montar CPFs fictícios válidos, como nos testes)
    public static function verificadores(string $noveDigitos): string
    {
        $cpf = $noveDigitos;
        for ($t = 9; $t < 11; $t++) {
            $soma = 0;
            for ($i = 0; $i < $t; $i++) {
                $soma += (int) $cpf[$i] * (($t + 1) - $i);
            }
            $cpf .= ((10 * $soma) % 11) % 10;
        }

        return substr($cpf, 9);
    }
}
