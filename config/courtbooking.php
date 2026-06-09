<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Slot generation horizon
    |--------------------------------------------------------------------------
    |
    | Default number of days ahead that `generate-slots` produces when no
    | explicit end_date (or `days`) is supplied — i.e. the rolling window an
    | admin gets when they "just generate". Overridable per request via `days`.
    |
    */

    'slot_generation_horizon_days' => (int) env('SLOT_GENERATION_HORIZON_DAYS', 30),

];
