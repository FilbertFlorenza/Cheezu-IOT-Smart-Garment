<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => Role::Operator,
            'is_active' => true,
        ];
    }

    public function administrator(): static
    {
        return $this->state(fn () => ['role' => Role::Administrator]);
    }

    public function operator(): static
    {
        return $this->state(fn () => ['role' => Role::Operator]);
    }

    public function service(): static
    {
        return $this->state(fn () => [
            'role' => Role::Service,
            'name' => 'Service Account',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
