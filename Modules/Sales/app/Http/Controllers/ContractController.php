<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Modules\Sales\Http\Requests\ContractRequest;
use Modules\Sales\Http\Requests\UpdateContractRequest;
use Modules\Sales\Models\Contract;
use Modules\Sales\Repositories\ContractRepository;
use Modules\Sales\Transformers\ContractResource;
use Modules\services\PusherNotifier;

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
        $filters = [
            'search' => $request->get('search'),
            'status' => $request->get('status'),
            'with_financing' => $request->get('with_financing', true), // Default to contracts with financing
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

            DB::commit();

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
            $contract->load(['reservation', 'advisor', 'schedules', 'invoices', 'approvals', 'previousContract'])
        );
    }

    /**
     * Update the given contract with validated data.
     */
    public function update(UpdateContractRequest $request, Contract $contract)
    {
        try {
            DB::beginTransaction();

            $updatedContract = $this->repository->update($contract, $request->validated());

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
            \Modules\Sales\Models\PaymentSchedule::insert($schedules);
            
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
}