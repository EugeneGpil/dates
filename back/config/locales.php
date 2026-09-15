<?php

return [

    /*
    |---------------------------------------------------------------------------
    | The languages this API answers in
    |---------------------------------------------------------------------------
    |
    | Language subtags, not full tags: the client sends `ru-RU`, and there is one Russian
    | catalogue for every region that asks for it. Matched loosely by
    | `App\Http\Middleware\SetLocale`, so `ru-BY` lands here too.
    |
    | Kept in step with `SUPPORTED_LOCALES` in `front/src/i18n/index.js` — the front decides
    | what a user can pick, this decides what the server can answer in, and a language in one
    | and not the other is a screen with two languages on it.
    |
    */

    'supported' => ['en', 'ru'],

    /*
    |---------------------------------------------------------------------------
    | The languages a user may choose and have remembered
    |---------------------------------------------------------------------------
    |
    | Full tags this time, because these are the client's own values: what the sign-in exchange
    | accepts and what `users.locale` stores, so a choice made on a phone reaches a laptop
    | spelled exactly as the switcher spelled it.
    |
    | Related to `supported` above but not the same list: that one is what the API can *answer*
    | in and is matched loosely, this one is what a user can *pick* and is matched exactly.
    |
    */

    'selectable' => ['en-US', 'ru-RU'],

];
