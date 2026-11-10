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

    // Endereço da foto do usuário (10/11/2026): a enviada pelo "Meu perfil" (nome gerado, em futebol/images/usuarios),
    // a antiga do projeto (dash/assets/img/user) ou o avatar padrão: uma silhueta neutra (dono do projeto, 10/11/2026:
    // o avatar5.png era um homem, e quem entra no painel pode ser uma mulher), cinza num fundo claro, visível nos dois temas
    public const AVATAR_PADRAO = 'dash/assets/img/user/avatar-padrao.png';

    public function urlFoto(): string
    {
        $foto = $this->foto_usuario;

        if (blank($foto)) {
            return asset(self::AVATAR_PADRAO);
        }

        return \App\Services\TratadorDeImagem::ehGerado($foto)
            ? asset('futebol/images/usuarios/' . $foto)
            : asset('dash/assets/img/user/' . $foto);
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
