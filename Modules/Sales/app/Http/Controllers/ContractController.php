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
    public function index()
    {
        return ContractResource::collection(
            $this->repository->paginate()
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
}