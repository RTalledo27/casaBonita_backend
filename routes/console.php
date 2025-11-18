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

// =======================================================================================
// LOGICWARE TOKEN RENEWAL SCHEDULE
// =======================================================================================

// Renovar Bearer Token de Logicware cada 5 minutos según recomendación oficial
// Tokens duran 24h pero la renovación frecuente evita problemas de expiración
Schedule::command('logicware:renew-token')
    ->everyFiveMinutes()
    ->timezone('America/Lima')
    ->description('Renovación automática del Bearer Token de Logicware (cada 5 minutos)')
    ->onFailure(function () {
        \Log::error('[LogicwareScheduler] Error al renovar token automáticamente');
    })
    ->onSuccess(function () {
        \Log::info('[LogicwareScheduler] Token de Logicware verificado/renovado');
    });
