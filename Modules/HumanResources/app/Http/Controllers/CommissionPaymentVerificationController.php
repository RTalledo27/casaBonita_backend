<?php

namespace Modules\HumanResources\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CommissionPaymentVerification;
use Dotenv\Exception\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Collections\Models\CustomerPayment;
use Modules\HumanResources\Models\Commission;
use Modules\HumanResources\Services\CommissionPaymentVerificationService;
use Pusher\Pusher;

class CommissionPaymentVerificationController extends Controller
{
    protected $verificationService;

    public function __construct(CommissionPaymentVerificationService $verificationService)
    {
        $this->verificationService = $verificationService;
        
        // Middleware de autenticación
        $this->middleware('auth:sanctum');
        
        // Middleware de permisos
        $this->middleware('permission:hr.commission-verifications.view')->only(['index', 'getVerificationStatus', 'getCommissionsRequiringVerification', 'getVerificationStats']);
        $this->middleware('permission:hr.commission-verifications.verify')->only(['verifyPayment']);
        $this->middleware('permission:hr.commission-verifications.process')->only(['processAutomaticVerifications']);
        $this->middleware('permission:hr.commission-verifications.reverse')->only(['reverseVerification']);
    }

    /**
     * Helper para crear instancia de Pusher
     */
    private function pusherInstance(): Pusher
    {
        return new Pusher(
            config('broadcasting.connections.pusher.key'),
            config('broadcasting.connections.pusher.secret'),
            config('broadcasting.connections.pusher.app_id'),
            [
                'cluster' => config('broadcasting.connections.pusher.options.cluster'),
                'encrypted' => true
            ]
        );
    }

