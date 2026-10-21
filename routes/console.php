<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Las horas son de Caracas: la app corre en America/Caracas (#368).
Schedule::command('generate:alerts')->dailyAt('06:00');
Schedule::command('finance:reconcile-charges')->dailyAt('05:50');
Schedule::command('levels:send-renewal-reminders')->dailyAt('06:20');
Schedule::command('finance:send-payment-reminders')->dailyAt('07:00');
Schedule::command('bcv:sync-rates')->dailyAt('05:40');
Schedule::command('finance:generate-extracurricular-charges')->dailyAt('06:05');
// Resumen diario de pendientes para cada admin, a las 7:00. #346
Schedule::command('admins:send-daily-pending')->dailyAt('07:00');
