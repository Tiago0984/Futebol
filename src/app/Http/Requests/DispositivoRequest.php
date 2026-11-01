<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DispositivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $regras = ['token' => ['bail', 'required', 'string', 'max:255', 'regex:/\A(?:ExponentPushToken|ExpoPushToken)\[[^\s\[\]]+\]\z/']];
        if (! $this->isMethod('DELETE')) {
            $regras += [
                'plataforma' => ['bail', 'required', 'string', 'in:android,ios'],
                'notificacoes_ativas' => ['required', 'boolean'],
            ];
        }

        return $regras;
    }

    public function messages(): array
    {
        return [
            'required' => 'O campo :attribute é obrigatório.',
            'string' => 'O campo :attribute deve ser um texto.',
            'token.max' => 'O token não pode ter mais de 255 caracteres.',
            'token.regex' => 'Informe um token de notificações Expo válido.',
            'plataforma.in' => 'A plataforma deve ser android ou ios.',
            'notificacoes_ativas.boolean' => 'O campo notificacoes_ativas deve ser verdadeiro ou falso.',
        ];
    }
}
