<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Sign in as a real bearer token, not `Sanctum::actingAs`.
     *
     * The fake acting-as path hands the request a `TransientToken`, which is not a row and
     * cannot be deleted — so a test of logout or of account deletion written on it would pass
     * without ever touching the table those endpoints exist to empty. Minting the token the
     * way the sign-in exchange does keeps the whole boundary in the test.
     */
    protected function actingAsUser(?User $user = null): User
    {
        $user ??= User::factory()->create();

        $this->withToken($user->createToken('test')->plainTextToken);

        return $user;
    }
}
