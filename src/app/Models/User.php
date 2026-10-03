<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table      = 'tbl_usuarios';
    protected $primaryKey = 'id_usuario';

    const CREATED_AT = 'criado_em_usuarios';
    const UPDATED_AT = 'atualizado_em_usuarios';

    // Valores provisórios até o professor responder (CLAUDE.md, seção 8)

    // Cargo exibido (coluna VARCHAR, validar com Rule::in(array_keys(User::CARGOS))) => rótulo
    public const CARGOS = [
        'PROFESSOR'     => 'Professor',
        'NUTRICIONISTA' => 'Nutricionista',
        'FISIOLOGISTA'  => 'Fisiologista',
        'MEDICO'        => 'Médico',
        'COORDENADOR'   => 'Coordenador',
    ];

    // Valores do ENUM nivel_usuario (permissão no sistema) => rótulo
    public const NIVEIS = [
        'ADMIN'   => 'Administrador',
        'EDITOR'  => 'Editor',
        'LEITURA' => 'Somente leitura',
    ];

    // nivel_usuario fica fora de propósito: um formulário de perfil com
    // $request->all() não pode deixar o usuário se promover a ADMIN
    protected $fillable = [
        'nome_usuario',
        'email_usuario',
        'senha_usuario',
        'foto_usuario',
        'cargo_usuario',
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

    // Informa ao Laravel o nome da coluna de senha (evita erro no rehash automático)
    public function getAuthPasswordName(): string
    {
        return 'senha_usuario';
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

    public function getCargoLabelAttribute(): ?string
    {
        if ($this->cargo_usuario === null) {
            return null;
        }

        return self::CARGOS[$this->cargo_usuario] ?? $this->cargo_usuario;
    }

    public function getNivelLabelAttribute(): string
    {
        return self::NIVEIS[$this->nivel_usuario] ?? $this->nivel_usuario;
    }
}
