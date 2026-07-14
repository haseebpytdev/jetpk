<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('ota:cleanup-expired-access')->hourly();
Schedule::command('ota:send-daily-report')->dailyAt('08:00');
Schedule::command('ota:send-agency-booking-activity-summary --all-active-agencies')
    ->dailyAt('07:10')
    ->when(fn (): bool => (bool) config('ota.agency_booking_activity_summary_daily_enabled', true));
Schedule::command('ota:send-weekly-report')->weeklyOn(1, '08:00');
Schedule::command('ota:send-monthly-report')->monthlyOn(1, '08:00');
Schedule::command('ota:send-monthly-ledgers')->monthlyOn(1, '09:00');
Schedule::command('homepage:refresh-featured-fares')->dailyAt('05:00');
Schedule::command('jetpk:homepage-route-fares-refresh')->dailyAt('00:30');
Schedule::command('ota:process-abandoned-flight-searches')->everyFifteenMinutes();
Schedule::command('ota:send-abandoned-flight-searches')->everyFifteenMinutes();
Schedule::command('group-ticketing:sync-inventory')->dailyAt('02:00');
Schedule::command('group-ticketing:release-expired')->everyMinute();
Schedule::command('jetpk:branding-background-cleanup')->dailyAt('03:15');
