<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Modules\Sales\Models\Contract;
use Modules\Inventory\Models\Lot;
use App\Services\LogicwareApiService;
use App\Services\LogicwareContractImporter;
use Modules\Sales\Models\LogicwarePayment;
use Modules\Sales\Models\PaymentSchedule;
use Carbon\Carbon;

class LogicwareWebhookHandler
{
    protected $apiService;
    protected $contractImporter;

    public function __construct()
    {
        $this->apiService = new LogicwareApiService();
        $this->contractImporter = new LogicwareContractImporter($this->apiService);
    }

    /**
     * Procesar webhook según el tipo de evento
     * 
     * @param array $payload
     * @return array Resultado del procesamiento
     */
    public function handle(array $payload): array
    {
        $eventType = $payload['eventType'];
        $data = $payload['data'] ?? [];
        $data['_messageId'] = $payload['messageId'] ?? null;
        $data['_correlationId'] = $payload['correlationId'] ?? null;
        $data['_eventTimestamp'] = $payload['eventTimestamp'] ?? null;
        $sourceId = $payload['sourceId'] ?? null;

        Log::info("📥 Manejando evento webhook: {$eventType}", [
            'sourceId' => $sourceId,
            'correlationId' => $payload['correlationId'] ?? null
        ]);

        // Enrutar al handler específico según el tipo de evento
        return match ($eventType) {
            'sales.process.completed' => $this->handleSalesCompleted($data, $sourceId),
            'separation.process.completed' => $this->handleSeparationCompleted($data, $sourceId),
            'payment.created' => $this->handlePaymentCreated($data, $sourceId),
            'schedule.created' => $this->handleScheduleCreated($data, $sourceId),
            'unit.updated' => $this->handleUnitUpdated($data, $sourceId),
            'unit.created' => $this->handleUnitCreated($data, $sourceId),
            'proforma.created' => $this->handleProformaCreated($data, $sourceId),
            default => $this->handleUnknownEvent($eventType, $data, $sourceId)
        };
    }

