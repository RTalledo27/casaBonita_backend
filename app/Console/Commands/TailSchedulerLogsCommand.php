<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TailSchedulerLogsCommand extends Command
{
    protected $signature = 'scheduler:tail {--lines=50 : Number of lines to show}';
    protected $description = 'Ver logs del scheduler en tiempo real';

    public function handle()
    {
        $lines = $this->option('lines');
        $logFile = storage_path('logs/laravel.log');

        if (!file_exists($logFile)) {
            $this->error('❌ No se encontró el archivo de logs');
            return 1;
        }

        $this->info('📋 Últimos logs del Scheduler (últimas ' . $lines . ' líneas)');
        $this->info('═══════════════════════════════════════════════════════════════');
        $this->newLine();

        // Leer últimas líneas
        $content = file_get_contents($logFile);
        $allLines = explode("\n", $content);
        $recentLines = array_slice($allLines, -$lines);

        // Filtrar líneas relevantes del scheduler
        $patterns = [
            'LogicwareScheduler',
            'SalesCut',
            'BonusCalculator',
            'LogicwareImport',
            'ScheduleGenerator',
            'Schedule running',
        ];

        $filtered = [];
        foreach ($recentLines as $line) {
            foreach ($patterns as $pattern) {
                if (stripos($line, $pattern) !== false) {
                    $filtered[] = $line;
                    break;
                }
            }
        }

        if (empty($filtered)) {
            $this->warn('⚠️  No se encontraron logs recientes del scheduler');
            $this->info('Esto podría significar:');
            $this->line('  • El scheduler no está corriendo');
            $this->line('  • El cron job no está configurado');
            $this->line('  • Los logs fueron rotados recientemente');
            $this->newLine();
            $this->info('Verifica el cron job con: crontab -l');
            return 0;
        }

        foreach ($filtered as $line) {
            // Colorear según el tipo de log
            if (stripos($line, 'ERROR') !== false || stripos($line, 'error') !== false) {
                $this->error($line);
            } elseif (stripos($line, 'WARNING') !== false || stripos($line, 'warn') !== false) {
                $this->warn($line);
            } elseif (stripos($line, 'INFO') !== false || stripos($line, 'exitosamente') !== false || stripos($line, 'renovado') !== false) {
                $this->info($line);
            } else {
                $this->line($line);
            }
        }

        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════════════');
        $this->info("📊 Total de logs del scheduler: " . count($filtered));
        $this->newLine();
        $this->comment('💡 Tip: Usa --lines=100 para ver más líneas');
        $this->comment('💡 Para ver logs en tiempo real: tail -f storage/logs/laravel.log | grep -E "Scheduler|SalesCut|Bonus|Logicware"');

        return 0;
    }
}
