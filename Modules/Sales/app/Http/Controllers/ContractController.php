<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\UserActivityLog;
use App\Events\ContractCreated;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Modules\Sales\Http\Requests\ContractRequest;
use Modules\Sales\Http\Requests\UpdateContractRequest;
use Modules\Sales\Models\Contract;
use Modules\Sales\Repositories\ContractRepository;
use Modules\Sales\Transformers\ContractResource;
use Modules\Services\PusherNotifier;

class ContractController extends Controller
{
    public function __construct(private ContractRepository $repository, private PusherNotifier $pusher)
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:sales.contracts.view')->only(['index', 'show']);
        $this->middleware('permission:sales.contracts.store')->only(['store']);
        $this->middleware('permission:sales.contracts.update')->only(['update']);
        $this->middleware('permission:sales.contracts.destroy')->only(['destroy']);

        $this->authorizeResource(Contract::class, 'contract');
    }

    /**
     * List registered contracts in a paginated collection.
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 15);
        $withFinancingRaw = $request->get('with_financing', true);
        $withFinancing = filter_var($withFinancingRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($withFinancing === null) {
            $withFinancing = true;
        }
        $filters = [
            'search' => $request->get('search'),
            'status' => $request->get('status'),
            'with_financing' => $withFinancing,
        ];

        return ContractResource::collection(
            $this->repository->paginate($perPage, $filters)
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ContractRequest $request)
    {
        try {
            DB::beginTransaction();

            $approvers = $request->input('approvers', []);
            $contract = $this->repository->create($request->validated(), $approvers);

            // Registrar actividad
            UserActivityLog::log(
                $request->user()->user_id,
                UserActivityLog::ACTION_CONTRACT_CREATED,
                "Contrato #{$contract->contract_id} creado",
                [
                    'contract_id' => $contract->contract_id,
                    'client_name' => $contract->reservation->client_name ?? 'N/A',
                ]
            );

            DB::commit();

            // Disparar evento para actualizar corte del día
            event(new ContractCreated($contract));

            // Notify listeners a new contract was created
            $this->pusher->notify('contract-channel', 'created', [
                'contract' => (new ContractResource($contract->load(['reservation', 'advisor', 'schedules', 'approvals'])))->toArray($request),
            ]);

            return (new ContractResource($contract))
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear contrato',
                'error'   => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display a single contract with its related data.
     */
    public function show(Contract $contract)
    {
        return new ContractResource(
            $contract->load(['reservation', 'advisor', 'schedules', 'invoices', 'approvals', 'previousContract', 'lot.manzana', 'client', 'advisor.user', 'lot'])
        );
    }

    public function batch(Request $request)
    {
        $idsParam = $request->get('ids', '');
        $ids = collect(explode(',', $idsParam))->filter()->map(fn($i) => (int) $i)->all();
        if (empty($ids)) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $contracts = Contract::with(['client.addresses', 'reservation.client.addresses', 'lot.manzana', 'reservation.lot.manzana'])
            ->whereIn('contract_id', $ids)
            ->get();

        $data = $contracts->map(function(Contract $contract) {
            $client = $contract->getClient();
            $lot = $contract->getLot();
            $addr = $client ? $client->addresses()->orderByDesc('address_id')->first() : null;
            return [
                'contract_id' => $contract->contract_id,
                'contract_number' => $contract->contract_number,
                'client' => $client ? [
                    'client_id' => $client->client_id,
                    'first_name' => $client->first_name,
                    'last_name' => $client->last_name,
                    'doc_number' => $client->doc_number ?? $client->document_number,
                    'email' => $client->email,
                    'primary_phone' => $client->primary_phone,
                    'secondary_phone' => $client->secondary_phone,
                    'address' => $addr ? [
                        'line1' => $addr->line1,
                        'city' => $addr->city,
                        'state' => $addr->state,
                        'country' => $addr->country,
                    ] : null,
                ] : null,
                'lot' => $lot ? [
                    'lot_id' => $lot->lot_id,
                    'num_lot' => $lot->num_lot,
                    'manzana_name' => optional($lot->manzana)->name,
                    'manzana_id' => $lot->manzana_id,
                    'external_code' => $lot->external_code,
                ] : null,
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Update the given contract with validated data.
     */
    public function update(UpdateContractRequest $request, Contract $contract)
    {
        try {
            DB::beginTransaction();

            $updatedContract = $this->repository->update($contract, $request->validated());

            // Registrar actividad
            UserActivityLog::log(
                $request->user()->user_id,
                UserActivityLog::ACTION_CONTRACT_UPDATED,
                "Contrato #{$updatedContract->contract_id} actualizado",
                [
                    'contract_id' => $updatedContract->contract_id,
                    'changes' => $request->validated(),
                ]
            );

            DB::commit();
            
            // Notify listeners about the update
            $this->pusher->notify('contract-channel', 'updated', [
                'contract' => (new ContractResource($updatedContract))->toArray($request),
            ]);

            return new ContractResource($updatedContract);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar contrato',
                'error'   => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete a contract from storage.
     */
    public function destroy(Contract $contract)
    {
        try{
            // Prepare resource for notification before deletion
            $resource = new ContractResource($contract->load(['reservation', 'advisor', 'schedules', 'invoices', 'approvals']));

            $this->repository->delete($contract);

            // Broadcast the deleted contract information
            $this->pusher->notify('contract-channel', 'deleted', [
                'contract' => $resource->toArray(request()),
            ]);

            return response()->json(['message' => 'Contrato eliminado correctamente']);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error al eliminar contrato',
                'error'   => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Generate contract preview PDF
     */
    public function preview(Contract $contract, \Modules\Sales\Services\ContractPdfService $pdf)
    {
        $this->authorize('view', $contract);
        $path = $pdf->preview($contract);
        return response()->json(['pdf_path' => $path]);
    }

    /**
     * Calculate monthly payment for given financial parameters
     */
    public function calculatePayment(Request $request)
    {
        $request->validate([
            'financing_amount' => 'required|numeric|min:0',
            'interest_rate' => 'required|numeric|min:0|max:1',
            'term_months' => 'required|integer|min:1|max:600',
        ]);

        $financingAmount = $request->input('financing_amount');
        $interestRate = $request->input('interest_rate');
        $termMonths = $request->input('term_months');

        if ($interestRate == 0) {
            $monthlyPayment = $financingAmount / $termMonths;
        } else {
            $monthlyRate = $interestRate / 12;
            $monthlyPayment = $financingAmount * ($monthlyRate * pow(1 + $monthlyRate, $termMonths)) / (pow(1 + $monthlyRate, $termMonths) - 1);
        }

        return response()->json([
            'monthly_payment' => round($monthlyPayment, 2),
            'total_interest' => round(($monthlyPayment * $termMonths) - $financingAmount, 2),
            'total_to_pay' => round($monthlyPayment * $termMonths, 2),
        ]);
    }

    /**
     * Generate payment schedule for a contract
     */
    public function generateSchedule(Request $request, Contract $contract)
    {
        $this->authorize('view', $contract);
        
        $request->validate([
            'start_date' => 'required|date|after_or_equal:today',
            'frequency' => 'required|in:monthly,biweekly,weekly',
            'notes' => 'nullable|string|max:500'
        ]);

        try {
            DB::beginTransaction();

            // Delete existing schedules for this contract
            $contract->schedules()->delete();

            $startDate = new \DateTime($request->input('start_date'));
            $frequency = $request->input('frequency');
            $notes = $request->input('notes');
            
            // Calculate payment details
            $financingAmount = $contract->financing_amount;
            $interestRate = $contract->interest_rate;
            $termMonths = $contract->term_months;
            
            // Calculate monthly payment
            if ($interestRate == 0) {
                $monthlyPayment = $financingAmount / $termMonths;
            } else {
                $monthlyRate = $interestRate / 12;
                $monthlyPayment = $financingAmount * ($monthlyRate * pow(1 + $monthlyRate, $termMonths)) / (pow(1 + $monthlyRate, $termMonths) - 1);
            }
            
            // Adjust payment frequency
            $paymentAmount = $monthlyPayment;
            $intervalDays = 30; // monthly by default
            $totalPayments = $termMonths;
            
            switch ($frequency) {
                case 'biweekly':
                    $paymentAmount = $monthlyPayment / 2;
                    $intervalDays = 14;
                    $totalPayments = $termMonths * 2;
                    break;
                case 'weekly':
                    $paymentAmount = $monthlyPayment / 4;
                    $intervalDays = 7;
                    $totalPayments = $termMonths * 4;
                    break;
            }
            
            // Generate payment schedules
            $schedules = [];
            $currentDate = clone $startDate;
            $installmentNumber = 1;
            
            // Add initial payment (cuota inicial) if exists
            $downPayment = $contract->down_payment ?? $contract->initial_quota ?? 0;
            if ($downPayment > 0) {
                $schedules[] = [
                    'contract_id' => $contract->contract_id,
                    'installment_number' => $installmentNumber,
                    'due_date' => $currentDate->format('Y-m-d'),
                    'amount' => round($downPayment, 2),
                    'status' => 'pendiente',
                    'notes' => $notes ? $notes . ' (Cuota inicial)' : 'Cuota inicial'
                ];
                $installmentNumber++;
                // Move to next payment date for regular installments
                $currentDate->add(new \DateInterval('P' . $intervalDays . 'D'));
            }
            
            // Generate regular payment installments
            for ($i = $installmentNumber; $i <= $totalPayments + ($downPayment > 0 ? 1 : 0); $i++) {
                $schedules[] = [
                    'contract_id' => $contract->contract_id,
                    'installment_number' => $i,
                    'due_date' => $currentDate->format('Y-m-d'),
                    'amount' => round($paymentAmount, 2),
                    'status' => 'pendiente',
                    'notes' => $notes
                ];
                
                // Add interval for next payment
                $currentDate->add(new \DateInterval('P' . $intervalDays . 'D'));
            }
            
            // Insert schedules in batches
            \Modules\Collections\Models\PaymentSchedule::insert($schedules);
            
            DB::commit();
            
            // Load the created schedules
            $createdSchedules = $contract->schedules()->orderBy('due_date')->get();
            
            return response()->json([
                'success' => true,
                'message' => 'Cronograma generado exitosamente',
                'data' => [
                    'contract_id' => $contract->contract_id,
                    'total_schedules' => $createdSchedules->count(),
                    'total_amount' => $createdSchedules->sum('amount'),
                    'frequency' => $frequency,
                    'start_date' => $request->input('start_date'),
                    'schedules' => $createdSchedules->map(function($schedule) {
                        return [
                            'schedule_id' => $schedule->schedule_id,
                            'due_date' => $schedule->due_date,
                            'amount' => $schedule->amount,
                            'status' => $schedule->status
                        ];
                    })
                ]
            ], Response::HTTP_CREATED);
            
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al generar cronograma',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtiene contratos con financiamiento
     */
    public function withFinancing(Request $request)
    {
        try {
            // Cargar relaciones para ambos tipos de contratos: con reserva y directos
            $query = Contract::with([
                'reservation.client', 
                'reservation.lot.manzana',
                'client', // Para contratos directos
                'lot.manzana' // Para contratos directos
            ])
                ->where('status', 'vigente')
                ->where('financing_amount', '>', 0);

            // Filtros opcionales
            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function($q) use ($search) {
                    $q->where('contract_number', 'like', "%{$search}%")
                      // Buscar en contratos con reserva
                      ->orWhereHas('reservation.client', function($clientQuery) use ($search) {
                          $clientQuery->where('first_name', 'like', "%{$search}%")
                                     ->orWhere('last_name', 'like', "%{$search}%")
                                     ->orWhere('document_number', 'like', "%{$search}%");
                      })
                      // Buscar en contratos directos
                      ->orWhereHas('client', function($clientQuery) use ($search) {
                          $clientQuery->where('first_name', 'like', "%{$search}%")
                                     ->orWhere('last_name', 'like', "%{$search}%")
                                     ->orWhere('document_number', 'like', "%{$search}%");
                      })
                      // Buscar en lotes (tanto de reserva como directos)
                      ->orWhereHas('reservation.lot', function($lotQuery) use ($search) {
                          $lotQuery->where('num_lot', 'like', "%{$search}%")
                                   ->orWhereHas('manzana', function($manzanaQuery) use ($search) {
                                       $manzanaQuery->where('name', 'like', "%{$search}%");
                                   });
                      })
                      ->orWhereHas('lot', function($lotQuery) use ($search) {
                          $lotQuery->where('num_lot', 'like', "%{$search}%")
                                   ->orWhereHas('manzana', function($manzanaQuery) use ($search) {
                                       $manzanaQuery->where('name', 'like', "%{$search}%");
                                   });
                      });
                });
            }

            // Paginación
            $perPage = $request->input('per_page', 15);
            $contracts = $query->paginate($perPage);

            // Formatear los datos para incluir información del cliente y lote
            $formattedContracts = $contracts->getCollection()->map(function($contract) {
                // Determinar si es contrato directo o con reserva
                $client = $contract->client ?? $contract->reservation?->client;
                $lot = $contract->lot ?? $contract->reservation?->lot;
                $manzana = $lot?->manzana;

                return [
                    'contract_id' => $contract->contract_id,
                    'contract_number' => $contract->contract_number,
                    'financing_amount' => $contract->financing_amount,
                    'term_months' => $contract->term_months,
                    'interest_rate' => $contract->interest_rate,
                    'monthly_payment' => $contract->monthly_payment,
                    'status' => $contract->status,
                    'client_name' => $client ? trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? '')) : 'N/A',
                    'client_document' => $client?->document_number ?? 'N/A',
                    'lot_name' => $lot ? ($manzana?->name ?? 'N/A') . '-' . ($lot->num_lot ?? 'N/A') : 'N/A',
                    'manzana_name' => $manzana?->name ?? 'N/A',
                    'lot_number' => $lot?->num_lot ?? 'N/A'
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedContracts,
                'meta' => [
                    'current_page' => $contracts->currentPage(),
                    'last_page' => $contracts->lastPage(),
                    'per_page' => $contracts->perPage(),
                    'total' => $contracts->total()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo contratos con financiamiento: ' . $e->getMessage()
            ], 500);
        }
    }
}
