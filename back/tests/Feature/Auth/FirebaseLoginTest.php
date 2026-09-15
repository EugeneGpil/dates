<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreait\Firebase\Contract\Auth;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Lcobucci\JWT\Token\DataSet;
use Lcobucci\JWT\UnencryptedToken;
use Mockery;
use Tests\TestCase;

/**
 * `POST /api/auth/firebase` — the only way an account comes into being.
 *
 * Firebase is never reachable from a test, so the contract is swapped for a mock and what is
 * pinned here is everything on *this* side of it: that a uid nobody has seen becomes a user,
 * that the same uid twice does not become two, which fields a later sign-in is allowed to
 * overwrite — and which it is not.
 */
class FirebaseLoginTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $claims
     */
    private function firebaseAccepting(array $claims): void
    {
        $token = Mockery::mock(UnencryptedToken::class);
        $token->shouldReceive('claims')->andReturn(new DataSet($claims, ''));

        $auth = Mockery::mock(Auth::class);
        $auth->shouldReceive('verifyIdToken')->andReturn($token);

        $this->instance(Auth::class, $auth);
    }

    private function firebaseRefusing(): void
    {
        $auth = Mockery::mock(Auth::class);
        $auth->shouldReceive('verifyIdToken')->andThrow(new FailedToVerifyToken('nope'));

        $this->instance(Auth::class, $auth);
    }

    public function test_a_uid_we_have_never_seen_becomes_a_user(): void
    {
        $this->firebaseAccepting([
            'sub' => 'uid-new',
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.test',
            'picture' => 'https://example.test/ada.jpg',
        ]);

        $response = $this->postJson('/api/auth/firebase', ['id_token' => 'whatever']);

        $response->assertOk();
        $response->assertJsonPath('data.user.name', 'Ada Lovelace');
        $response->assertJsonPath('data.user.email', 'ada@example.test');
        $response->assertJsonPath('data.user.avatar', 'https://example.test/ada.jpg');
        $this->assertDatabaseHas('users', ['firebase_uid' => 'uid-new']);
    }

    public function test_the_token_it_returns_authenticates_the_rest_of_the_api(): void
    {
        $this->firebaseAccepting(['sub' => 'uid-new', 'name' => 'Ada', 'email' => 'ada@example.test']);

        $token = $this->postJson('/api/auth/firebase', ['id_token' => 'whatever'])
            ->json('data.token');

        $this->withToken($token)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.firebase_uid', 'uid-new');
    }

    /** Signing in is signing up, so the second sign-in must recognise rather than register. */
    public function test_signing_in_again_updates_the_same_user_rather_than_making_a_second(): void
    {
        User::factory()->create([
            'firebase_uid' => 'uid-known',
            'name' => 'Old Name',
            'email' => 'old@example.test',
        ]);

        $this->firebaseAccepting([
            'sub' => 'uid-known',
            'name' => 'New Name',
            'email' => 'new@example.test',
            'picture' => 'https://example.test/new.jpg',
        ]);

        $this->postJson('/api/auth/firebase', ['id_token' => 'whatever'])->assertOk();

        $this->assertSame(1, User::count());
        // The profile belongs to Google, so a renamed or re-photographed account arrives here.
        $this->assertDatabaseHas('users', [
            'firebase_uid' => 'uid-known',
            'name' => 'New Name',
            'email' => 'new@example.test',
        ]);
    }

    public function test_a_token_firebase_refuses_is_a_401_and_creates_nothing(): void
    {
        $this->firebaseRefusing();

        $response = $this->postJson('/api/auth/firebase', ['id_token' => 'forged']);

        $response->assertStatus(401);
        $response->assertJsonPath('message', 'Invalid token');
        $this->assertSame(0, User::count());
    }

    public function test_it_takes_the_timezone_the_device_reports(): void
    {
        $this->firebaseAccepting(['sub' => 'uid-new', 'name' => 'Ada', 'email' => 'ada@example.test']);

        $this->postJson('/api/auth/firebase', [
            'id_token' => 'whatever',
            'timezone' => 'Asia/Vladivostok',
        ])->assertOk();

        $this->assertDatabaseHas('users', ['firebase_uid' => 'uid-new', 'timezone' => 'Asia/Vladivostok']);
    }

    /**
     * A caller that says nothing about where it is still gets a zone, because every later
     * count of "days until" needs one. What it costs is the hour a reminder arrives.
     */
    public function test_a_client_that_reports_no_timezone_gets_the_configured_default(): void
    {
        config(['dates.timezone' => 'UTC']);
        $this->firebaseAccepting(['sub' => 'uid-new', 'name' => 'Ada', 'email' => 'ada@example.test']);

        $this->postJson('/api/auth/firebase', ['id_token' => 'whatever'])->assertOk();

        $this->assertDatabaseHas('users', ['firebase_uid' => 'uid-new', 'timezone' => 'UTC']);
    }

    /**
     * The case the controller is written the long way round for: signing in from a borrowed
     * laptop in another country must not move somebody's reminders to a zone they are not in.
     */
    public function test_signing_in_elsewhere_does_not_overwrite_a_timezone_already_set(): void
    {
        User::factory()->create(['firebase_uid' => 'uid-known', 'timezone' => 'Asia/Vladivostok']);

        $this->firebaseAccepting(['sub' => 'uid-known', 'name' => 'Ada', 'email' => 'ada@example.test']);

        $this->postJson('/api/auth/firebase', [
            'id_token' => 'whatever',
            'timezone' => 'Europe/Lisbon',
        ])->assertOk();

        $this->assertDatabaseHas('users', ['firebase_uid' => 'uid-known', 'timezone' => 'Asia/Vladivostok']);
    }

    public function test_it_refuses_a_timezone_this_server_cannot_resolve(): void
    {
        $this->firebaseAccepting(['sub' => 'uid-new', 'name' => 'Ada', 'email' => 'ada@example.test']);

        $this->postJson('/api/auth/firebase', [
            'id_token' => 'whatever',
            'timezone' => 'Middle/Earth',
        ])->assertStatus(422)->assertJsonValidationErrors('timezone');
    }

    public function test_it_refuses_a_language_the_app_does_not_ship(): void
    {
        $this->firebaseAccepting(['sub' => 'uid-new', 'name' => 'Ada', 'email' => 'ada@example.test']);

        $this->postJson('/api/auth/firebase', [
            'id_token' => 'whatever',
            'locale' => 'kl-GL',
        ])->assertStatus(422)->assertJsonValidationErrors('locale');
    }

    public function test_it_requires_an_id_token(): void
    {
        // Mocked even though nothing reaches Firebase: the controller takes the SDK in its
        // constructor, which the container builds before the request is validated. Without
        // this the case would fail on missing credentials rather than on the missing field.
        $this->firebaseAccepting(['sub' => 'uid-new']);

        $this->postJson('/api/auth/firebase', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_token');
    }
}
