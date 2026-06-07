<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $table      = 'tbl_usuarios';
    protected $primaryKey = 'id';

    const CREATED_AT = 'criado_em_usuarios';
    const UPDATED_AT = 'atualizado_em_usuarios';

    protected $fillable = [
        'nome_usuario',
        'email_usuario',
        'senha_usuario',
    ];

    protected $hidden = [
        'senha_usuario',
        'remember_token_usuario',
    ];

    protected function casts(): array
    {
        return [
            'senha_usuario' => 'hashed',
        ];
    }

    // Campo usado como senha
    public function getAuthPassword()
    {
        return $this->senha_usuario;
    }

    // Campo remember token customizado
    public function getRememberTokenName()
    {
        return 'remember_token_usuario';
    }
}
