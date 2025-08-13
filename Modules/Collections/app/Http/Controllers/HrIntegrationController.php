<?php

namespace Modules\Collections\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\Collections\Models\CustomerPayment;
use Modules\HumanResources\Models\Employee;
use Modules\HumanResources\Models\Commission;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HrIntegrationController extends Controller
{
    /**
     * Get HR integration statistics
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = [
                'total_employees_with_commissions' => Employee::whereHas('commissions')->count(),
                'pending_verifications' => Commission::where('verification_status', 'pending')->count(),
                'verified_commissions' => Commission::where('verification_status', 'verified')->count(),
                'failed_verifications' => Commission::where('verification_status', 'failed')->count(),
                'total_commission_amount' => Commission::where('verification_status', 'verified')->sum('commission_amount'),
                'last_sync_date' => Commission::latest('updated_at')->first()?->updated_at,
                'employees_eligible_for_payment' => Employee::whereHas('commissions', function($query) {
                    $query->where('verification_status', 'verified')
                          ->where('payment_status', 'pending');
                })->count()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas de integración HR',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Synchronize HR data with Collections
     */
    public function sync(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Sincronizar comisiones con pagos de clientes
            $syncedCount = 0;
            
            // Obtener comisiones pendientes con logging
            $commissions = Commission::where('verification_status', 'pending')
                                   ->with(['employee'])
                                   ->get();

            foreach ($commissions as $commission) {
                try {
                    // Verificar que los campos necesarios no sean null
                    if (is_null($commission->customer_id) || is_null($commission->period_start) || is_null($commission->period_end)) {
                        continue; // Saltar esta comisión si faltan datos
                    }
                    
                    // Buscar pagos relacionados del cliente con validación
                    $customerPayments = CustomerPayment::where('client_id', $commission->customer_id)
                                                      ->whereNotNull('payment_date')
                                                      ->where('payment_date', '>=', $commission->period_start)
                                                      ->where('payment_date', '<=', $commission->period_end)
                                                      ->get();

                    if ($customerPayments->isNotEmpty()) {
                        $commission->update([
                            'verification_status' => 'verified',
                            'verified_at' => now(),
                            'verified_amount' => $customerPayments->sum('amount')
                        ]);
                        $syncedCount++;
                    }
                } catch (\Exception $innerE) {
                    // Log el error específico pero continúa con la siguiente comisión
                    \Log::error('Error procesando comisión ID: ' . $commission->commission_id . ' - ' . $innerE->getMessage());
                    continue;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Sincronización completada. {$syncedCount} comisiones verificadas.",
                'data' => [
                    'synced_commissions' => $syncedCount,
                    'sync_date' => now()
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error durante la sincronización',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process employees eligible for commission payment
     */
    public function processEligible(Request $request): JsonResponse
    {
        try {
            $eligibleEmployees = Employee::whereHas('commissions', function($query) {
                $query->where('verification_status', 'verified')
                      ->where('payment_status', 'pending');
            })->with(['commissions' => function($query) {
                $query->where('verification_status', 'verified')
                      ->where('payment_status', 'pending');
            }])->get();

            $processedData = $eligibleEmployees->map(function($employee) {
                $totalCommission = $employee->commissions->sum('commission_amount');
                return [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->full_name,
                    'employee_code' => $employee->employee_code,
                    'total_commission' => $totalCommission,
                    'commission_count' => $employee->commissions->count(),
                    'period_start' => $employee->commissions->min('period_start'),
                    'period_end' => $employee->commissions->max('period_end')
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $processedData,
                'total_employees' => $processedData->count(),
                'total_amount' => $processedData->sum('total_commission')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar empleados elegibles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark employees as eligible for payment
     */
    public function markEligible(Request $request): JsonResponse
    {
        $request->validate([
            'employee_ids' => 'required|array',
            'employee_ids.*' => 'exists:employees,id'
        ]);

        try {
            DB::beginTransaction();

            $markedCount = 0;
            foreach ($request->employee_ids as $employeeId) {
                $updated = Commission::where('employee_id', $employeeId)
                                   ->where('verification_status', 'verified')
                                   ->where('payment_status', 'pending')
                                   ->update([
                                       'payment_status' => 'eligible',
                                       'eligible_date' => now()->toDateTimeString()
                                   ]);
                $markedCount += $updated;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Se marcaron {$markedCount} comisiones como elegibles para pago.",
                'data' => [
                    'marked_commissions' => $markedCount,
                    'processed_employees' => count($request->employee_ids)
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al marcar empleados como elegibles',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}