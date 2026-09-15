<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answer in the language the client asked for.
 *
 * `Accept-Language` rather than a query parameter, and that is not a departure from this
 * project's "everything in the query string" rule: that rule is about naming *resources* —
 * which date, whose account — and the language a response is worded in is not one. It is
 * metadata about the representation, which is the header's job, and putting it in the query
 * string would make every URL in the app vary by language for no gain — including the ones a
 * client caches.
 *
 * The front sends its active locale (`front/src/api.js`); a browser or a third-party client
 * that sends nothing but its own preferences is served by the same negotiation.
 */
class SetLocale
{
    /** RFC 9110's quality value: absent means 1, and `q=0` means "explicitly not this one". */
    private const DEFAULT_QUALITY = 1.0;

    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('locales.supported', []);
        $wanted = $this->negotiate($request->header('Accept-Language'), $supported);

        if ($wanted !== null) {
            app()->setLocale($wanted);
        }

        return $next($request);
    }

    /**
     * The best supported language in an `Accept-Language` header, or null for none.
     *
     * Matched on the language subtag, exactly as the front matches `navigator.languages`: a
     * client asking for `ru-BY` or `de-AT` is asking for a language this API has, and refusing
     * it over a region we do not ship a separate catalogue for would answer a Russian request
     * in English.
     *
     * `*` is honoured as "anything you have" and, being weakest by convention, is only reached
     * when nothing named matched — so it lands on the fallback rather than on whichever
     * catalogue happens to be first.
     *
     * @param  array<int, string>  $supported
     */
    private function negotiate(?string $header, array $supported): ?string
    {
        if ($header === null || $supported === []) {
            return null;
        }

        $ranked = [];

        foreach (explode(',', $header) as $part) {
            $bits = explode(';', trim($part));
            $tag = strtolower(trim($bits[0]));

            if ($tag === '') {
                continue;
            }

            $quality = self::DEFAULT_QUALITY;

            foreach (array_slice($bits, 1) as $parameter) {
                if (preg_match('/^\s*q\s*=\s*([0-9.]+)\s*$/i', $parameter, $found) === 1) {
                    $quality = (float) $found[1];
                }
            }

            // `q=0` is a refusal, not a weak preference, so it must never be selectable.
            if ($quality <= 0) {
                continue;
            }

            $subtag = $tag === '*' ? '*' : explode('-', $tag)[0];

            // A header may name `ru-RU` and `ru`; keep the stronger claim on the language.
            $ranked[$subtag] = max($ranked[$subtag] ?? 0.0, $quality);
        }

        // Ties keep the order the client wrote them in, which `arsort` preserves in PHP 8.
        arsort($ranked);

        foreach (array_keys($ranked) as $subtag) {
            if ($subtag === '*') {
                return config('app.fallback_locale');
            }

            if (in_array($subtag, $supported, true)) {
                return $subtag;
            }
        }

        return null;
    }
}
