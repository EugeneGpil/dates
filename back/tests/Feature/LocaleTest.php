<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The API answers in the language the caller asked for.
 *
 * Two halves, and they fail in different ways. **Negotiation** is `SetLocale`: a header this
 * app has never seen — `ru-BY`, a quality-ranked list — must land on a sensible language rather
 * than silently on English, and the subtag match is what makes `ru-RU` from the front and `ru`
 * from a browser the same request. **Coverage** is the catalogues: a message handed to `__()`
 * that nobody has translated comes back as its own dotted key, which is the one failure that
 * looks like working software from the outside — a 200 with `messages.auth.logged_out` in it.
 */
class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_answers_in_the_language_the_client_asked_for(): void
    {
        $this->actingAsUser();

        $this->withHeader('Accept-Language', 'ru-RU')
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Вы вышли');
    }

    /**
     * A region we ship no separate catalogue for is still a Russian speaker asking in Russian;
     * answering them in English because of the `BY` would be the wrong lesson to draw from a
     * tag we do not recognise.
     */
    public function test_it_matches_on_the_language_subtag_not_the_whole_tag(): void
    {
        $this->actingAsUser();

        $this->withHeader('Accept-Language', 'ru-BY')
            ->postJson('/api/auth/logout')
            ->assertJsonPath('message', 'Вы вышли');
    }

    public function test_it_ignores_a_language_it_cannot_answer_in(): void
    {
        $this->actingAsUser();

        $this->withHeader('Accept-Language', 'th-TH')
            ->postJson('/api/auth/logout')
            ->assertJsonPath('message', 'Logged out');
    }

    /**
     * The auth boundary's own sentence. It never reaches the translator by itself — the message
     * is fixed inside the framework at construction — so this is what proves the render hook in
     * `bootstrap/app.php` is doing its job.
     */
    public function test_the_401_from_the_auth_boundary_is_translated_too(): void
    {
        $this->withHeader('Accept-Language', 'ru-RU')
            ->getJson('/api/auth/me')
            ->assertStatus(401)
            ->assertExactJson(['message' => 'Не авторизован.']);
    }
}
