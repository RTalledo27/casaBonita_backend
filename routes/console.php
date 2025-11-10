<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// =======================================================================================
// AUTOMATED BONUS CALCULATION SCHEDULE
// =======================================================================================

// Calcular bonos automáticamente el día 1 de cada mes a las 00:05
// Calcula bonos del mes anterior
Schedule::command('bonuses:calculate')
    ->monthlyOn(1, '00:05')
    ->timezone('America/Lima')
    ->description('Cálculo automático de bonos mensuales')
    ->onFailure(function () {
        // Log error o enviar notificación
        \Log::error('Failed to calculate monthly bonuses');
    })
    ->onSuccess(function () {
        \Log::info('Monthly bonuses calculated successfully');
    });

// Calcular bonos quincenales (día 1 y 16 de cada mes)
Schedule::command('bonuses:calculate --type=BIWEEKLY')
    ->cron('0 1 1,16 * *') // Día 1 y 16 a la 1:00 AM
    ->timezone('America/Lima')
    ->description('Cálculo automático de bonos quincenales');

// Calcular bonos trimestrales (solo en marzo, junio, septiembre y diciembre)
Schedule::command('bonuses:calculate --type=QUARTERLY')
    ->cron('0 2 1 3,6,9,12 *') // Primer día de cada trimestre a las 2:00 AM
    ->timezone('America/Lima')
    ->description('Cálculo automático de bonos trimestrales');

