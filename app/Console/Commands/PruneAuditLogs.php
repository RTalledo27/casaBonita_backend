<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PruneAuditLogs extends Command
{
    protected $signature = 'audit:prune {--dry-run : No borra, solo muestra conteos} {--batch=1000 : Tamaño de lote para borrado}';

    protected $description = 'Limpia logs de auditoría antiguos según política de retención.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $batchSize = max(100, (int) $this->option('batch'));

        $systemHttpDays = (int) env('AUDIT_TECH_HTTP_DAYS', 90);
        $systemLoginFailedDays = (int) env('AUDIT_TECH_LOGIN_FAILED_DAYS', 180);
        $systemOtherDays = (int) env('AUDIT_TECH_OTHER_DAYS', 30);

        $pruneBusinessEnabled = filter_var(env('AUDIT_PRUNE_BUSINESS_ENABLED', false), FILTER_VALIDATE_BOOL);
        $businessDays = (int) env('AUDIT_BUSINESS_DAYS', 1825);

        $this->info('Audit prune policy');
        $this->line('- system_activity_logs http_request: ' . $systemHttpDays . ' días');
        $this->line('- system_activity_logs login_failed: ' . $systemLoginFailedDays . ' días');
        $this->line('- system_activity_logs otros: ' . $systemOtherDays . ' días');
        $this->line('- user_activity_logs habilitado: ' . ($pruneBusinessEnabled ? 'sí' : 'no'));
        if ($pruneBusinessEnabled) {
            $this->line('- user_activity_logs: ' . $businessDays . ' días');
        }
        $this->newLine();

        $totalDeleted = 0;

        if (Schema::hasTable('system_activity_logs')) {
            $totalDeleted += $this->pruneSystemLogs('http_request', $systemHttpDays, $dryRun, $batchSize);
            $totalDeleted += $this->pruneSystemLogs('login_failed', $systemLoginFailedDays, $dryRun, $batchSize);
            $totalDeleted += $this->pruneSystemLogsOther([$systemOtherDays], ['http_request', 'login_failed'], $dryRun, $batchSize);
        } else {
            $this->warn('Tabla system_activity_logs no existe. Saltando.');
        }

        if ($pruneBusinessEnabled) {
            if (Schema::hasTable('user_activity_logs')) {
                $totalDeleted += $this->pruneTableByAge('user_activity_logs', $businessDays, $dryRun, $batchSize);
            } else {
                $this->warn('Tabla user_activity_logs no existe. Saltando.');
            }
        }

        $this->newLine();
        $this->info(($dryRun ? 'Dry-run. ' : '') . 'Total borrados: ' . $totalDeleted);

        return self::SUCCESS;
    }

    private function pruneSystemLogs(string $action, int $days, bool $dryRun, int $batchSize): int
    {
        $days = max(0, $days);
        $cutoff = Carbon::now()->subDays($days);

        $query = DB::table('system_activity_logs')
            ->where('action', $action)
            ->where('created_at', '<', $cutoff);

        $count = (int) $query->count();
        $this->line("system_activity_logs: {$action} > {$days} días => {$count}");

        if ($dryRun || $count === 0) {
            return 0;
        }

        $deleted = $this->deleteInBatches('system_activity_logs', $query, $batchSize);
        $this->line("  borrados: {$deleted}");
        return $deleted;
    }

    private function pruneSystemLogsOther(array $daysConfig, array $excludeActions, bool $dryRun, int $batchSize): int
    {
        $days = max(0, (int) ($daysConfig[0] ?? 0));
        $cutoff = Carbon::now()->subDays($days);

        $query = DB::table('system_activity_logs')
            ->whereNotIn('action', $excludeActions)
            ->where('created_at', '<', $cutoff);

        $count = (int) $query->count();
        $this->line("system_activity_logs: otros (excepto " . implode(', ', $excludeActions) . ") > {$days} días => {$count}");

        if ($dryRun || $count === 0) {
            return 0;
        }

        $deleted = $this->deleteInBatches('system_activity_logs', $query, $batchSize);
        $this->line("  borrados: {$deleted}");
        return $deleted;
    }

    private function pruneTableByAge(string $table, int $days, bool $dryRun, int $batchSize): int
    {
        $days = max(0, $days);
        $cutoff = Carbon::now()->subDays($days);

        $query = DB::table($table)->where('created_at', '<', $cutoff);

        $count = (int) $query->count();
        $this->line("{$table}: > {$days} días => {$count}");

        if ($dryRun || $count === 0) {
            return 0;
        }

        $deleted = $this->deleteInBatches($table, $query, $batchSize);
        $this->line("  borrados: {$deleted}");
        return $deleted;
    }

    private function deleteInBatches(string $table, $baseQuery, int $batchSize): int
    {
        $total = 0;

        while (true) {
            $ids = (clone $baseQuery)
                ->orderBy('id')
                ->limit($batchSize)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted = DB::table($table)->whereIn('id', $ids->all())->delete();
            $total += (int) $deleted;

            if ($deleted === 0) {
                break;
            }
        }

        return $total;
    }
}

