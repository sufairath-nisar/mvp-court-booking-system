<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment('Book the court. Play the game.');
})->purpose('Display an inspiring quote');
