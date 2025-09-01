<?php

namespace Modules\Sales\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Sales\Services\ContractImportService;
use Modules\Sales\Models\ContractImportLog;

class ProcessContractImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutos
    public $tries = 3;
    public $maxExceptions = 1;

    protected string $filePath;
    protected int $userId;
    protected array $options;
    protected ?string $importLogId;

    /**
     * Create a new job instance.
     */
    public function __construct(
        string $filePath, 
        int $userId, 
        array $options = [],
        ?string $importLogId = null
    ) {
        $this->filePath = $filePath;
        $this->userId = $userId;
        $this->options = $options;
        $this->importLogId = $importLogId;
    }

    /**
     * Execute the job.
     */
    public function handle(ContractImportService $importService): void
    {
        try {
            Log::info("Iniciando procesamiento de importación de contratos", [
                'file_path' => $this->filePath,
                'user_id' => $this->userId,
                'options' => $this->options
            ]);

            // Actualizar estado del log si existe
            if ($this->importLogId) {
                $this->updateImportLog('processing', 'Procesando archivo...');
            }

            // Verificar que el archivo existe
            if (!Storage::disk('local')->exists($this->filePath)) {
                throw new Exception('El archivo no existe en el almacenamiento');
            }

            $fullPath = Storage::disk('local')->path($this->filePath);

            // Procesar el archivo
            $result = $importService->processExcel($fullPath);

            // Actualizar log con resultados
            if ($this->importLogId) {
                $status = $result['success'] ? 'completed' : 'failed';
                $message = $result['message'];
                
                $this->updateImportLog($status, $message, [
                    'processed' => $result['processed'],
                    'errors' => $result['errors'],
                    'error_details' => $result['error_details']
                ]);
            }

            // Limpiar archivo temporal
            Storage::disk('local')->delete($this->filePath);

            Log::info("Importación de contratos completada", [
                'success' => $result['success'],
                'processed' => $result['processed'],
                'errors' => $result['errors']
            ]);

            // Enviar notificación al usuario (opcional)
            $this->notifyUser($result);

        } catch (Exception $e) {
            Log::error("Error en procesamiento de importación de contratos", [
                'error' => $e->getMessage(),
                'file_path' => $this->filePath,
                'user_id' => $this->userId
            ]);

            // Actualizar log con error
            if ($this->importLogId) {
                $this->updateImportLog('failed', 'Error: ' . $e->getMessage());
            }

            // Limpiar archivo temporal
            if (Storage::disk('local')->exists($this->filePath)) {
                Storage::disk('local')->delete($this->filePath);
            }

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error("Job de importación de contratos falló", [
            'error' => $exception->getMessage(),
            'file_path' => $this->filePath,
            'user_id' => $this->userId
        ]);

        // Actualizar log con fallo
        if ($this->importLogId) {
            $this->updateImportLog('failed', 'Job falló: ' . $exception->getMessage());
        }

        // Limpiar archivo temporal
        if (Storage::disk('local')->exists($this->filePath)) {
            Storage::disk('local')->delete($this->filePath);
        }
    }

    /**
     * Actualizar el log de importación
     */
    private function updateImportLog(string $status, string $message, array $results = []): void
    {
        try {
            // Aquí se actualizaría el modelo ContractImportLog si existe
            // Por ahora solo logueamos
            Log::info("Actualizando log de importación", [
                'import_log_id' => $this->importLogId,
                'status' => $status,
                'message' => $message,
                'results' => $results
            ]);
        } catch (Exception $e) {
            Log::warning("No se pudo actualizar el log de importación: " . $e->getMessage());
        }
    }

    /**
     * Notificar al usuario sobre el resultado
     */
    private function notifyUser(array $result): void
    {
        try {
            // Aquí se podría implementar notificación por email, websocket, etc.
            Log::info("Notificando usuario sobre importación", [
                'user_id' => $this->userId,
                'result' => $result
            ]);
        } catch (Exception $e) {
            Log::warning("No se pudo notificar al usuario: " . $e->getMessage());
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'contract-import',
            'user:' . $this->userId,
            'file:' . basename($this->filePath)
        ];
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [30, 60, 120]; // 30 segundos, 1 minuto, 2 minutos
    }
}