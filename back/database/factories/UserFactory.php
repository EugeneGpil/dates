<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * A user as `auth/firebase` creates one: an identity from Google and nothing else. The
     * timezone is left null on purpose — the default belongs to the sign-in exchange, and a
     * factory that filled it in would hide a user who has never told us where they are.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'firebase_uid' => 'uid-'.Str::random(24),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'avatar' => null,
            'locale' => null,
            'timezone' => null,
        ];
    }
}
