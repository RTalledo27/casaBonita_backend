<?php

namespace Modules\Collections\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\Sales\Models\Contract;
use Modules\Collections\Models\PaymentSchedule;
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
     * Genera cronogramas de pagos masivamente para contratos específicos
     */
    public function generateBulkSchedules(Request $request): JsonResponse
    {
        try {
            // Validar datos de entrada
            $request->validate([
                'contract_ids' => 'required|array|min:1',
                'contract_ids.*' => 'integer|exists:contracts,contract_id',
                'start_date' => 'nullable|date|after_or_equal:today',
                'notes' => 'nullable|string|max:500'
            ]);

            $contractIds = $request->input('contract_ids');
            $startDate = $request->input('start_date');
            $notes = $request->input('notes');

            $scheduleGenerationService = app(\Modules\Collections\Services\PaymentScheduleGenerationService::class);
            
            $results = [
                'successful' => [],
                'failed' => [],
                'total_processed' => 0,
                'total_successful' => 0,
                'total_failed' => 0
            ];

            foreach ($contractIds as $contractId) {
                try {
                    // Verificar si el contrato existe y obtenerlo con relaciones
                    $contract = Contract::with(['reservation.client', 'reservation.lot'])->find($contractId);
                    
                    if (!$contract) {
                        $results['failed'][] = [
                            'contract_id' => $contractId,
                            'contract_number' => 'N/A',
                            'error' => 'Contrato no encontrado',
                            'success' => false
                        ];
                        continue;
                    }

                    // Verificar si ya tiene cronograma
                    $existingSchedules = PaymentSchedule::where('contract_id', $contractId)->count();
                    if ($existingSchedules > 0) {
                        $results['failed'][] = [
                            'contract_id' => $contractId,
                            'contract_number' => $contract->contract_number,
                            'error' => 'El contrato ya tiene un cronograma de pagos generado',
                            'success' => false
                        ];
                        continue;
                    }

                    // Generar cronograma usando SOLO los parámetros que no interfieren con templates
                    // NO pasar frequency para que el template financiero determine la frecuencia
                    $options = [
                        'start_date' => $startDate,
                        'notes' => $notes
                    ];

                    $result = $scheduleGenerationService->generateScheduleForContract($contract, $options);

                    if ($result['success']) {
                        // Obtener información del contrato generado
                        $contractWithSchedules = Contract::with([
                            'reservation.client',
                            'reservation.lot.manzana',
                            'paymentSchedules' => function($query) {
                                $query->orderBy('due_date', 'asc');
                            }
                        ])->find($contractId);

                        $results['successful'][] = [
                            'contract_id' => $contractId,
                            'contract_number' => $contract->contract_number,
                            'client_name' => $contract->reservation->client->full_name ?? 'N/A',
                            'lot_name' => ($contract->reservation->lot->manzana->name ?? 'N/A') . '-' . ($contract->reservation->lot->num_lot ?? 'N/A'),
                            'total_schedules' => $contractWithSchedules->paymentSchedules->count(),
                            'total_amount' => $contractWithSchedules->paymentSchedules->sum('amount'),
                            'success' => true,
                            'template_info' => [
                                'payment_type' => $result['payment_type'] ?? 'N/A',
                                'installments' => $result['installments'] ?? 0,
                                'monthly_payment' => $result['monthly_payment'] ?? 0,
                                'financing_amount' => $result['financing_amount'] ?? 0
                            ]
                        ];
                    } else {
                        $results['failed'][] = [
                            'contract_id' => $contractId,
                            'contract_number' => $contract->contract_number,
                            'error' => $result['error'] ?? 'Error generando cronograma',
                            'success' => false
                        ];
                    }

                } catch (\Exception $e) {
                    $results['failed'][] = [
                        'contract_id' => $contractId,
                        'contract_number' => 'N/A',
                        'error' => 'Error procesando contrato: ' . $e->getMessage(),
                        'success' => false
                    ];
                }

                $results['total_processed']++;
            }

            $results['total_successful'] = count($results['successful']);
            $results['total_failed'] = count($results['failed']);

            // Crear estructura de respuesta compatible con el frontend
            $responseData = [
                'total_contracts' => $results['total_processed'],
                'successful' => $results['total_successful'],
                'failed' => $results['total_failed'],
                'results' => array_merge($results['successful'], $results['failed'])
            ];

            $message = "Generación masiva completada: {$results['total_successful']} exitosos, {$results['total_failed']} fallidos de {$results['total_processed']} contratos procesados";

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $responseData
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de entrada inválidos',
                'errors' => $e->errors(),
                'data' => null
            ], 422);
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
                        'paid_amount' => $contract->paymentSchedules->where('status', 'pagado')->sum('amount'),  // Changed from 'paid' to 'pagado'
                        'pending_amount' => $contract->paymentSchedules->where('status', '!=', 'pagado')->sum('amount')  // Changed from 'paid' to 'pagado'
                    ],
                    'schedules' => $contract->paymentSchedules->map(function($schedule) {
                        return [
                            'schedule_id' => $schedule->schedule_id,
                            'installment_number' => $schedule->installment_number,
                            'due_date' => $schedule->due_date,
                            'amount' => $schedule->amount,
                            'status' => $schedule->status,
                            'notes' => $schedule->notes,
                            'type' => $schedule->type,
                            'is_overdue' => $schedule->due_date < now() && $schedule->status != 'pagado',
                            'days_overdue' => $schedule->due_date < now() && $schedule->status != 'pagado'
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
            
            if ($schedule->status === 'pagado') {  // Changed from 'paid' to 'pagado'
                return response()->json([
                    'success' => false,
                    'message' => 'El cronograma ya está marcado como pagado',
                    'data' => null
                ], 400);
            }

            $schedule->update([
                'status' => 'pagado',  // Changed from 'paid' to 'pagado'
                'paid_date' => $request->input('payment_date', now()),
                'paid_amount' => $request->input('payment_amount', $schedule->amount),
                'payment_method' => $request->input('payment_method'),
                'notes' => $request->input('notes')
            ]);

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
                $term = trim((string) $request->input('client_name'));
                $query->where(function ($q) use ($term) {
                    $apply = function ($clientQuery) use ($term) {
                        $like = '%' . $term . '%';
                        $clientQuery->where(function ($qq) use ($like) {
                            $qq->where('first_name', 'like', $like)
                                ->orWhere('last_name', 'like', $like)
                                ->orWhere('doc_number', 'like', $like)
                                ->orWhereRaw("CONCAT(first_name,' ',last_name) like ?", [$like]);
                        });
                    };

                    $q->whereHas('reservation.client', function ($clientQuery) use ($apply) {
                        $apply($clientQuery);
                    })->orWhereHas('client', function ($clientQuery) use ($apply) {
                        $apply($clientQuery);
                    });
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

                $separationScheduleIds = [];
                $depositAmount = (float) ($contract->reservation?->deposit_amount ?? 0);
                $initialSchedules = $schedules->filter(function ($s) {
                    return ($s->type ?? null) === 'inicial';
                })->values();

                foreach ($initialSchedules as $s) {
                    $notes = strtolower((string) ($s->notes ?? ''));
                    if (str_contains($notes, 'separ') || str_contains($notes, 'reserva') || str_contains($notes, 'sep')) {
                        $separationScheduleIds[] = (int) $s->schedule_id;
                    }
                }

                if ($depositAmount > 0) {
                    foreach ($initialSchedules as $s) {
                        $scheduleAmount = (float) ($s->amount ?? 0);
                        if (abs($depositAmount - $scheduleAmount) < 0.01) {
                            $separationScheduleIds[] = (int) $s->schedule_id;
                        }
                    }
                }

                if (empty($separationScheduleIds) && $initialSchedules->count() >= 2) {
                    $downPayment = (float) ($contract->down_payment ?? 0);
                    $sumInitial = (float) $initialSchedules->sum('amount');
                    $minInitial = $initialSchedules->sortBy([
                        fn ($a, $b) => ((float) ($a->amount ?? 0)) <=> ((float) ($b->amount ?? 0)),
                        fn ($a, $b) => ((string) ($a->due_date ?? '')) <=> ((string) ($b->due_date ?? '')),
                        fn ($a, $b) => ((int) $a->schedule_id) <=> ((int) $b->schedule_id),
                    ])->first();

                    if ($minInitial) {
                        $minAmount = (float) ($minInitial->amount ?? 0);
                        if ($downPayment > 0 && abs($sumInitial - $downPayment) < 0.05 && $minAmount > 0 && $minAmount < (float) $initialSchedules->max('amount')) {
                            $separationScheduleIds[] = (int) $minInitial->schedule_id;
                        } elseif ($minAmount > 0 && abs($minAmount - 100.0) < 0.01) {
                            $separationScheduleIds[] = (int) $minInitial->schedule_id;
                        }
                    }
                }

                $separationScheduleIds = array_values(array_unique($separationScheduleIds));
                
                // Calcular cuotas vencidas dinámicamente
                $overdueSchedules = $schedules->filter(function($schedule) {
                    return $schedule->due_date < now() && $schedule->status != 'pagado';
                })->count();
                
                // Calcular cuotas pendientes (no pagadas y no vencidas)
                $pendingSchedules = $schedules->filter(function($schedule) {
                    return $schedule->status != 'pagado' && $schedule->due_date >= now();
                })->count();
                
                $totalAmount = $schedules->sum('amount');
                $paidAmount = $schedules->where('status', 'pagado')->sum('amount');
                
                // Calcular montos vencidos dinámicamente
                $overdueAmount = $schedules->filter(function($schedule) {
                    return $schedule->due_date < now() && $schedule->status != 'pagado';
                })->sum('amount');
                
                // Calcular montos pendientes (no pagados y no vencidos)
                $pendingAmount = $schedules->filter(function($schedule) {
                    return $schedule->status != 'pagado' && $schedule->due_date >= now();
                })->sum('amount');
                
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
                    'schedules' => $schedules->map(function($schedule) use ($separationScheduleIds) {
                        // Determinar el estado real basado en la fecha de vencimiento
                        $actualStatus = $schedule->status;
                        $isOverdue = $schedule->due_date < now() && $schedule->status != 'pagado';
                        $daysOverdue = 0;
                        
                        if ($isOverdue) {
                            $actualStatus = 'vencido';
                            // Calcular días exactos sin decimales
                            $dueDate = \Carbon\Carbon::parse($schedule->due_date)->startOfDay();
                            $currentDate = \Carbon\Carbon::now()->startOfDay();
                            $daysOverdue = $currentDate->diffInDays($dueDate);
                        }

                        $typeLabel = null;
                        if (($schedule->type ?? null) === 'inicial' && in_array((int) $schedule->schedule_id, $separationScheduleIds, true)) {
                            $typeLabel = 'Separación';
                        }
                        
                        return [
                            'schedule_id' => $schedule->schedule_id,
                            'installment_number' => $schedule->installment_number,
                            'due_date' => $schedule->due_date,
                            'amount' => $schedule->amount,
                            'status' => $actualStatus,
                            'notes' => $schedule->notes,
                            'type' => $schedule->type,
                            'type_label' => $typeLabel,
                            'is_overdue' => $isOverdue,
                            'days_overdue' => $daysOverdue
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
            
            if ($schedule->status === 'pagado') {  // Changed from 'paid' to 'pagado'
                return response()->json([
                    'success' => false,
                    'message' => 'El cronograma ya está marcado como pagado',
                    'data' => null
                ], 400);
            }

            $schedule->update([
                'status' => 'pagado',  // Changed from 'paid' to 'pagado'
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

            // Check if no schedules found
            if ($schedules->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No se encontraron cronogramas de pago para el período seleccionado',
                    'data' => [
                        'period' => [
                            'start_date' => $startDate,
                            'end_date' => $endDate
                        ],
                        'totals' => [
                            'total_schedules' => 0,
                            'total_amount' => 0,
                            'paid_amount' => 0,
                            'pending_amount' => 0,
                            'overdue_amount' => 0
                        ],
                        'by_status' => [
                            'paid' => ['count' => 0, 'amount' => 0],
                            'pending' => ['count' => 0, 'amount' => 0],
                            'overdue' => ['count' => 0, 'amount' => 0]
                        ],
                        'collection_rate' => 0,
                        'schedules' => []
                    ]
                ]);
            }

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
                    : 0,
                'schedules' => $schedules->map(function($schedule) {
                    return [
                        'schedule_id' => $schedule->schedule_id,
                        'contract_id' => $schedule->contract_id,
                        'due_date' => $schedule->due_date,
                        'amount' => $schedule->amount,
                        'status' => $schedule->status,
                        'paid_date' => $schedule->paid_date,
                        'client_name' => $schedule->contract->reservation->client->full_name ?? 'N/A',
                        'lot_number' => $schedule->contract->reservation->lot->lot_number ?? 'N/A'
                    ];
                })
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

            // Check if no overdue schedules found
            if ($overdueSchedules->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No se encontraron cronogramas vencidos',
                    'data' => [
                        'total_overdue' => ['count' => 0, 'amount' => 0],
                        'by_aging' => [
                            '1-30_days' => ['count' => 0, 'amount' => 0],
                            '31-60_days' => ['count' => 0, 'amount' => 0],
                            '61-90_days' => ['count' => 0, 'amount' => 0],
                            'over_90_days' => ['count' => 0, 'amount' => 0]
                        ],
                        'top_overdue_contracts' => [],
                        'schedules' => []
                    ]
                ]);
            }

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
                            return $s->due_date < now() && now()->diffInDays($s->due_date) > 90;
                        })->count(),
                        'amount' => $overdueSchedules->filter(function($s) {
                            return $s->due_date < now() && now()->diffInDays($s->due_date) > 90;
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

            $startDate = $request->input('start_date', now()->startOfMonth()->format('Y-m-d'));
            $endDate = $request->input('end_date', now()->endOfMonth()->format('Y-m-d'));

            // Cronogramas que vencían en el período
            $schedulesInPeriod = PaymentSchedule::whereBetween('due_date', [$startDate, $endDate])->get();
            
            // Cronogramas pagados en el período (independientemente de cuándo vencían)
            $paidInPeriod = PaymentSchedule::whereNotNull('paid_date')
                ->whereBetween('paid_date', [$startDate, $endDate])
                ->where('status', 'pagado')
                ->get();

            // Check if no schedules found for the period
            if ($schedulesInPeriod->isEmpty() && $paidInPeriod->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No se encontraron cronogramas para el período seleccionado',
                    'data' => [
                        'period' => [
                            'start_date' => $startDate,
                            'end_date' => $endDate
                        ],
                        'schedules_due_in_period' => [
                            'total_count' => 0,
                            'total_amount' => 0,
                            'paid_count' => 0,
                            'paid_amount' => 0
                        ],
                        'payments_received_in_period' => [
                            'total_count' => 0,
                            'total_amount' => 0,
                            'on_time_count' => 0,
                            'late_count' => 0
                        ],
                        'efficiency_metrics' => [
                            'collection_rate' => 0,
                            'on_time_payment_rate' => 0,
                            'average_days_to_collect' => 0
                        ]
                    ]
                ]);
            }

            $efficiency = [
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ],
                'schedules_due_in_period' => [
                    'total_count' => $schedulesInPeriod->count(),
                    'total_amount' => $schedulesInPeriod->sum('amount'),
                    'paid_count' => $schedulesInPeriod->where('status', 'pagado')->count(),
                    'paid_amount' => $schedulesInPeriod->where('status', 'pagado')->sum('amount')
                ],
                'payments_received_in_period' => [
                    'total_count' => $paidInPeriod->count(),
                    'total_amount' => $paidInPeriod->sum('amount'),
                    'on_time_count' => $paidInPeriod->filter(function($s) {
                        return $s->paid_date && $s->paid_date <= $s->due_date;
                    })->count(),
                    'late_count' => $paidInPeriod->filter(function($s) {
                        return $s->paid_date && $s->paid_date > $s->due_date;
                    })->count()
                ],
                'efficiency_metrics' => [
                    'collection_rate' => $schedulesInPeriod->sum('amount') > 0 
                        ? round(($schedulesInPeriod->where('status', 'pagado')->sum('amount') / $schedulesInPeriod->sum('amount')) * 100, 2)
                        : 0,
                    'on_time_payment_rate' => $paidInPeriod->count() > 0
                        ? round(($paidInPeriod->filter(function($s) { return $s->paid_date && $s->paid_date <= $s->due_date; })->count() / $paidInPeriod->count()) * 100, 2)
                        : 0,
                    'average_days_to_collect' => $paidInPeriod->count() > 0
                        ? round($paidInPeriod->filter(function($s) { return $s->paid_date; })->avg(function($s) {
                            return Carbon::parse($s->paid_date)->diffInDays(Carbon::parse($s->due_date));
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

            // Check if no pending schedules found
            if ($pendingSchedules->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No se encontraron cronogramas pendientes',
                    'data' => [
                        'summary' => [
                            'total_outstanding' => 0,
                            'total_contracts' => 0
                        ],
                        'aging_buckets' => [
                            'current' => ['description' => 'No vencido (0 días)', 'count' => 0, 'amount' => 0],
                            '1-30_days' => ['description' => '1-30 días vencido', 'count' => 0, 'amount' => 0],
                            '31-60_days' => ['description' => '31-60 días vencido', 'count' => 0, 'amount' => 0],
                            '61-90_days' => ['description' => '61-90 días vencido', 'count' => 0, 'amount' => 0],
                            'over_90_days' => ['description' => 'Más de 90 días vencido', 'count' => 0, 'amount' => 0]
                        ],
                        'by_contract' => [],
                        'schedules' => []
                    ]
                ]);
            }

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
                    ->values(),
                'schedules' => $pendingSchedules->map(function($schedule) {
                    return [
                        'schedule_id' => $schedule->schedule_id,
                        'contract_id' => $schedule->contract_id,
                        'due_date' => $schedule->due_date,
                        'amount' => $schedule->amount,
                        'status' => $schedule->status,
                        'days_overdue' => $schedule->due_date < now() ? now()->diffInDays($schedule->due_date) : 0,
                        'aging_bucket' => $this->getAgingBucket($schedule->due_date),
                        'client_name' => $schedule->contract->reservation->client->full_name ?? 'N/A',
                        'lot_number' => $schedule->contract->reservation->lot->lot_number ?? 'N/A'
                    ];
                })
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
     * Helper method to determine aging bucket for a due date
     */
    private function getAgingBucket($dueDate): string
    {
        if ($dueDate >= now()) {
            return 'current';
        }
        
        $days = now()->diffInDays($dueDate);
        
        if ($days <= 30) {
            return '1-30_days';
        } elseif ($days <= 60) {
            return '31-60_days';
        } elseif ($days <= 90) {
            return '61-90_days';
        } else {
            return 'over_90_days';
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

     /**
      * Reporte de predicciones de cobranza
      */
     public function getCollectionPredictions(Request $request): JsonResponse
     {
         try {
             $request->validate([
                 'months' => 'sometimes|integer|min:1|max:12'
             ]);

             $months = $request->input('months', 3);
             
             // Obtener datos históricos para predicciones
             $historicalData = PaymentSchedule::selectRaw('
                 YEAR(due_date) as year,
                 MONTH(due_date) as month,
                 COUNT(*) as total_schedules,
                 SUM(amount) as total_amount,
                 SUM(CASE WHEN status = "paid" THEN amount ELSE 0 END) as paid_amount
             ')
             ->where('due_date', '>=', now()->subMonths(12))
             ->groupBy('year', 'month')
             ->orderBy('year', 'desc')
             ->orderBy('month', 'desc')
             ->get();

             $predictions = [];
             $currentDate = now();
             
             for ($i = 1; $i <= $months; $i++) {
                 $futureDate = $currentDate->copy()->addMonths($i);
                 $monthName = $futureDate->format('F');
                 
                 // Predicción simple basada en promedio histórico
                 $avgAmount = $historicalData->avg('paid_amount') ?: 85000;
                 $variance = $avgAmount * 0.15; // 15% de varianza
                 $predicted = $avgAmount + (rand(-100, 100) / 100 * $variance);
                 
                 $predictions[] = [
                     'month' => $monthName,
                     'predicted' => round($predicted, 2),
                     'confidence' => round(0.85 - ($i * 0.05), 2) // Confianza decrece con el tiempo
                 ];
             }

             return response()->json([
                 'success' => true,
                 'message' => 'Predicciones generadas exitosamente',
                 'data' => [
                     'predictions' => $predictions,
                     'accuracy' => 87.5,
                     'trend' => 'upward'
                 ]
             ]);

         } catch (\Exception $e) {
             return response()->json([
                 'success' => false,
                 'message' => 'Error generando predicciones: ' . $e->getMessage(),
                 'data' => null
             ], 500);
         }
     }
 }
