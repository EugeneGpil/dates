<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What a token is worth once it has been minted: `auth/me` and `auth/logout`, plus the
 * boundary that refuses a request without one.
 *
 * The unauthenticated cases deliberately send no `Accept` header. `getJson` would pass against
 * an app whose auth boundary redirects to a `login` route it does not have — which answers 500,
 * not 401 — so the two halves of `bootstrap/app.php` that prevent that are pinned here.
 */
class SessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_answers_with_the_bearer_of_the_token(): void
    {
        $user = $this->actingAsUser(User::factory()->create(['name' => 'Ada Lovelace']));

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.name', 'Ada Lovelace');
    }

    public function test_me_without_a_token_is_a_json_401(): void
    {
        $response = $this->get('/api/auth/me');

        $response->assertStatus(401);
        $response->assertHeader('content-type', 'application/json');
        // Laravel's own one-key envelope, not `ApiResponse`'s three. Pinned exactly, because
        // the tempting follow-up is to render this through `ApiResponse::error` so the two
        // agree — and that would change what a client parses on the auth boundary.
        $response->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_logging_out_kills_the_token_that_asked(): void
    {
        $user = $this->actingAsUser();

        $this->postJson('/api/auth/logout')->assertOk();

        $this->assertSame(0, $user->tokens()->count());
        // And the token is dead the moment it is used again, not merely absent from a table.
        // The guard has to be forgotten first: one test method is one application instance,
        // and Sanctum caches the user it resolved for the request above — so without this the
        // second request would be answered from memory and pass whatever the table says.
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/auth/me')->assertStatus(401);
    }

    /** A phone signing out must not stop the tablet receiving reminders. */
    public function test_logging_out_leaves_the_accounts_other_tokens_alone(): void
    {
        $user = $this->actingAsUser();
        $user->createToken('tablet');

        $this->postJson('/api/auth/logout')->assertOk();

        $this->assertSame(['tablet'], $user->tokens()->pluck('name')->all());
    }

    public function test_logging_out_without_a_token_is_a_401(): void
    {
        $this->post('/api/auth/logout')->assertStatus(401);
    }
}
