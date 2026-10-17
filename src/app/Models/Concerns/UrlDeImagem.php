<?php

namespace App\Models\Concerns;

/**
 * Endereço completo das imagens enviadas pelo admin (em public/futebol/images/{pasta}), para o JSON da API: o
 * banco guarda só o nome do arquivo e o app não tem como saber a pasta. Sem arquivo, null; endereço já completo
 * (http...), como está.
 */
trait UrlDeImagem
{
    public static function urlDaImagem(?string $arquivo, string $pasta): ?string
    {
        if (blank($arquivo)) {
            return null;
        }

        return preg_match('#^https?://#i', $arquivo) ? $arquivo : asset("futebol/images/{$pasta}/{$arquivo}");
    }
}
