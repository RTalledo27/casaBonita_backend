<?php

namespace Modules\HumanResources\Services;

use App\Models\CommissionPaymentVerification;
use App\Models\PaymentEvent;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Collections\Events\InstallmentPaidEvent;
use Modules\Collections\Models\CustomerPayment;
use Modules\Collections\Models\AccountReceivable;
use Modules\HumanResources\Models\Commission;

class CommissionVerificationService
{
    protected CommissionPaymentVerificationService $verificationService;

    public function __construct(CommissionPaymentVerificationService $verificationService)
    {
        $this->verificationService = $verificationService;
    }

    /**
     * Procesa la verificación de comisiones basada en eventos de pago
     */
    public function processCommissionVerification(InstallmentPaidEvent $event): array
    {
        try {
            DB::beginTransaction();

            $results = [
                'processed_commissions' => [],
                'errors' => [],
                'total_processed' => 0,
                'total_verified' => 0
            ];

            // Obtener el pago del evento
            $payment = CustomerPayment::find($event->paymentId);
            if (!$payment) {
                throw new Exception("Pago no encontrado: {$event->paymentId}");
            }

            // Obtener la cuenta por cobrar
            $accountReceivable = AccountReceivable::find($payment->ar_id);
            if (!$accountReceivable) {
                throw new Exception("Cuenta por cobrar no encontrada: {$payment->ar_id}");
            }

            // Buscar comisiones relacionadas con el contrato
            $commissions = Commission::where('contract_id', $accountReceivable->contract_id)
                ->where('payment_dependency_type', '!=', 'none')
                ->where('payment_status', '!=', 'pagado')
                ->get();

            Log::info('Procesando verificación de comisiones', [
                'payment_id' => $payment->payment_id,
                'contract_id' => $accountReceivable->contract_id,
                'installment_type' => $event->installmentType,
                'commissions_found' => $commissions->count()
            ]);

            foreach ($commissions as $commission) {
                try {
                    $result = $this->processCommissionForPayment($commission, $payment, $event);
                    $results['processed_commissions'][] = $result;
                    $results['total_processed']++;

                    if ($result['verification_updated']) {
                        $results['total_verified']++;
                    }

                } catch (Exception $e) {
                    $error = [
                        'commission_id' => $commission->commission_id,
                        'error' => $e->getMessage()
                    ];
                    $results['errors'][] = $error;

                    Log::error('Error procesando comisión individual', $error);
                }
            }

            DB::commit();

            Log::info('Verificación de comisiones completada', [
                'payment_id' => $payment->payment_id,
                'total_processed' => $results['total_processed'],
                'total_verified' => $results['total_verified'],
                'errors' => count($results['errors'])
            ]);

            return $results;

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Error en procesamiento de verificación de comisiones', [
                'payment_id' => $event->paymentId ?? null,
                'installment_type' => $event->installmentType ?? null,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Procesa una comisión específica para un pago
     */
    protected function processCommissionForPayment(
        Commission $commission,
        CustomerPayment $payment,
        InstallmentPaidEvent $event
    ): array {
        $result = [
            'commission_id' => $commission->commission_id,
            'installment_type' => $event->installmentType,
            'verification_updated' => false,
            'verification_status' => null,
            'notes' => []
        ];

        // Determinar si este pago afecta esta comisión
        $affectsCommission = $this->doesPaymentAffectCommission($commission, $event);

        if (!$affectsCommission) {
            $result['notes'][] = 'El pago no afecta esta comisión según su configuración';
            return $result;
        }

        // Crear o actualizar verificación de pago
        $verification = $this->createOrUpdatePaymentVerification(
            $commission,
            $payment,
            $event
        );

        if ($verification) {
            $result['verification_updated'] = true;
            $result['verification_status'] = $verification->verification_status;
            $result['notes'][] = 'Verificación de pago creada/actualizada';

            // Actualizar contadores de la comisión
            $this->updateCommissionPaymentCounters($commission, $event->installmentType);

            // Verificar si la comisión está completamente verificada
            $this->checkAndUpdateCommissionStatus($commission);

            $result['notes'][] = 'Estado de comisión actualizado';
        }

        return $result;
    }

    /**
     * Determina si un pago afecta una comisión específica
     * Considera el payment_part para comisiones divididas
     */
    protected function doesPaymentAffectCommission(Commission $commission, InstallmentPaidEvent $event): bool
    {
        // Si no requiere verificación de pagos, no afecta
        if ($commission->payment_dependency_type === 'none') {
            return false;
        }

        // Para comisiones divididas, solo considerar el pago correspondiente al payment_part
        if ($commission->payment_part) {
            if ($commission->payment_part == 1) {
                // payment_part = 1 solo se afecta por el primer pago del cliente
                return $event->installmentType === 'first';
            } elseif ($commission->payment_part == 2) {
                // payment_part = 2 solo se afecta por el segundo pago del cliente
                return $event->installmentType === 'second';
            }
        }

        // Para comisiones no divididas, verificar según el tipo de dependencia
        switch ($commission->payment_dependency_type) {
            case 'first_payment_only':
                return $event->installmentType === 'first';

            case 'second_payment_only':
                return $event->installmentType === 'second';

            case 'both_payments':
                return in_array($event->installmentType, ['first', 'second']);

            case 'any_payment':
                return true;

            default:
                return false;
        }
    }

    /**
     * Crea o actualiza una verificación de pago
     */
    protected function createOrUpdatePaymentVerification(
        Commission $commission,
        CustomerPayment $payment,
        InstallmentPaidEvent $event
    ): ?CommissionPaymentVerification {
        try {
            // Obtener la cuenta por cobrar
            $accountReceivable = AccountReceivable::find($payment->ar_id);
            if (!$accountReceivable) {
                return null;
            }

            // Crear o actualizar la verificación
            $verification = CommissionPaymentVerification::updateOrCreate(
                [
                    'commission_id' => $commission->commission_id,
                    'client_payment_id' => $payment->payment_id,
                    'payment_installment' => $event->installmentType
                ],
                [
                    'account_receivable_id' => $accountReceivable->ar_id,
                    'verification_date' => now(),
                    'verified_amount' => $payment->amount,
                    'verification_status' => 'verified',
                    'verification_method' => 'automatic_event',
                    'verified_by' => null, // Verificación automática
                    'event_id' => $event->eventData['event_id'] ?? null,
                    'notes' => $this->generateVerificationNotes($payment, $event)
                ]
            );

            Log::info('Verificación de pago creada/actualizada', [
                'verification_id' => $verification->id,
                'commission_id' => $commission->commission_id,
                'payment_id' => $payment->payment_id,
                'installment_type' => $event->installmentType
            ]);

            return $verification;

        } catch (Exception $e) {
            Log::error('Error creando verificación de pago', [
                'commission_id' => $commission->commission_id,
                'payment_id' => $payment->payment_id,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Actualiza los contadores de pagos de la comisión
     */
    protected function updateCommissionPaymentCounters(Commission $commission, string $installmentType): void
    {
        $updates = [];

        // Incrementar contador según el tipo de cuota
        if ($installmentType === 'first') {
            $updates['client_payments_verified'] = DB::raw('client_payments_verified + 1');
        } elseif ($installmentType === 'second') {
            $updates['client_payments_verified'] = DB::raw('client_payments_verified + 1');
        }

        // Actualizar fecha de última verificación
        $updates['last_payment_event_id'] = null; // Se puede obtener del evento si es necesario
        $updates['next_verification_date'] = $this->calculateNextVerificationDate($commission);

        if (!empty($updates)) {
            $commission->update($updates);
        }
    }

    /**
     * Verifica y actualiza el estado general de la comisión
     */
    protected function checkAndUpdateCommissionStatus(Commission $commission): void
    {
        // Obtener todas las verificaciones de esta comisión
        $verifications = CommissionPaymentVerification::where('commission_id', $commission->commission_id)
            ->where('verification_status', 'verified')
            ->get();

        $hasFirstPayment = $verifications->where('payment_installment', 'first')->isNotEmpty();
        $hasSecondPayment = $verifications->where('payment_installment', 'second')->isNotEmpty();

        // Determinar nuevo estado
        $newStatus = $this->determineCommissionStatus($commission, $hasFirstPayment, $hasSecondPayment);

        // Actualizar estado si cambió
        if ($commission->payment_verification_status !== $newStatus) {
            $commission->update([
                'payment_verification_status' => $newStatus,
                'verification_notes' => $this->generateCommissionStatusNotes(
                    $hasFirstPayment,
                    $hasSecondPayment,
                    $newStatus
                )
            ]);

            Log::info('Estado de comisión actualizado', [
                'commission_id' => $commission->commission_id,
                'old_status' => $commission->payment_verification_status,
                'new_status' => $newStatus
            ]);
        }
    }

    /**
     * Determina el estado de verificación de una comisión
     * Considera el payment_part para comisiones divididas
     */
    protected function determineCommissionStatus(
        Commission $commission,
        bool $hasFirstPayment,
        bool $hasSecondPayment
    ): string {
        // Para comisiones divididas, solo considerar el pago correspondiente
        if ($commission->payment_part) {
            if ($commission->payment_part == 1) {
                // payment_part = 1 solo necesita el primer pago
                return $hasFirstPayment ? 'verified' : 'pending';
            } elseif ($commission->payment_part == 2) {
                // payment_part = 2 solo necesita el segundo pago
                return $hasSecondPayment ? 'verified' : 'pending';
            }
        }

        // Para comisiones no divididas, verificar según el tipo de dependencia
        switch ($commission->payment_dependency_type) {
            case 'first_payment_only':
                return $hasFirstPayment ? 'verified' : 'pending';

            case 'second_payment_only':
                return $hasSecondPayment ? 'verified' : 'pending';

            case 'both_payments':
                if ($hasFirstPayment && $hasSecondPayment) {
                    return 'verified';
                } elseif ($hasFirstPayment || $hasSecondPayment) {
                    return 'partially_verified';
                } else {
                    return 'pending';
                }

            case 'any_payment':
                return ($hasFirstPayment || $hasSecondPayment) ? 'verified' : 'pending';

            default:
                return 'pending';
        }
    }

    /**
     * Calcula la próxima fecha de verificación
     */
    protected function calculateNextVerificationDate(Commission $commission): ?Carbon
    {
        // Si ya está completamente verificada, no necesita próxima verificación
        if ($commission->payment_verification_status === 'verified') {
            return null;
        }

        // Calcular basado en la configuración de la comisión
        return now()->addDays(7); // Por defecto, verificar en una semana
    }

    /**
     * Genera notas para la verificación de pago
     */
    protected function generateVerificationNotes(CustomerPayment $payment, InstallmentPaidEvent $event): string
    {
        $notes = [
            "Verificación automática por evento de pago",
            "Tipo de cuota: {$event->installmentType}",
            "Monto: {$payment->amount} {$payment->currency}",
            "Fecha de pago: {$payment->payment_date}",
            "Método: {$payment->payment_method}",
            "Procesado: " . now()->format('d/m/Y H:i:s')
        ];

        return implode(' | ', $notes);
    }

    /**
     * Genera notas para el estado de la comisión
     */
    protected function generateCommissionStatusNotes(
        bool $hasFirstPayment,
        bool $hasSecondPayment,
        string $status
    ): string {
        $notes = [];

        if ($hasFirstPayment) {
            $notes[] = 'Primera cuota verificada';
        }

        if ($hasSecondPayment) {
            $notes[] = 'Segunda cuota verificada';
        }

        $notes[] = "Estado: {$status}";
        $notes[] = 'Actualizado: ' . now()->format('d/m/Y H:i:s');

        return implode(' | ', $notes);
    }

    /**
     * Obtiene estadísticas de verificación
     */
    public function getVerificationStats(array $filters = []): array
    {
        $query = Commission::query();

        // Aplicar filtros
        if (isset($filters['date_from']) && isset($filters['date_to'])) {
            $query->whereBetween('created_at', [$filters['date_from'], $filters['date_to']]);
        }

        if (isset($filters['payment_dependency_type'])) {
            $query->where('payment_dependency_type', $filters['payment_dependency_type']);
        }

        // Obtener estadísticas
        $total = $query->count();
        $verified = $query->where('payment_verification_status', 'verified')->count();
        $partiallyVerified = $query->where('payment_verification_status', 'partially_verified')->count();
        $pending = $query->where('payment_verification_status', 'pending')->count();

        return [
            'total_commissions' => $total,
            'verified' => $verified,
            'partially_verified' => $partiallyVerified,
            'pending' => $pending,
            'verification_rate' => $total > 0 ? round(($verified / $total) * 100, 2) : 0
        ];
    }
}