<?php

return [
    // Long random secret (min 24 characters) for the /cron/{token} URL. Set CRON_TOKEN in the environment.
    'token' => env('CRON_TOKEN'),
];
