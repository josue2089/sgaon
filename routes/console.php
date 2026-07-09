<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('generate:alerts')->dailyAt('06:00');
Schedule::command('finance:reconcile-charges')->dailyAt('05:50');
Schedule::command('finance:generate-recurring-charges')->monthlyOn(1, '06:10');
Schedule::command('levels:send-renewal-reminders')->dailyAt('06:20');
Schedule::command('finance:send-payment-reminders')->dailyAt('07:00');
Schedule::command('bcv:sync-rates')->dailyAt('05:40');
