<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// =======================================================================================
// CORTES DE VENTAS DIARIOS AUTOMÁTICOS
// =======================================================================================

// Crear corte diario automáticamente a las 11:59 PM cada día
Schedule::command('sales:create-daily-cut')
    ->dailyAt('23:59')
    ->timezone('America/Lima')
    ->description('Crear corte diario de ventas automáticamente')
    ->onFailure(function () {
        \Log::error('Failed to create daily sales cut');
    })
    ->onSuccess(function () {
        \Log::info('Daily sales cut created successfully');
    });

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

// =======================================================================================
// LOGICWARE DATA SYNC SCHEDULE
// =======================================================================================

// Importar contratos desde Logicware cada 30 minutos
// Mantiene sincronizada la información de contratos entre sistemas
Schedule::command('logicware:import-contracts')
    ->everyThirtyMinutes()
    ->timezone('America/Lima')
    ->description('Sincronización de contratos desde Logicware cada 30 minutos')
    ->onFailure(function () {
        \Log::error('[LogicwareSync] Error al importar contratos desde Logicware');
    })
    ->onSuccess(function () {
        \Log::info('[LogicwareSync] Contratos importados exitosamente desde Logicware');
    });

// =======================================================================================
// PAYMENT SCHEDULES GENERATION
// =======================================================================================

// Generar cronogramas de pagos para contratos sin cronograma
// Se ejecuta diariamente a las 2:00 AM
Schedule::command('collections:generate-schedules')
    ->dailyAt('02:00')
    ->timezone('America/Lima')
    ->description('Generar cronogramas de pagos para contratos activos sin cronograma')
    ->onFailure(function () {
        \Log::error('[ScheduleGenerator] Error al generar cronogramas de pagos');
    })
    ->onSuccess(function () {
        \Log::info('[ScheduleGenerator] Cronogramas de pagos generados exitosamente');
    });

// =======================================================================================
// CACHE MAINTENANCE
// =======================================================================================

// Limpiar cache obsoleto diariamente a las 3:00 AM
Schedule::command('cache:prune-stale-tags')
    ->dailyAt('03:00')
    ->timezone('America/Lima')
    ->description('Limpieza de tags obsoletos en cache')
    ->onFailure(function () {
        \Log::error('[CacheMaintenance] Error al limpiar cache obsoleto');
    })
    ->onSuccess(function () {
        \Log::info('[CacheMaintenance] Cache limpiado exitosamente');
    });

// =======================================================================================
// DATABASE BACKUP (Recomendado agregar)
// =======================================================================================

// Backup automático de base de datos diario a las 4:00 AM
// Nota: Requiere configurar el comando de backup
// Schedule::command('backup:run --only-db')
//     ->dailyAt('04:00')
//     ->timezone('America/Lima')
//     ->description('Backup automático de base de datos')
//     ->onFailure(function () {
//         \Log::error('[Backup] Error al crear backup de base de datos');
//     })
//     ->onSuccess(function () {
//         \Log::info('[Backup] Backup de base de datos creado exitosamente');
//     });
