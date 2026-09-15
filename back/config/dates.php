<?php

return [

    /*
    |---------------------------------------------------------------------------
    | The timezone a user's dates are counted in when we have not been told
    |---------------------------------------------------------------------------
    |
    | The client sends the device's own zone with the sign-in exchange
    | (`Intl.DateTimeFormat().resolvedOptions().timeZone`), so this is only reached by a
    | caller that sent nothing — a third-party client, or a browser that would not say. UTC
    | rather than the server's zone: it is the one answer that is wrong by the same amount for
    | everybody, and a guess at the server's own zone would be wrong in a way that looks right
    | from here.
    |
    | What it costs the user is the hour their reminder arrives, not which dates they see.
    |
    */

    'timezone' => env('DATES_DEFAULT_TIMEZONE', 'UTC'),

];
