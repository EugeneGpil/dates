<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class FirebaseLoginRequest extends FormRequest
{
    /**
     * The one endpoint behind no token: this is where a caller becomes a user, so there is
     * nobody to authorize yet. Whether the id token is genuine is Firebase's answer, not a
     * validation rule — the controller asks it and returns 401 if it is not.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `timezone` and `locale` are what the device knows about its owner and the server cannot
     * work out for itself. Both optional: a client that sends neither gets the configured
     * default and a null language, and neither is worth refusing a sign-in over.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'id_token' => ['required', 'string'],
            // The IANA identifier, validated against PHP's own list rather than a regex — a
            // zone this server cannot resolve is one the notification job could not count in.
            'timezone' => ['sometimes', 'nullable', 'timezone'],
            // A full tag, matched exactly against what the switcher offers: this column is a
            // preference, not a free-text field.
            'locale' => ['sometimes', 'nullable', 'string', 'in:'.implode(',', config('locales.selectable'))],
        ];
    }
}
