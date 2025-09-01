<?php

namespace Modules\Collections\Services;

use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Facades\Log;
use Modules\Collections\Models\AccountReceivable;
use Modules\Collections\Models\CustomerPayment;
use Modules\Collections\Repositories\CollectionRepository;

class CollectionService
{
    protected $repository;
    protected $accountingService;

    public function __construct(CollectionRepository $repository, AccountingService $accountingService)
    {
        $this->repository = $repository;
        $this->accountingService = $accountingService;
    }

    /**
     * Registrar un pago
     */
    public function recordPayment(AccountReceivable $accountReceivable, array $paymentData): CustomerPayment
    {
        return DB::transaction(function () use ($accountReceivable, $paymentData) {
            try {
                // Validar que se pueda recibir el pago
                if (!$accountReceivable->canReceivePayment()) {
                    throw new Exception('Esta cuenta por cobrar no puede recibir pagos');
                }

                // Validar que el monto no exceda el saldo pendiente
                if ($paymentData['amount'] > $accountReceivable->outstanding_amount) {
                    throw new Exception('El monto del pago excede el saldo pendiente');
                }

                // Crear el registro del pago
                $payment = $this->repository->createPayment([
                    'client_id' => $accountReceivable->client_id,
                    'ar_id' => $accountReceivable->ar_id,
                    'payment_date' => $paymentData['payment_date'],
                    'amount' => $paymentData['amount'],
                    'currency' => $accountReceivable->currency,
                    'payment_method' => $paymentData['payment_method'],
                    'reference_number' => $paymentData['reference_number'] ?? null,
                    'notes' => $paymentData['notes'] ?? null,
                    'processed_by' => auth()->id()
                ]);

                // Crear asiento contable
                $journalEntry = $this->accountingService->createPaymentJournalEntry($payment);
                $payment->update(['journal_entry_id' => $journalEntry->journal_entry_id]);

                // Actualizar el saldo de la cuenta por cobrar
                $newOutstandingAmount = $accountReceivable->outstanding_amount - $paymentData['amount'];
                $accountReceivable->update(['outstanding_amount' => $newOutstandingAmount]);

                // Actualizar el estado de la cuenta
                $accountReceivable->updateStatus();

                Log::info('Pago registrado exitosamente', [
                    'payment_id' => $payment->payment_id,
                    'ar_id' => $accountReceivable->ar_id,
                    'amount' => $paymentData['amount']
                ]);

                return $payment;
            } catch (Exception $e) {
                Log::error('Error al registrar pago', [
                    'ar_id' => $accountReceivable->ar_id,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        });
    }

    /**
     * Crear nueva cuenta por cobrar
     */
    public function createAccountReceivable(array $data): AccountReceivable
    {
        return DB::transaction(function () use ($data) {
            // Establecer valores por defecto
            $data['outstanding_amount'] = $data['original_amount'];
            $data['status'] = AccountReceivable::STATUS_PENDING;

            $accountReceivable = $this->repository->createAccountReceivable($data);

            // Crear asiento contable inicial si es necesario
            if (isset($data['create_journal_entry']) && $data['create_journal_entry']) {
                $this->accountingService->createAccountReceivableJournalEntry($accountReceivable);
            }

            return $accountReceivable;
        });
    }

    /**
     * Asignar cobrador a cuenta por cobrar
     */
    public function assignCollector(int $arId, int $collectorId): bool
    {
        $accountReceivable = $this->repository->findAccountReceivable($arId);

        if (!$accountReceivable) {
            throw new Exception('Cuenta por cobrar no encontrada');
        }

        return $this->repository->updateAccountReceivable($arId, [
            'assigned_collector_id' => $collectorId
        ]);
    }

    /**
     * Obtener reporte de antigüedad
     */
    public function getAgingReport(?int $clientId = null): array
    {
        return $this->repository->getAgingReport($clientId);
    }

    /**
     * Obtener estadísticas de cobranza
     */
    public function getCollectionStats(string $startDate, string $endDate): array
    {
        return $this->repository->getCollectionStats($startDate, $endDate);
    }

    /**
     * Generar alertas de cobranza
     */
    public function generateCollectionAlerts(): array
    {
        $alerts = [];

        // Cuentas vencidas
        $overdueAccounts = $this->repository->getAccountsReceivable(['overdue_only' => true], 100);

        foreach ($overdueAccounts as $account) {
            $alerts[] = [
                'type' => 'overdue',
                'severity' => $account->aging_days > 60 ? 'high' : 'medium',
                'message' => "Cuenta {$account->ar_number} vencida hace {$account->aging_days} días",
                'account_id' => $account->ar_id,
                'client_name' => $account->client->name,
                'amount' => $account->outstanding_amount
            ];
        }

        return $alerts;
    }

    /**
     * Cancelar cuenta por cobrar
     */
    public function cancelAccountReceivable(int $arId, string $reason): bool
    {
        return DB::transaction(function () use ($arId, $reason) {
            $accountReceivable = $this->repository->findAccountReceivable($arId);

            if (!$accountReceivable) {
                throw new Exception('Cuenta por cobrar no encontrada');
            }

            if ($accountReceivable->payments()->exists()) {
                throw new Exception('No se puede cancelar una cuenta con pagos aplicados');
            }

            return $this->repository->updateAccountReceivable($arId, [
                'status' => AccountReceivable::STATUS_CANCELLED,
                'notes' => ($accountReceivable->notes ?? '') . "\nCancelada: " . $reason
            ]);
        });
    }
}