    /**
     * Obtener todas las verificaciones de pago para una comisión
     */
    public function index(Request $request, $commissionId): JsonResponse
    {
        try {
            $commission = Commission::findOrFail($commissionId);
            
            $verifications = CommissionPaymentVerification::where('commission_id', $commissionId)
                ->with(['customerPayment', 'verifiedBy'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $verifications,
                'commission' => $commission
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las verificaciones: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verificar manualmente un pago de cliente para una comisión
     */
    public function verifyPayment(Request $request): JsonResponse
    {
        $request->validate([
            'commission_id' => 'required|exists:commissions,commission_id',
            'customer_payment_id' => 'required|exists:customer_payments,id',
            'payment_installment' => 'required|in:first,second',
            'verification_notes' => 'nullable|string|max:1000'
        ]);

        try {
            DB::beginTransaction();

            $commission = Commission::findOrFail($request->commission_id);
            $customerPayment = CustomerPayment::findOrFail($request->customer_payment_id);

            $verification = $this->verificationService->verifyInstallmentPayment(
                $commission,
                $customerPayment,
                $request->payment_installment,
                Auth::id(),
                $request->verification_notes
            );

            DB::commit();

            // Enviar notificación Pusher
            try {
                $pusher = $this->pusherInstance();
                $pusher->trigger('commission-verifications', 'verification.processed', [
                    'commission_id' => $commission->commission_id,
                    'employee_name' => $commission->employee->user->name ?? 'N/A',
                    'verification_status' => $commission->payment_verification_status,
                    'payment_installment' => $request->payment_installment,
                    'verified_by' => Auth::user()->name,
                    'message' => 'Verificación procesada exitosamente'
                ]);
            } catch (\Exception $e) {
                \Log::error('Error enviando notificación Pusher:', ['error' => $e->getMessage()]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Pago verificado exitosamente',
                'data' => $verification->load(['commission', 'customerPayment', 'verifiedBy'])
            ]);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al verificar el pago: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Procesar verificaciones automáticas para un período específico
     */
    public function processAutomaticVerifications(Request $request): JsonResponse
    {
        $request->validate([
            'commission_period' => 'required|string|regex:/^\d{4}-\d{2}$/',
            'force_reprocess' => 'boolean'
        ]);

        try {
            DB::beginTransaction();

            $results = $this->verificationService->processBatchVerifications(
                $request->commission_period,
                $request->boolean('force_reprocess', false)
            );

            DB::commit();

            // Enviar notificación Pusher para verificaciones automáticas
            try {
                $pusher = $this->pusherInstance();
                $pusher->trigger('commission-verifications', 'verification.automatic.completed', [
                    'commission_period' => $request->commission_period,
                    'processed_count' => $results['processed_count'] ?? 0,
                    'successful_count' => $results['successful_count'] ?? 0,
                    'failed_count' => $results['failed_count'] ?? 0,
                    'message' => 'Verificaciones automáticas completadas'
                ]);
            } catch (\Exception $e) {
                \Log::error('Error enviando notificación Pusher:', ['error' => $e->getMessage()]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Verificaciones automáticas procesadas exitosamente',
                'data' => $results
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar verificaciones automáticas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Revertir una verificación de pago
     */
    public function reverseVerification(Request $request, $verificationId): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        try {
            DB::beginTransaction();

            $verification = CommissionPaymentVerification::findOrFail($verificationId);
            
            $result = $this->verificationService->reversePaymentVerification(
                $verification,
                Auth::id(),
                $request->reason
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Verificación revertida exitosamente',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al revertir la verificación: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener el estado de verificación de una comisión
     */
    public function getVerificationStatus($commissionId): JsonResponse
    {
        try {
            $commission = Commission::with([
                'paymentVerifications' => function($query) {
                    $query->orderBy('created_at', 'desc');
                },
                'paymentVerifications.customerPayment',
                'paymentVerifications.verifiedBy'
            ])->findOrFail($commissionId);

            $status = [
                'commission_id' => $commission->commission_id,
                'requires_verification' => $commission->requires_client_payment_verification,
                'verification_status' => $commission->payment_verification_status,
                'is_eligible_for_payment' => $commission->is_eligible_for_payment,
                'first_payment_verified_at' => $commission->first_payment_verified_at,
                'second_payment_verified_at' => $commission->second_payment_verified_at,
                'verification_notes' => $commission->verification_notes,
                'verifications' => $commission->paymentVerifications
            ];

            return response()->json([
                'success' => true,
                'data' => $status
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el estado de verificación: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener comisiones que requieren verificación
     */
    public function getCommissionsRequiringVerification(Request $request): JsonResponse
    {
        try {
            $query = Commission::requiresVerification()
                ->with(['employee.user', 'contract', 'paymentVerifications'])
                ->orderBy('created_at', 'desc');

            // Filtrar por período si se proporciona
            if ($request->has('commission_period')) {
                $query->where('commission_period', $request->commission_period);
            }

            // Filtrar por estado de verificación
            if ($request->has('verification_status')) {
                $query->byVerificationStatus($request->verification_status);
            }

            // Filtrar por elegibilidad de pago
            if ($request->has('eligible_for_payment')) {
                if ($request->boolean('eligible_for_payment')) {
                    $query->eligibleForPayment();
                } else {
                    $query->where('is_eligible_for_payment', false);
                }
            }

            $commissions = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $commissions
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las comisiones: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de verificaciones
     */
    public function getVerificationStats(Request $request): JsonResponse
    {
        try {
            $commissionPeriod = $request->get('commission_period');
            
            // Crear consulta base - SOLO para comisiones que requieren verificación
            $baseQuery = Commission::requiresVerification();
            if ($commissionPeriod) {
                $baseQuery->where('commission_period', $commissionPeriod);
            }

            // Calcular contadores usando consultas separadas
            $totalCommissions = (clone $baseQuery)->count();
            $requiresVerification = $totalCommissions; // Todas las comisiones en baseQuery requieren verificación
            $totalPending = (clone $baseQuery)->byVerificationStatus('pending_verification')->count();
            $firstPaymentVerified = (clone $baseQuery)->byVerificationStatus('first_payment_verified')->count();
            $secondPaymentVerified = (clone $baseQuery)->byVerificationStatus('second_payment_verified')->count();
            $totalVerified = (clone $baseQuery)->byVerificationStatus('fully_verified')->count();
            $totalFailed = (clone $baseQuery)->byVerificationStatus('verification_failed')->count();
            $eligibleForPayment = (clone $baseQuery)->eligibleForPayment()->count();
            $notEligibleForPayment = (clone $baseQuery)->where('is_eligible_for_payment', false)->count();
            
            // Calcular montos usando la columna correcta 'commission_amount'
            $pendingAmount = (clone $baseQuery)->byVerificationStatus('pending_verification')->sum('commission_amount') ?? 0;
            $verifiedAmount = (clone $baseQuery)->byVerificationStatus('fully_verified')->sum('commission_amount') ?? 0;

            $stats = [
                'total_commissions' => $totalCommissions,
                'requiring_verification' => $requiresVerification,
                'total_pending' => $totalPending,
                'pending_verification' => $totalPending,
                'first_payment_verified' => $firstPaymentVerified,
                'second_payment_verified' => $secondPaymentVerified,
                'total_verified' => $totalVerified,
                'fully_verified' => $totalVerified,
                'total_failed' => $totalFailed,
                'verification_failed' => $totalFailed,
                'eligible_for_payment' => $eligibleForPayment,
                'not_eligible_for_payment' => $notEligibleForPayment,
                'pending_amount' => (float) $pendingAmount,
                'verified_amount' => (float) $verifiedAmount
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las estadísticas: ' . $e->getMessage()
            ], 500);
        }
    }
}