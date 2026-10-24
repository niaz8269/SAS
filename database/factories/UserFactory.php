<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => ['email_verified_at' => null]);
    }

    public function role(Role|string ...$roles): static
    {
        return $this->afterCreating(function (User $user) use ($roles) {
            foreach ($roles as $role) {
                UserRole::create(['user_id' => $user->id, 'role' => $role instanceof Role ? $role->value : $role]);
            }
            $user->unsetRelation('userRoles');
        });
    }

    public function suspended(): static
    {
        return $this->afterMaking(function (User $user) {
            $user->status = 'suspended';
            $user->suspended_at = now();
        });
    }
}
