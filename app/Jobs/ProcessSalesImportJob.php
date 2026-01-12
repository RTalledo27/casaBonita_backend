<?php

namespace App\Jobs;

use App\Models\AsyncImportProcess;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Inventory\Services\ExternalLotImportService;
use Throwable;

class ProcessSalesImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1200;

    protected AsyncImportProcess $importProcess;
    protected array $options;

    public function __construct(AsyncImportProcess $importProcess, array $options = [])
    {
        $this->importProcess = $importProcess;
        $this->options = $options;
    }

    public function handle(): void
    {
        $startDate = $this->options['startDate'] ?? null;
        $endDate = $this->options['endDate'] ?? null;
        $forceRefresh = (bool) ($this->options['force_refresh'] ?? false);

        try {
            $this->importProcess->update([
                'status' => 'processing',
                'started_at' => now(),
                'progress_percentage' => 5,
                'summary' => [
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'force_refresh' => $forceRefresh,
                ],
            ]);

            /** @var ExternalLotImportService $service */
            $service = app(ExternalLotImportService::class);

            Log::info('[ProcessSalesImportJob] Starting sales import', [
                'process_id' => $this->importProcess->id,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'force_refresh' => $forceRefresh,
            ]);

            $result = $service->importSales($startDate, $endDate, $forceRefresh);

            $stats = $result['data']['stats'] ?? null;
            if (is_array($stats)) {
                $total = (int) ($stats['total'] ?? 0);
                $processed = $total;
                $successful = (int) ($stats['created'] ?? 0) + (int) ($stats['updated'] ?? 0);
                $failed = (int) ($stats['errors'] ?? 0);
                $progress = $total > 0 ? ($processed / $total) * 100 : 90;

                $this->importProcess->update([
                    'total_rows' => $total,
                    'processed_rows' => $processed,
                    'successful_rows' => $successful,
                    'failed_rows' => $failed,
                    'progress_percentage' => $progress,
                ]);
            }

            if (!($result['success'] ?? false)) {
                $this->importProcess->markAsFailed([
                    'message' => $result['message'] ?? 'Importación fallida',
                    'errors' => $result['data']['errors'] ?? [],
                ]);
                return;
            }

            $this->importProcess->markAsCompleted([
                'message' => $result['message'] ?? 'Importación completada',
                'result' => $result['data'] ?? null,
            ]);

            Log::info('[ProcessSalesImportJob] Sales import completed', [
                'process_id' => $this->importProcess->id,
            ]);
        } catch (Throwable $e) {
            Log::error('[ProcessSalesImportJob] Sales import failed', [
                'process_id' => $this->importProcess->id,
                'error' => $e->getMessage(),
            ]);

            $this->importProcess->markAsFailed([
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
