<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'fullName' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'passwordHash' => static::$password ??= Hash::make('Passw0rd!2026'),
            'roleId' => Role::query()->where('roleName', Role::USER)->value('id'),
            'isActive' => true,
            'mustChangePassword' => false,
            'passwordChangedAt' => now(),
        ];
    }

    public function role(string $roleName): static
    {
        return $this->state(fn () => [
            'roleId' => Role::query()->where('roleName', $roleName)->value('id'),
        ]);
    }
}
