<?php

namespace Tests\Feature\Account;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreait\Firebase\Contract\Auth;
use Kreait\Firebase\Exception\Auth\UserNotFound;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * `DELETE /api/account` — the in-app deletion path Play's User Data policy requires, and the
 * one the privacy policy will promise.
 *
 * What is pinned here is mostly "nothing survives". The Sanctum tokens are the case worth
 * naming: they hang off a polymorphic relation with no foreign key, so no cascade reaches them
 * and only the explicit delete in the controller does. The dates and device tokens of later
 * phases cascade from `users`, and each brings its own assertion here when it lands.
 *
 * The other half is the failure ordering: Firebase going down must not stop the data being
 * deleted, because the alternative — an account nobody can sign into with its dates still on
 * disk — is worse than an orphaned auth record.
 */
class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function firebaseExpecting(string $uid): void
    {
        $auth = Mockery::mock(Auth::class);
        $auth->shouldReceive('deleteUser')->once()->with($uid);

        $this->instance(Auth::class, $auth);
    }

    private function firebaseThrowing(\Throwable $e): void
    {
        $auth = Mockery::mock(Auth::class);
        $auth->shouldReceive('deleteUser')->once()->andThrow($e);

        $this->instance(Auth::class, $auth);
    }

    public function test_it_removes_the_user_and_every_token_they_hold(): void
    {
        $user = $this->actingAsUser(User::factory()->create(['firebase_uid' => 'uid-delete-me']));
        $user->createToken('tablet');
        $this->firebaseExpecting('uid-delete-me');

        $this->deleteJson('/api/account')->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /** Already gone from the Firebase console, or a retry of a request that got this far. */
    public function test_a_firebase_user_that_is_already_gone_is_not_a_failure(): void
    {
        $user = $this->actingAsUser(User::factory()->create(['firebase_uid' => 'uid-delete-me']));
        $this->firebaseThrowing(new UserNotFound('gone'));

        $this->deleteJson('/api/account')->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    /**
     * The ordering the controller is written for: an identity record nobody can authenticate
     * as is a mess to sweep up by hand, and data nobody can reach or delete is worse.
     */
    public function test_firebase_being_down_does_not_keep_the_data_alive(): void
    {
        $user = $this->actingAsUser(User::factory()->create(['firebase_uid' => 'uid-delete-me']));
        $this->firebaseThrowing(new RuntimeException('Firebase is down'));

        $this->deleteJson('/api/account')->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_it_requires_a_token(): void
    {
        $this->delete('/api/account')->assertStatus(401);
    }
}