    /**
     * Venta completada - Sincronizar contrato completo
     */
    protected function handleSalesCompleted(array $data, $sourceId): array
    {
        Log::info('💰 Procesando venta completada', ['sourceId' => $sourceId]);

        try {
            // Obtener ventas recientes desde Logicware API
            // Usar fecha de hoy para traer las ventas más recientes
            $today = date('Y-m-d');
            $salesData = $this->apiService->getSales($today, $today, true);
            
            // Buscar la venta específica en los datos obtenidos
            $saleInfo = $this->findSaleBySourceId($salesData, $sourceId);
            
            if (!$saleInfo) {
                Log::warning('⚠️ No se encontró la venta en datos del día, ampliando búsqueda', [
                    'sourceId' => $sourceId
                ]);
                
                // Intentar con últimos 30 días
                $startDate = date('Y-m-d', strtotime('-30 days'));
                $salesData = $this->apiService->getSales($startDate, $today, true);
                $saleInfo = $this->findSaleBySourceId($salesData, $sourceId);
                
                if (!$saleInfo) {
                    // Como último recurso, importar todas las ventas
                    Log::warning('⚠️ Venta no encontrada, ejecutando importación completa', [
                        'sourceId' => $sourceId
                    ]);
                    
                    $this->contractImporter->importContracts();
                    
                    return [
                        'action' => 'sales_completed',
                        'message' => 'Importación masiva activada por nueva venta',
                        'sourceId' => $sourceId
                    ];
                }
            }

            // Importar/actualizar el contrato específico
            $contract = $this->contractImporter->importSingleSale($saleInfo);

            return [
                'action' => 'sales_completed',
                'contract_id' => $contract->id,
                'contract_number' => $contract->contract_number,
                'client_name' => $contract->client->full_name ?? 'N/A',
                'message' => 'Contrato sincronizado exitosamente'
            ];

        } catch (\Exception $e) {
            Log::error('❌ Error procesando venta completada', [
                'sourceId' => $sourceId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Separación completada - Actualizar estado
     */
    protected function handleSeparationCompleted(array $data, $sourceId): array
    {
        Log::info('🔒 Procesando separación completada', ['sourceId' => $sourceId]);

        try {
            // Buscar contrato por sourceId (proformaId de Logicware)
            $contract = Contract::where('logicware_proforma_id', $sourceId)->first();

            if (!$contract) {
                Log::warning('⚠️ Contrato no encontrado para separación', [
                    'sourceId' => $sourceId
                ]);
                
                // Intentar importar desde Logicware
                $this->contractImporter->importContracts();
                
                return [
                    'action' => 'separation_completed',
                    'message' => 'Contrato no encontrado, importación activada',
                    'sourceId' => $sourceId
                ];
            }

            // Actualizar estado del contrato
            $contract->update([
                'status' => 'separado'
            ]);

            return [
                'action' => 'separation_completed',
                'contract_id' => $contract->id,
                'contract_number' => $contract->contract_number,
                'client_name' => $contract->client->full_name ?? 'N/A',
                'message' => 'Estado actualizado a separado'
            ];

        } catch (\Exception $e) {
            Log::error('❌ Error procesando separación', [
                'sourceId' => $sourceId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Pago creado - Sincronizar cronograma
     */
    protected function handlePaymentCreated(array $data, $sourceId): array
    {
        Log::info('💳 Procesando nuevo pago', ['sourceId' => $sourceId]);

        try {
            // sourceId podría ser el ID del cronograma o del pago
            // Necesitamos el correlativo del contrato para sincronizar
            
            if (isset($data['correlative']) || isset($data['ord_correlative'])) {
                $correlative = $data['correlative'] ?? $data['ord_correlative'];
                
                // Buscar contrato por correlativo
                $contract = Contract::where('contract_number', $correlative)->first();
                
                if ($contract) {
                    // Sincronizar cronograma desde Logicware
                    $this->contractImporter->syncPaymentScheduleFromLogicware(
                        $contract,
                        $correlative
                    );

                    $this->storeLogicwarePayment($data, $sourceId, $contract);

                    return [
                        'action' => 'payment_created',
                        'contract_id' => $contract->id,
                        'contract_number' => $contract->contract_number,
                        'amount' => $data['amount'] ?? 'N/A',
                        'message' => 'Cronograma de pagos sincronizado'
                    ];
                }
            }

            // Si no tenemos suficiente info, importar todo
            Log::warning('⚠️ Info insuficiente para sincronizar pago específico', [
                'data' => $data
            ]);
            
            return [
                'action' => 'payment_created',
                'message' => 'Pago registrado, sincronización manual requerida',
                'sourceId' => $sourceId
            ];

        } catch (\Exception $e) {
            Log::error('❌ Error procesando pago', [
                'sourceId' => $sourceId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    protected function storeLogicwarePayment(array $data, $sourceId, Contract $contract): void
    {
        try {
            $messageId = $data['_messageId'] ?? null;
            $correlationId = $data['_correlationId'] ?? null;
            $eventTs = $data['_eventTimestamp'] ?? null;

            $externalPaymentNumber =
                $data['payment_number']
                ?? $data['paymentNumber']
                ?? $data['pay_number']
                ?? $data['number']
                ?? $data['payment_id']
                ?? $data['paymentId']
                ?? null;

            $installmentNumber = isset($data['installmentNumber']) ? (int) $data['installmentNumber'] : (isset($data['installment_number']) ? (int) $data['installment_number'] : null);
            $scheduleDetId = $data['scheduleDetId'] ?? $data['schedule_det_id'] ?? null;

            $schedule = null;
            if ($scheduleDetId) {
                $schedule = PaymentSchedule::where('contract_id', $contract->contract_id)
                    ->where('logicware_schedule_det_id', $scheduleDetId)
                    ->first();
            }
            if (!$schedule && $installmentNumber) {
                $schedule = PaymentSchedule::where('contract_id', $contract->contract_id)
                    ->where('installment_number', $installmentNumber)
                    ->first();
            }

            $paymentDateRaw = $data['payment_date'] ?? $data['paymentDate'] ?? $data['date'] ?? $eventTs ?? null;
            $paymentDate = $paymentDateRaw ? Carbon::parse($paymentDateRaw) : null;

            $amountRaw = $data['amount'] ?? $data['total'] ?? $data['value'] ?? 0;
            $amount = is_numeric($amountRaw) ? (float) $amountRaw : (float) preg_replace('/[^0-9.\-]/', '', (string) $amountRaw);

            $method = $data['method'] ?? $data['payment_method'] ?? $data['paymentMethod'] ?? null;
            $bankName = $data['bank'] ?? $data['bank_name'] ?? $data['bankName'] ?? null;
            $referenceNumber = $data['reference_number'] ?? $data['referenceNumber'] ?? $data['ref'] ?? $data['reference'] ?? null;
            $status = $data['status'] ?? null;
            $userName = $data['user'] ?? $data['user_name'] ?? $data['userName'] ?? null;
            $currency = $data['currency'] ?? $contract->currency ?? null;

            $uniqueExt = $externalPaymentNumber ? (string) $externalPaymentNumber : null;
            if (!$messageId && !$uniqueExt && !$sourceId) {
                return;
            }

            $query = LogicwarePayment::query();
            if ($messageId) $query->where('message_id', $messageId);
            if ($uniqueExt) $query->where('external_payment_number', $uniqueExt);
            if (!$uniqueExt && $sourceId) $query->where('source_id', (string) $sourceId);

            $exists = $query->exists();
            if ($exists) return;

            LogicwarePayment::create([
                'message_id' => $messageId,
                'correlation_id' => $correlationId,
                'source_id' => $sourceId ? (string) $sourceId : null,
                'contract_id' => $contract->contract_id,
                'schedule_id' => $schedule?->schedule_id,
                'installment_number' => $installmentNumber,
                'external_payment_number' => $uniqueExt,
                'payment_date' => $paymentDate,
                'amount' => $amount,
                'currency' => $currency,
                'method' => $method,
                'bank_name' => $bankName,
                'reference_number' => $referenceNumber,
                'status' => $status,
                'user_name' => is_string($userName) ? $userName : null,
                'raw' => $data,
            ]);
        } catch (\Throwable $e) {
            Log::warning('⚠️ No se pudo guardar detalle de pago Logicware', [
                'error' => $e->getMessage(),
                'sourceId' => $sourceId,
            ]);
        }
    }

    /**
     * Cronograma creado/actualizado - Sincronizar
     */
    protected function handleScheduleCreated(array $data, $sourceId): array
    {
        Log::info('📅 Procesando cronograma creado/actualizado', ['sourceId' => $sourceId]);

        try {
            if (isset($data['correlative']) || isset($data['ord_correlative'])) {
                $correlative = $data['correlative'] ?? $data['ord_correlative'];
                
                $contract = Contract::where('contract_number', $correlative)->first();
                
                if ($contract) {
                    // Sincronizar cronograma completo
                    $result = $this->contractImporter->syncPaymentScheduleFromLogicware(
                        $contract,
                        $correlative
                    );

                    return [
                        'action' => 'schedule_created',
                        'contract_id' => $contract->id,
                        'contract_number' => $contract->contract_number,
                        'installments_synced' => $result['total_installments'] ?? 0,
                        'message' => 'Cronograma sincronizado completamente'
                    ];
                }
            }

            return [
                'action' => 'schedule_created',
                'message' => 'Cronograma actualizado, sincronización pendiente',
                'sourceId' => $sourceId
            ];

        } catch (\Exception $e) {
            Log::error('❌ Error procesando cronograma', [
                'sourceId' => $sourceId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Lote/unidad actualizado - Sincronizar estado
     */
    protected function handleUnitUpdated(array $data, $sourceId): array
    {
        Log::info('🏠 Procesando actualización de lote', ['sourceId' => $sourceId, 'data' => $data]);

        try {
            $lot = null;
            
            // Buscar lote por código externo (unit_number de Logicware)
            if (isset($data['unit_number'])) {
                $lot = Lot::where('external_code', $data['unit_number'])->first();
            }
            
            // Si no se encuentra, buscar por external_id
            if (!$lot && is_numeric($sourceId)) {
                $lot = Lot::where('external_id', $sourceId)->first();
            }

            if ($lot && isset($data['status'])) {
                // Mapear estado de Logicware a estado local
                $statusMap = [
                    'Disponible' => 'disponible',
                    'Reservado' => 'reservado',
                    'Vendido' => 'vendido',
                    'Bloqueado' => 'no_disponible'
                ];

                $newStatus = $statusMap[$data['status']] ?? $lot->status;
                
                $lot->update(['status' => $newStatus]);

                return [
                    'action' => 'unit_updated',
                    'lot_id' => $lot->id,
                    'unit_code' => $lot->number,
                    'status' => $newStatus,
                    'message' => 'Estado del lote actualizado'
                ];
            }

            return [
                'action' => 'unit_updated',
                'message' => 'Lote no encontrado o sin cambios de estado',
                'sourceId' => $sourceId
            ];

        } catch (\Exception $e) {
            Log::error('❌ Error procesando actualización de lote', [
                'sourceId' => $sourceId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Lote/unidad creado - Importar nuevo lote
     */
    protected function handleUnitCreated(array $data, $sourceId): array
    {
        Log::info('🆕 Procesando nuevo lote creado', ['sourceId' => $sourceId]);

        // Por ahora solo registrar, la creación de lotes se hace via importación masiva
        return [
            'action' => 'unit_created',
            'message' => 'Nuevo lote detectado, usar importación de inventario',
            'sourceId' => $sourceId
        ];
    }

    /**
     * Proforma creada - Registrar actividad
     */
    protected function handleProformaCreated(array $data, $sourceId): array
    {
        Log::info('📋 Procesando proforma creada', ['sourceId' => $sourceId]);

        return [
            'action' => 'proforma_created',
            'message' => 'Proforma registrada, esperando separación o venta',
            'sourceId' => $sourceId,
            'correlative' => $data['ord_correlative'] ?? null
        ];
    }

    /**
     * Evento desconocido - Solo registrar
     */
    protected function handleUnknownEvent(string $eventType, array $data, $sourceId): array
    {
        Log::info('❓ Evento desconocido recibido', [
            'eventType' => $eventType,
            'sourceId' => $sourceId
        ]);

        return [
            'action' => 'unknown_event',
            'event_type' => $eventType,
            'message' => 'Evento registrado pero no procesado',
            'sourceId' => $sourceId
        ];
    }

    /**
     * Buscar venta específica en los datos de Logicware
     */
    private function findSaleBySourceId(array $salesData, $sourceId)
    {
        foreach ($salesData as $clientData) {
            if (isset($clientData['documents']) && is_array($clientData['documents'])) {
                foreach ($clientData['documents'] as $document) {
                    if (isset($document['proformaId']) && $document['proformaId'] == $sourceId) {
                        return [
                            'client' => $clientData,
                            'document' => $document
                        ];
                    }
                }
            }
        }
        return null;
    }
}
