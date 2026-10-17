<?php

/*
 * Mensagens de validação em português, usadas pela API do app (o middleware IdiomaDaApi liga o pt_BR nas rotas
 * api/*). As regras que não estão aqui caem no inglês do Laravel (fallback_locale).
 */
return [
    'accepted'       => 'O campo :attribute deve ser aceito.',
    'array'          => 'O campo :attribute deve ser uma lista.',
    'boolean'        => 'O campo :attribute deve ser verdadeiro ou falso.',
    'confirmed'      => 'A confirmação de :attribute não confere.',
    'current_password' => 'A senha está incorreta.',
    'date'           => 'O campo :attribute deve ser uma data válida.',
    'date_format'    => 'O campo :attribute deve estar no formato :format.',
    'different'      => 'Os campos :attribute e :other devem ser diferentes.',
    'digits'         => 'O campo :attribute deve ter :digits dígitos.',
    'email'          => 'O campo :attribute deve ser um e-mail válido.',
    'exists'         => 'O :attribute escolhido não existe.',
    'in'             => 'O valor de :attribute não é válido.',
    'integer'        => 'O campo :attribute deve ser um número inteiro.',
    'max'            => [
        'array'   => 'O campo :attribute deve ter no máximo :max itens.',
        'file'    => 'O arquivo :attribute deve ter no máximo :max kilobytes.',
        'numeric' => 'O campo :attribute deve ser no máximo :max.',
        'string'  => 'O campo :attribute deve ter no máximo :max caracteres.',
    ],
    'min'            => [
        'array'   => 'O campo :attribute deve ter pelo menos :min itens.',
        'file'    => 'O arquivo :attribute deve ter pelo menos :min kilobytes.',
        'numeric' => 'O campo :attribute deve ser pelo menos :min.',
        'string'  => 'O campo :attribute deve ter pelo menos :min caracteres.',
    ],
    'numeric'        => 'O campo :attribute deve ser um número.',
    'present'        => 'O campo :attribute deve ser enviado.',
    'prohibited'     => 'O campo :attribute não pode ser enviado.',
    'regex'          => 'O formato de :attribute não é válido.',
    'required'       => 'O campo :attribute é obrigatório.',
    'required_if'    => 'O campo :attribute é obrigatório quando :other é :value.',
    'required_with'  => 'O campo :attribute é obrigatório quando :values é informado.',
    'same'           => 'Os campos :attribute e :other devem ser iguais.',
    'size'           => [
        'array'   => 'O campo :attribute deve ter :size itens.',
        'numeric' => 'O campo :attribute deve ser :size.',
        'string'  => 'O campo :attribute deve ter :size caracteres.',
    ],
    'string'         => 'O campo :attribute deve ser um texto.',
    'unique'         => 'Este :attribute já está em uso.',
    'url'            => 'O campo :attribute deve ser um endereço válido.',

    'password' => [
        'letters'       => 'A :attribute deve ter pelo menos uma letra.',
        'mixed'         => 'A :attribute deve ter letras maiúsculas e minúsculas.',
        'numbers'       => 'A :attribute deve ter pelo menos um número.',
        'symbols'       => 'A :attribute deve ter pelo menos um símbolo.',
        'uncompromised' => 'Esta :attribute apareceu em um vazamento de dados. Escolha outra.',
    ],

    // Nomes dos campos da API nas mensagens ("O campo e-mail é obrigatório.")
    'attributes' => [
        'email'                   => 'e-mail',
        'senha'                   => 'senha',
        'perfil'                  => 'perfil',
        'device_name'             => 'nome do aparelho',
        'senha_atual'             => 'senha atual',
        'nova_senha'              => 'nova senha',
        'email_atleta'            => 'e-mail',
        'telefone_atleta'         => 'telefone',
        'escola_atleta'           => 'escola',
        'serie_atleta'            => 'série',
        'sala_atleta'             => 'sala',
        'periodo_escolar_atleta'  => 'período escolar',
        'turno_treino_atleta'     => 'turno do treino',
        'peso_atleta'             => 'peso',
        'altura_atleta'           => 'altura',
        'descricao_atleta'        => 'descrição',
        'email_responsavel'       => 'e-mail',
        'telefone_responsavel'    => 'telefone',
        'whatsapp_responsavel'    => 'WhatsApp',
    ],
];
