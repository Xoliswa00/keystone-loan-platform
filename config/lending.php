<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Rejection Cooldown (NCR alignment)
    |--------------------------------------------------------------------------
    | Number of days a client must wait after a rejection before submitting
    | a new application. 30 days is industry standard.
    | NCA Section 81 (reckless credit) context: re-lending too soon without
    | re-assessment is considered reckless.
    */
    'rejection_cooldown_days' => env('LENDING_REJECTION_COOLDOWN_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Maximum active loans per client
    |--------------------------------------------------------------------------
    | We currently allow only 1 active loan at a time.
    */
    'max_active_loans' => 1,
];
