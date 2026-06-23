<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily HACCP expiry alert at 07:00
Schedule::command('haccp:alert-scadenze')->dailyAt('07:00');

// Daily DB backup at 03:00
Schedule::command('db:backup')->dailyAt('03:00');
