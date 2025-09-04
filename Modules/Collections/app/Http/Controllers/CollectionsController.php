<?php

namespace Modules\Collections\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\Sales\Models\Contract;
use Modules\Sales\Models\PaymentSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CollectionsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        try {
            // Obtener métricas del dashboard
            $totalContracts = Contract::count();
            $activeContracts = Contract::where('status', 'active')->count();
            
            // Cronogramas de pago
            $totalSchedules = PaymentSchedule::count();
            $paidSchedules = PaymentSchedule::where('status', 'pagado')->count();
            $pendingSchedules = PaymentSchedule::where('status', 'pendiente')->count();
            $overdueSchedules = PaymentSchedule::where('status', 'pendiente')
                ->where('due_date', '<', Carbon::now())
                ->count();
            
            // Montos
            $totalAmount = PaymentSchedule::sum('amount');
            $paidAmount = PaymentSchedule::where('status', 'pagado')->sum('amount');
            $pendingAmount = PaymentSchedule::where('status', 'pendiente')->sum('amount');
            $overdueAmount = PaymentSchedule::where('status', 'pendiente')
                ->where('due_date', '<', Carbon::now())
                ->sum('amount');
            
            // Cronogramas recientes
            $recentSchedules = PaymentSchedule::with(['contract.reservation.client', 'contract.reservation.lot', 'payments'])
                ->orderBy('schedule_id', 'desc')
                ->limit(10)
                ->get();
            
            // Próximos vencimientos
            $upcomingSchedules = PaymentSchedule::with(['contract.reservation.client', 'contract.reservation.lot', 'payments'])
                ->where('status', 'pendiente')
                ->where('due_date', '>=', Carbon::now())
                ->where('due_date', '<=', Carbon::now()->addDays(30))
                ->orderBy('due_date', 'asc')
                ->limit(10)
                ->get();
            
            $dashboardData = [
                'metrics' => [
                    'total_contracts' => $totalContracts,
                    'active_contracts' => $activeContracts,
                    'total_schedules' => $totalSchedules,
                    'paid_schedules' => $paidSchedules,
                    'pending_schedules' => $pendingSchedules,
                    'overdue_schedules' => $overdueSchedules,
                    'total_amount' => $totalAmount,
                    'paid_amount' => $paidAmount,
                    'pending_amount' => $pendingAmount,
                    'overdue_amount' => $overdueAmount,
                ],
                'recent_schedules' => $recentSchedules,
                'upcoming_schedules' => $upcomingSchedules,
            ];
            
            return response()->json([
                'success' => true,
                'message' => 'Dashboard data retrieved successfully',
                'data' => $dashboardData
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving dashboard data: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Create method not implemented'
        ], 501);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Store method not implemented'
        ], 501);
    }

    /**
     * Genera cronograma de pagos para un contrato específico
     */
    public function generateSchedule(Request $request, $contract_id): JsonResponse
    {
        try {
            $request->validate([
                'start_date' => 'nullable|date|after_or_equal:today'
            ]);

            $contract = Contract::with(['reservation.client', 'reservation.lot'])->findOrFail($contract_id);
            
            // Verificar si ya tiene cronograma
            $existingSchedules = PaymentSchedule::where('contract_id', $contract_id)->count();
            if ($existingSchedules > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'El contrato ya tiene un cronograma de pagos generado',
                    'data' => null
                ], 400);
            }

            $scheduleGenerationService = app(\Modules\Collections\Services\PaymentScheduleGenerationService::class);
            
            $options = [
                'start_date' => $request->input('start_date')
            ];

            $result = $scheduleGenerationService->generateScheduleForContract($contract, $options);

            if ($result['success']) {
                // Recargar el contrato con los cronogramas generados
                $contractWithSchedules = Contract::with([
                    'reservation.client',
                    'reservation.lot.manzana',
                    'paymentSchedules' => function($query) {
                        $query->orderBy('due_date', 'asc');
                    }
                ])->find($contract_id);

                return response()->json([
                    'success' => true,
                    'message' => 'Cronograma generado exitosamente',
                    'data' => [
                        'contract' => [
                            'contract_id' => $contractWithSchedules->contract_id,
                            'contract_number' => $contractWithSchedules->contract_number,
                            'client_name' => $contractWithSchedules->reservation->client->full_name ?? 'N/A',
                            'lot_name' => ($contractWithSchedules->reservation->lot->manzana->name ?? 'N/A') . '-' . ($contractWithSchedules->reservation->lot->num_lot ?? 'N/A'),
                            'total_schedules' => $contractWithSchedules->paymentSchedules->count(),
                            'total_amount' => $contractWithSchedules->paymentSchedules->sum('amount')
                        ],
                        'schedules' => $contractWithSchedules->paymentSchedules->map(function($schedule) {
                            return [
                                'schedule_id' => $schedule->schedule_id,
                                'installment_number' => $schedule->installment_number,
                                'due_date' => $schedule->due_date,
                                'amount' => $schedule->amount,
                                'status' => $schedule->status
                            ];
                        })
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Error generando cronograma',
                    'data' => null
                ], 500);
            }

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Contrato no encontrado',
                'data' => null
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generando cronograma: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Genera cronogramas de pagos masivamente para contratos activos
     */
    public function generateBulkSchedules(Request $request): JsonResponse
    {
        try {
            $scheduleGenerationService = app(\Modules\Collections\Services\PaymentScheduleGenerationService::class);
            
            $options = [
                'payment_type' => $request->input('payment_type', 'installments'),
                'installments' => $request->input('installments', 24),
                'start_date' => $request->input('start_date')
            ];

            $result = $scheduleGenerationService->generateBulkPaymentSchedules($options);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'processed' => $result['processed'],
                    'errors' => $result['errors'],
                    'results' => $result['results']
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error en generación masiva de cronogramas: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Obtiene estadísticas de generación de cronogramas
     */
    public function getGenerationStats(): JsonResponse
    {
        try {
            $scheduleGenerationService = app(\Modules\Collections\Services\PaymentScheduleGenerationService::class);
            $stats = $scheduleGenerationService->getGenerationStats();

            return response()->json([
                'success' => true,
                'message' => 'Estadísticas obtenidas exitosamente',
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo estadísticas: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Obtiene cronogramas de un contrato específico
     */
    public function getContractSchedules(Request $request, $contract_id): JsonResponse
    {
        try {
            $contract = Contract::with([
                'reservation.client',
                'reservation.lot.manzana',
                'paymentSchedules' => function($query) use ($request) {
                    $query->orderBy('due_date', 'asc');
                    
                    // Filtros opcionales
                    if ($request->has('status')) {
                        $query->where('status', $request->input('status'));
                    }
                    
                    if ($request->has('overdue_only') && $request->boolean('overdue_only')) {
                        $query->where('due_date', '<', now())
                              ->where('status', '!=', 'paid');
                    }
                }
            ])->findOrFail($contract_id);

            return response()->json([
                'success' => true,
                'message' => 'Cronogramas obtenidos exitosamente',
                'data' => [
                    'contract' => [
                        'contract_id' => $contract->contract_id,
                        'contract_number' => $contract->contract_number,
                        'client_name' => $contract->reservation->client->full_name ?? 'N/A',
                        'lot_name' => ($contract->reservation->lot->manzana->name ?? 'N/A') . '-' . ($contract->reservation->lot->num_lot ?? 'N/A'),
                        'total_schedules' => $contract->paymentSchedules->count(),
                        'total_amount' => $contract->paymentSchedules->sum('amount'),
                        'paid_amount' => $contract->paymentSchedules->where('status', 'paid')->sum('amount'),
                        'pending_amount' => $contract->paymentSchedules->where('status', '!=', 'paid')->sum('amount')
                    ],
                    'schedules' => $contract->paymentSchedules->map(function($schedule) {
                        return [
                            'schedule_id' => $schedule->schedule_id,
                            'installment_number' => $schedule->installment_number,
                            'due_date' => $schedule->due_date,
                            'amount' => $schedule->amount,
                            'status' => $schedule->status,
                            'is_overdue' => $schedule->due_date < now() && $schedule->status != 'paid',
                            'days_overdue' => $schedule->due_date < now() && $schedule->status != 'paid' 
                                ? now()->diffInDays($schedule->due_date) : 0
                        ];
                    })
                ]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Contrato no encontrado',
                'data' => null
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo cronogramas: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Actualiza un cronograma específico
     */
    public function updateSchedule(Request $request, $schedule_id): JsonResponse
    {
        try {
            $request->validate([
                'due_date' => 'sometimes|date',
                'amount' => 'sometimes|numeric|min:0',
                'status' => 'sometimes|in:pending,paid,overdue,cancelled',
                'notes' => 'sometimes|string|max:500'
            ]);

            $schedule = PaymentSchedule::findOrFail($schedule_id);
            
            // Solo permitir actualizar cronogramas no pagados
            if ($schedule->status === 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede modificar un cronograma ya pagado',
                    'data' => null
                ], 400);
            }

            $schedule->update($request->only(['due_date', 'amount', 'status', 'notes']));

            return response()->json([
                'success' => true,
                'message' => 'Cronograma actualizado exitosamente',
                'data' => [
                    'schedule_id' => $schedule->schedule_id,
                    'installment_number' => $schedule->installment_number,
                    'due_date' => $schedule->due_date,
                    'amount' => $schedule->amount,
                    'status' => $schedule->status,
                    'notes' => $schedule->notes
                ]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cronograma no encontrado',
                'data' => null
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error actualizando cronograma: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Elimina un cronograma específico
     */
    public function deleteSchedule($schedule_id): JsonResponse
    {
        try {
            $schedule = PaymentSchedule::findOrFail($schedule_id);
            
            // Solo permitir eliminar cronogramas no pagados
            if ($schedule->status === 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar un cronograma ya pagado',
                    'data' => null
                ], 400);
            }

            $schedule->delete();

            return response()->json([
                'success' => true,
                'message' => 'Cronograma eliminado exitosamente',
                'data' => null
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cronograma no encontrado',
                'data' => null
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error eliminando cronograma: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Obtiene contratos agrupados con resumen de cronogramas
     */
    public function getContractsWithSchedulesSummary(Request $request): JsonResponse
    {
        try {
            $query = Contract::with([
                'reservation.client',
                'reservation.lot.manzana',
                'reservation.advisor',
                'client', // Para contratos directos
                'lot.manzana', // Para contratos directos
                'advisor', // Para contratos directos
                'paymentSchedules' => function($q) {
                    $q->orderBy('due_date', 'asc');
                }
            ])->whereHas('paymentSchedules');

            // Aplicar filtros
            if ($request->has('status')) {
                $query->whereHas('paymentSchedules', function($q) use ($request) {
                    $q->where('status', $request->input('status'));
                });
            }

            if ($request->has('client_name')) {
                $query->whereHas('reservation.client', function($q) use ($request) {
                    $q->where('full_name', 'like', '%' . $request->input('client_name') . '%');
                });
            }

            if ($request->has('contract_number')) {
                $query->where('contract_number', 'like', '%' . $request->input('contract_number') . '%');
            }

            // Paginación
            $perPage = $request->input('per_page', 10); // Default 10 items per page
            $perPage = min(max($perPage, 1), 100); // Limit between 1 and 100
            
            $contracts = $query->paginate($perPage);

            $contractsSummary = $contracts->map(function($contract) {
                $schedules = $contract->paymentSchedules;
                $totalSchedules = $schedules->count();
                $paidSchedules = $schedules->where('status', 'pagado')->count();
                $pendingSchedules = $schedules->where('status', 'pendiente')->count();
                $overdueSchedules = $schedules->where('status', 'vencido')->count();
                
                $totalAmount = $schedules->sum('amount');
                $paidAmount = $schedules->where('status', 'pagado')->sum('amount');
                $pendingAmount = $schedules->where('status', 'pendiente')->sum('amount');
                $overdueAmount = $schedules->where('status', 'vencido')->sum('amount');
                
                $paymentRate = $totalAmount > 0 ? ($paidAmount / $totalAmount) * 100 : 0;
                
                // Próxima fecha de vencimiento
                $nextDueSchedule = $schedules->where('status', 'pendiente')
                    ->sortBy('due_date')
                    ->first();
                
                // Obtener cliente (directo o desde reserva)
                $client = $contract->getClient();
                $clientName = $client ? $client->full_name : 'N/A';
                $clientId = $client ? $client->client_id : null;
                
                // Obtener asesor (directo o desde reserva)
                $advisor = $contract->getAdvisor();
                $advisorName = $advisor ? $advisor->full_name : 'N/A';
                $advisorId = $advisor ? $advisor->employee_id : null;
                
                // Obtener lote (directo o desde reserva)
                $lot = $contract->getLot();
                $lotName = 'N/A';
                if ($lot) {
                    $manzanaName = $lot->manzana ? $lot->manzana->name : 'N/A';
                    $lotNumber = $lot->num_lot ?? 'N/A';
                    $lotName = $manzanaName . '-' . $lotNumber;
                }
                
                return [
                    'contract_id' => $contract->contract_id,
                    'contract_number' => $contract->contract_number,
                    'client_name' => $clientName,
                    'client_id' => $clientId,
                    'advisor_name' => $advisorName,
                    'advisor_id' => $advisorId,
                    'lot_name' => $lotName,
                    'total_schedules' => $totalSchedules,
                    'paid_schedules' => $paidSchedules,
                    'pending_schedules' => $pendingSchedules,
                    'overdue_schedules' => $overdueSchedules,
                    'total_amount' => $totalAmount,
                    'paid_amount' => $paidAmount,
                    'pending_amount' => $pendingAmount,
                    'overdue_amount' => $overdueAmount,
                    'payment_rate' => round($paymentRate, 2),
                    'next_due_date' => $nextDueSchedule ? $nextDueSchedule->due_date : null,
                    'schedules' => $schedules->map(function($schedule) {
                        return [
                            'schedule_id' => $schedule->schedule_id,
                            'installment_number' => $schedule->installment_number,
                            'due_date' => $schedule->due_date,
                            'amount' => $schedule->amount,
                            'status' => $schedule->status,
                            'is_overdue' => $schedule->due_date < now() && $schedule->status != 'pagado',
                            'days_overdue' => $schedule->due_date < now() && $schedule->status != 'pagado' 
                                ? now()->diffInDays($schedule->due_date) : 0
                        ];
                    })->values()
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Contratos con cronogramas obtenidos exitosamente',
                'data' => $contractsSummary->values(),
                'pagination' => [
                    'current_page' => $contracts->currentPage(),
                    'last_page' => $contracts->lastPage(),
                    'per_page' => $contracts->perPage(),
                    'total' => $contracts->total(),
                    'from' => $contracts->firstItem(),
                    'to' => $contracts->lastItem()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo contratos: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Marca un cronograma como pagado
     */
    public function markScheduleAsPaid(Request $request, $schedule_id): JsonResponse
    {
        try {
            $request->validate([
                'payment_date' => 'sometimes|date',
                'payment_amount' => 'sometimes|numeric|min:0',
                'payment_method' => 'sometimes|string|max:50',
                'notes' => 'sometimes|string|max:500'
            ]);

            $schedule = PaymentSchedule::findOrFail($schedule_id);
            
            if ($schedule->status === 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'El cronograma ya está marcado como pagado',
                    'data' => null
                ], 400);
            }

            $schedule->update([
                'status' => 'paid',
                'paid_date' => $request->input('payment_date', now()),
                'paid_amount' => $request->input('payment_amount', $schedule->amount),
                'payment_method' => $request->input('payment_method'),
                'notes' => $request->input('notes')
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cronograma marcado como pagado exitosamente',
                'data' => [
                    'schedule_id' => $schedule->schedule_id,
                    'installment_number' => $schedule->installment_number,
                    'status' => $schedule->status,
                    'paid_date' => $schedule->paid_date,
                    'paid_amount' => $schedule->paid_amount
                ]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cronograma no encontrado',
                'data' => null
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error marcando cronograma como pagado: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Marca un cronograma como vencido
     */
    public function markScheduleAsOverdue($schedule_id): JsonResponse
    {
        try {
            $schedule = PaymentSchedule::findOrFail($schedule_id);
            
            if ($schedule->status === 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede marcar como vencido un cronograma ya pagado',
                    'data' => null
                ], 400);
            }

            $schedule->update([
                'status' => 'overdue'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cronograma marcado como vencido exitosamente',
                'data' => [
                    'schedule_id' => $schedule->schedule_id,
                    'installment_number' => $schedule->installment_number,
                    'status' => $schedule->status,
                    'due_date' => $schedule->due_date,
                    'days_overdue' => now()->diffInDays($schedule->due_date)
                ]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cronograma no encontrado',
                'data' => null
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error marcando cronograma como vencido: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Reporte de resumen de pagos
     */
    public function getPaymentSummaryReport(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'start_date' => 'sometimes|date',
                'end_date' => 'sometimes|date|after_or_equal:start_date',
                'contract_id' => 'sometimes|exists:contracts,contract_id'
            ]);

            $startDate = $request->input('start_date', now()->startOfMonth());
            $endDate = $request->input('end_date', now()->endOfMonth());
            $contractId = $request->input('contract_id');

            $query = PaymentSchedule::with(['contract.reservation.client', 'contract.reservation.lot.manzana'])
                ->whereBetween('due_date', [$startDate, $endDate]);

            if ($contractId) {
                $query->where('contract_id', $contractId);
            }

            $schedules = $query->get();

            $summary = [
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ],
                'totals' => [
                    'total_schedules' => $schedules->count(),
                    'total_amount' => $schedules->sum('amount'),
                    'paid_amount' => $schedules->where('status', 'pagado')->sum('amount'),
                    'pending_amount' => $schedules->where('status', 'pendiente')->sum('amount'),
                    'overdue_amount' => $schedules->where('status', 'vencido')->sum('amount')
                ],
                'by_status' => [
                    'paid' => [
                        'count' => $schedules->where('status', 'pagado')->count(),
                        'amount' => $schedules->where('status', 'pagado')->sum('amount')
                    ],
                    'pending' => [
                'count' => $schedules->where('status', 'pendiente')->count(),
                'amount' => $schedules->where('status', 'pendiente')->sum('amount')
                    ],
                    'overdue' => [
                        'count' => $schedules->where('status', 'vencido')->count(),
                        'amount' => $schedules->where('status', 'vencido')->sum('amount')
                    ]
                ],
                'collection_rate' => $schedules->sum('amount') > 0 
                    ? round(($schedules->where('status', 'pagado')->sum('amount') / $schedules->sum('amount')) * 100, 2)
                    : 0
            ];

            return response()->json([
                'success' => true,
                'message' => 'Reporte de resumen de pagos generado exitosamente',
                'data' => $summary
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generando reporte: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Reporte de análisis de vencidos
     */
    public function getOverdueAnalysisReport(Request $request): JsonResponse
    {
        try {
            $overdueSchedules = PaymentSchedule::with(['contract.reservation.client', 'contract.reservation.lot.manzana'])
                ->where('status', 'overdue')
                ->orWhere(function($query) {
                    $query->where('due_date', '<', now())
                          ->where('status', '!=', 'paid');
                })
                ->get();

            $analysis = [
                'total_overdue' => [
                    'count' => $overdueSchedules->count(),
                    'amount' => $overdueSchedules->sum('amount')
                ],
                'by_aging' => [
                    '1-30_days' => [
                        'count' => $overdueSchedules->filter(function($s) {
                            $days = now()->diffInDays($s->due_date);
                            return $days >= 1 && $days <= 30;
                        })->count(),
                        'amount' => $overdueSchedules->filter(function($s) {
                            $days = now()->diffInDays($s->due_date);
                            return $days >= 1 && $days <= 30;
                        })->sum('amount')
                    ],
                    '31-60_days' => [
                        'count' => $overdueSchedules->filter(function($s) {
                            $days = now()->diffInDays($s->due_date);
                            return $days >= 31 && $days <= 60;
                        })->count(),
                        'amount' => $overdueSchedules->filter(function($s) {
                            $days = now()->diffInDays($s->due_date);
                            return $days >= 31 && $days <= 60;
                        })->sum('amount')
                    ],
                    '61-90_days' => [
                        'count' => $overdueSchedules->filter(function($s) {
                            $days = now()->diffInDays($s->due_date);
                            return $days >= 61 && $days <= 90;
                        })->count(),
                        'amount' => $overdueSchedules->filter(function($s) {
                            $days = now()->diffInDays($s->due_date);
                            return $days >= 61 && $days <= 90;
                        })->sum('amount')
                    ],
                    'over_90_days' => [
                        'count' => $overdueSchedules->filter(function($s) {
                            return now()->diffInDays($s->due_date) > 90;
                        })->count(),
                        'amount' => $overdueSchedules->filter(function($s) {
                            return now()->diffInDays($s->due_date) > 90;
                        })->sum('amount')
                    ]
                ],
                'top_overdue_contracts' => $overdueSchedules->groupBy('contract_id')
                    ->map(function($schedules, $contractId) {
                        $contract = $schedules->first()->contract;
                        return [
                            'contract_id' => $contractId,
                            'contract_number' => $contract->contract_number,
                            'client_name' => $contract->reservation->client->full_name ?? 'N/A',
                            'overdue_count' => $schedules->count(),
                            'overdue_amount' => $schedules->sum('amount'),
                            'oldest_overdue_days' => $schedules->max(function($s) {
                                return now()->diffInDays($s->due_date);
                            })
                        ];
                    })
                    ->sortByDesc('overdue_amount')
                    ->take(10)
                    ->values()
            ];

            return response()->json([
                'success' => true,
                'message' => 'Reporte de análisis de vencidos generado exitosamente',
                'data' => $analysis
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generando reporte: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Reporte de eficiencia de cobranza
     */
    public function getCollectionEfficiencyReport(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'start_date' => 'sometimes|date',
                'end_date' => 'sometimes|date|after_or_equal:start_date'
            ]);

            $startDate = $request->input('start_date', now()->startOfMonth());
            $endDate = $request->input('end_date', now()->endOfMonth());

            // Cronogramas que vencían en el período
            $schedulesInPeriod = PaymentSchedule::whereBetween('due_date', [$startDate, $endDate])->get();
            
            // Cronogramas pagados en el período (independientemente de cuándo vencían)
            $paidInPeriod = PaymentSchedule::whereBetween('paid_date', [$startDate, $endDate])
                ->where('status', 'paid')
                ->get();

            $efficiency = [
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ],
                'schedules_due_in_period' => [
                    'total_count' => $schedulesInPeriod->count(),
                    'total_amount' => $schedulesInPeriod->sum('amount'),
                    'paid_count' => $schedulesInPeriod->where('status', 'paid')->count(),
                    'paid_amount' => $schedulesInPeriod->where('status', 'paid')->sum('amount')
                ],
                'payments_received_in_period' => [
                    'total_count' => $paidInPeriod->count(),
                    'total_amount' => $paidInPeriod->sum('amount'),
                    'on_time_count' => $paidInPeriod->filter(function($s) {
                        return $s->paid_date <= $s->due_date;
                    })->count(),
                    'late_count' => $paidInPeriod->filter(function($s) {
                        return $s->paid_date > $s->due_date;
                    })->count()
                ],
                'efficiency_metrics' => [
                    'collection_rate' => $schedulesInPeriod->sum('amount') > 0 
                        ? round(($schedulesInPeriod->where('status', 'paid')->sum('amount') / $schedulesInPeriod->sum('amount')) * 100, 2)
                        : 0,
                    'on_time_payment_rate' => $paidInPeriod->count() > 0
                        ? round(($paidInPeriod->filter(function($s) { return $s->paid_date <= $s->due_date; })->count() / $paidInPeriod->count()) * 100, 2)
                        : 0,
                    'average_days_to_collect' => $paidInPeriod->count() > 0
                        ? round($paidInPeriod->avg(function($s) {
                            return $s->paid_date->diffInDays($s->due_date);
                        }), 1)
                        : 0
                ]
            ];

            return response()->json([
                'success' => true,
                'message' => 'Reporte de eficiencia de cobranza generado exitosamente',
                'data' => $efficiency
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generando reporte: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Reporte de antigüedad de saldos
     */
    public function getAgingReport(Request $request): JsonResponse
    {
        try {
            $pendingSchedules = PaymentSchedule::with(['contract.reservation.client', 'contract.reservation.lot.manzana'])
                ->where('status', '!=', 'paid')
                ->get();

            $agingReport = [
                'summary' => [
                    'total_outstanding' => $pendingSchedules->sum('amount'),
                    'total_contracts' => $pendingSchedules->pluck('contract_id')->unique()->count()
                ],
                'aging_buckets' => [
                    'current' => [
                        'description' => 'No vencido (0 días)',
                        'count' => $pendingSchedules->filter(function($s) {
                            return $s->due_date >= now();
                        })->count(),
                        'amount' => $pendingSchedules->filter(function($s) {
                            return $s->due_date >= now();
                        })->sum('amount')
                    ],
                    '1-30_days' => [
                        'description' => '1-30 días vencido',
                        'count' => $pendingSchedules->filter(function($s) {
                            $days = now()->diffInDays($s->due_date);
                            return $s->due_date < now() && $days >= 1 && $days <= 30;
                        })->count(),
                        'amount' => $pendingSchedules->filter(function($s) {
                            $days = now()->diffInDays($s->due_date);
                            return $s->due_date < now() && $days >= 1 && $days <= 30;
                        })->sum('amount')
                    ],
                    '31-60_days' => [
                        'description' => '31-60 días vencido',
                        'count' => $pendingSchedules->filter(function($s) {
                            $days = now()->diffInDays($s->due_date);
                            return $s->due_date < now() && $days >= 31 && $days <= 60;
                        })->count(),
                        'amount' => $pendingSchedules->filter(function($s) {
                            $days = now()->diffInDays($s->due_date);
                            return $s->due_date < now() && $days >= 31 && $days <= 60;
                        })->sum('amount')
                    ],
                    '61-90_days' => [
                        'description' => '61-90 días vencido',
                        'count' => $pendingSchedules->filter(function($s) {
                            $days = now()->diffInDays($s->due_date);
                            return $s->due_date < now() && $days >= 61 && $days <= 90;
                        })->count(),
                        'amount' => $pendingSchedules->filter(function($s) {
                            $days = now()->diffInDays($s->due_date);
                            return $s->due_date < now() && $days >= 61 && $days <= 90;
                        })->sum('amount')
                    ],
                    'over_90_days' => [
                        'description' => 'Más de 90 días vencido',
                        'count' => $pendingSchedules->filter(function($s) {
                            return $s->due_date < now() && now()->diffInDays($s->due_date) > 90;
                        })->count(),
                        'amount' => $pendingSchedules->filter(function($s) {
                            return $s->due_date < now() && now()->diffInDays($s->due_date) > 90;
                        })->sum('amount')
                    ]
                ],
                'by_contract' => $pendingSchedules->groupBy('contract_id')
                    ->map(function($schedules, $contractId) {
                        $contract = $schedules->first()->contract;
                        return [
                            'contract_id' => $contractId,
                            'contract_number' => $contract->contract_number,
                            'client_name' => $contract->reservation->client->full_name ?? 'N/A',
                            'total_outstanding' => $schedules->sum('amount'),
                            'oldest_overdue_days' => $schedules->filter(function($s) {
                                return $s->due_date < now();
                            })->max(function($s) {
                                return now()->diffInDays($s->due_date);
                            }) ?? 0
                        ];
                    })
                    ->sortByDesc('total_outstanding')
                    ->values()
            ];

            return response()->json([
                'success' => true,
                'message' => 'Reporte de antigüedad de saldos generado exitosamente',
                'data' => $agingReport
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generando reporte: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Show the specified resource.
     */
    public function show($id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Collection resource found',
            'data' => [
                'id' => $id,
                'type' => 'collection'
            ]
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Edit method not implemented'
        ], 501);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Update method not implemented'
        ], 501);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Destroy method not implemented'
        ], 501);
    }
}
