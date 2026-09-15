<?php

/**
 * What the API says, in the source language.
 *
 * Every other file under `lang/` is a translation of this one, key for key — the same rule the
 * front's catalogues follow (`front/src/i18n/en-US/index.js`). Which language a request gets is
 * decided by `App\Http\Middleware\SetLocale`.
 *
 * The front words most outcomes itself from the status code, because a screen has room for more
 * than a response envelope does. These are translated regardless — a message only ever seen by a
 * third-party client or a developer is still a sentence, and leaving half a catalogue in English
 * is how the other half rots.
 */
return [

    'auth' => [
        'invalid_token' => 'Invalid token',
        'unauthenticated' => 'Unauthenticated.',
        'logged_out' => 'Logged out',
    ],

    'account' => [
        'deleted' => 'Account deleted',
    ],

];
