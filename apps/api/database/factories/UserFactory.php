<?php

namespace Database\Factories;

use App\Domains\Authorization\Enums\RoleKey;
use App\Domains\Authorization\Models\Role;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    public function configure(): static
    {
        return $this->afterCreating(
            fn (User $user) => $this->syncRoles($user, [RoleKey::Student]),
        );
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
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

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the account is suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'suspended',
            'suspended_at' => now(),
        ]);
    }

    public function withRole(RoleKey $role): static
    {
        return $this->withRoles($role);
    }

    public function withRoles(RoleKey ...$roles): static
    {
        return $this->afterCreating(
            fn (User $user) => $this->syncRoles($user, $roles),
        );
    }

    /**
     * @param  list<RoleKey>  $roles
     */
    private function syncRoles(User $user, array $roles): void
    {
        $roleIds = Role::query()
            ->whereIn('key', array_map(static fn (RoleKey $role): string => $role->value, $roles))
            ->pluck('id')
            ->all();

        $uniqueRoleKeys = array_unique(array_map(
            static fn (RoleKey $role): string => $role->value,
            $roles,
        ));

        if (count($roleIds) !== count($uniqueRoleKeys)) {
            throw new \LogicException('One or more requested factory roles are unavailable.');
        }

        $user->roles()->sync(array_fill_keys($roleIds, ['assigned_at' => now()]));
        $user->unsetRelation('roles');
    }
}
