<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Unpaid hold window
    |--------------------------------------------------------------------------
    |
    | How long (in minutes) a pending booking holds its seats before the hold
    | expires and the seats are released back to the pool. Set to 0 to disable
    | expiry (holds never time out).
    |
    */

    'hold_minutes' => (int) env('BOOKING_HOLD_MINUTES', 1),

];
