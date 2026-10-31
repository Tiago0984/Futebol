<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class ContatoSiteRequest extends FaleConoscoRequest
{
    public const ASSUNTOS = [
        'matricula' => 'Matrículas e Categorias',
        'horarios' => 'Horários e Unidades',
        'financeiro' => 'Financeiro / Mensalidades',
        'sugestao' => 'Sugestões ou Elogios',
    ];

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'nome' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'telefone' => ['bail', 'required', 'string', 'max:20', 'regex:/^\+?[\d\s().-]+$/', function ($attribute, $value, $fail) {
                $quantidade = strlen(preg_replace('/\D/', '', $value));
                if ($quantidade < 10 || $quantidade > 13) {
                    $fail('Informe um telefone válido com DDD.');
                }
            }],
            'assunto' => ['required', 'string', 'max:100', Rule::in(array_keys(self::ASSUNTOS))],
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'email.email' => 'Informe um e-mail válido.',
            'telefone.regex' => 'Informe um telefone válido com DDD.',
            'assunto.in' => 'Escolha um assunto da lista.',
        ]);
    }

    public function attributes(): array
    {
        return ['email' => 'e-mail'];
    }
}
