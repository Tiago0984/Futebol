<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Estado padrão de um usuário do admin (tbl_usuarios).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome_usuario'           => fake()->name(),
            'email_usuario'          => fake()->unique()->safeEmail(),
            'senha_usuario'          => static::$password ??= Hash::make('password'),
            'remember_token_usuario' => Str::random(10),
        ];
    }
}
