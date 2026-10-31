<?php

return [
    // Contatos preenchidos apenas no .env de cada ambiente.
    'email' => env('AACJ_CONTATO_EMAIL'),
    // Sem número oficial, o site não exibe esses canais.
    'telefone' => env('AACJ_CONTATO_TELEFONE'),
    'whatsapp' => env('AACJ_CONTATO_WHATSAPP'),
];
