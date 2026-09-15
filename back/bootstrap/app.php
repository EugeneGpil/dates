<?php

use App\Http\Middleware\SetLocale;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // There is nowhere to send a guest, so do not try to work one out.
        //
        // Laravel's default is `route('login')`, and it is built *eagerly* — inside the
        // middleware, before the `AuthenticationException` exists, for any request that did not
        // ask for JSON. This app is an API with no route named `login`, so that call throws
        // `RouteNotFoundException` and the auth boundary answers 500 — a stack trace under
        // `APP_DEBUG` — instead of 401. Returning `null` is what the handler reads as "no
        // redirect": a 401, JSON or empty, by the rule below.
        //
        // Deliberately not fixed by adding a stub `login` route — an API-only app should not
        // carry a fake web route to satisfy a redirect it must never perform.
        $middleware->redirectGuestsTo(fn () => null);

        // Every API answer — a success message, a refusal, a validator's complaint — is worded
        // in the language the caller asked for. Prepended so it runs before anything that can
        // produce a message of its own, `auth:sanctum` included: a 401 from the auth boundary is
        // as much a sentence as a 200 is.
        $middleware->api(prepend: [SetLocale::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // The auth boundary's own sentence, in the caller's language.
        //
        // `AuthenticationException` carries a hardcoded English default and never reaches the
        // translator, so without this it is the last English string a client can be handed — a
        // Russian app whose token lapsed would be told "Unauthenticated." Rendering it here is
        // the only way in: the message is fixed at construction, inside the framework.
        //
        // **The envelope is Laravel's one key, not `ApiResponse`'s three, and that is
        // deliberate** — a client parses the auth boundary differently from a controller's
        // answer, and this is a translation, not a redesign.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => __('messages.auth.unauthenticated')], 401);
            }
        });
    })->create();
