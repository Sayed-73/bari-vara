<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


// Add at the BOTTOM of routes/console.php:
Schedule::command('rent:generate-charges')->monthlyOn(1, '06:00');
