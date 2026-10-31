<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FaleConoscoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Autenticação e perfil nas rotas da API; formulário público com CSRF no site.
    }

    public function rules(): array
    {
        return [
            'assunto' => ['bail', 'required', 'string', 'max:100'],
            'mensagem' => ['bail', 'required', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'O campo :attribute é obrigatório.',
            'string' => 'O campo :attribute deve ser um texto.',
            'max' => 'O campo :attribute não pode ter mais de :max caracteres.',
        ];
    }
}
