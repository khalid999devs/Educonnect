<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Support inbox
    |--------------------------------------------------------------------------
    | Where public contact-form submissions are delivered. Falls back to the
    | default mail "from" address so the form works out of the box; production
    | should point this at the monitored support inbox.
    */
    'to_address' => env('CONTACT_TO_ADDRESS', env('MAIL_FROM_ADDRESS', 'hello@example.com')),
];
