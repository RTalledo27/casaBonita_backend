<?php

namespace App\Jobs;

use App\Models\AsyncImportProcess;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Inventory\Services\LotImportService;
use Exception;

class ProcessLotImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected AsyncImportProcess $importProcess;
    protected array $options;

    /**
     * Create a new job instance.
     */
    public function __construct(AsyncImportProcess $importProcess, array $options = [])
    {
        $this->importProcess = $importProcess;
        $this->options = $options;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Starting async lot import process', [
                'process_id' => $this->importProcess->id,
                'file_name' => $this->importProcess->file_name
            ]);

            // Mark as processing
            $this->importProcess->update([
                'status' => 'processing',
                'started_at' => now()
            ]);

            // Get the file from storage
            $filePath = $this->importProcess->file_path;
            if (!Storage::exists($filePath)) {
                throw new Exception("File not found: {$filePath}");
            }

            // Initialize the import service
            $importService = new LotImportService();

            // Process the file with progress tracking
            $result = $importService->processExcelAsync(
                $this->importProcess,
                $this->options
            );

            // Mark as completed with summary
            $this->importProcess->markAsCompleted([
                'total_processed' => $result['total_processed'] ?? 0,
                'successful_imports' => $result['successful_imports'] ?? 0,
                'failed_imports' => $result['failed_imports'] ?? 0,
                'warnings' => $result['warnings'] ?? [],
                'processing_time' => now()->diffInSeconds($this->importProcess->started_at),
                'file_size' => Storage::size($filePath)
            ]);

            Log::info('Async lot import process completed successfully', [
                'process_id' => $this->importProcess->id,
                'summary' => $this->importProcess->summary
            ]);

        } catch (Exception $e) {
            Log::error('Async lot import process failed', [
                'process_id' => $this->importProcess->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->importProcess->markAsFailed([
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'failed_at' => now()->toISOString()
            ]);

            // Re-throw the exception to mark the job as failed
            throw $e;
        } finally {
            // Clean up the temporary file if it exists
            if (Storage::exists($this->importProcess->file_path)) {
                Storage::delete($this->importProcess->file_path);
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error('ProcessLotImportJob failed permanently', [
            'process_id' => $this->importProcess->id,
            'exception' => $exception->getMessage()
        ]);

        $this->importProcess->markAsFailed([
            'error_message' => $exception->getMessage(),
            'error_code' => $exception->getCode(),
            'failed_permanently' => true,
            'failed_at' => now()->toISOString()
        ]);
    }
}