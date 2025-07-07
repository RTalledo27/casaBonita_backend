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
use Pusher\Pusher;

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
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection     */
    public function index()
    {
        //
        // Return a paginated resource collection of contracts

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
            // Validate and create a new contract
            // Start a database transaction to ensure data integrity
            // If any error occurs, the transaction will be rolled back
            // If the contract is created successfully, commit the transaction
            /*   DB::beginTransaction(); // Start transaction

            $contract = $this->repository->create($request->validated());

            DB::commit(); // Commit transaction

            // Notify listeners a new contract was created
            $this->pusher->notify('contract-channel', 'created', [
                'contract' => (new ContractResource($contract->load(['reservation', 'schedules', 'invoices'])))->toArray($request),
            ]);

            return (new ContractResource($contract))
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            DB::rollBack(); // Undo transaction
            return response()->json([
                'message' => 'Error al crear contrato',
                'error'   => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        } */


            // Si el contrato se crea a partir de una reserva, actualizar el estado de la reserva
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error al crear contrato',
                'error'   => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
                
        }
    /**
     * Display a single contract with its related data.
     *
     * @param Contract $contract
     * @return ContractResource     */
    public function show(Contract $contract)
    {
        // Load related reservation, schedules and invoices
        return new ContractResource(
            $contract->load(['reservation', 'schedules', 'invoices'])
        );
        }

    /**
     * Update the given contract with validated data.
     *
     * A transaction ensures data integrity and the updated contract is
     * broadcasted through Pusher once committed.
    */
    public function update(UpdateContractRequest $request, Contract $contract)
    {
        try {
            DB::beginTransaction(); // Start transaction

            $this->repository->update($contract, $request->validated()); // Update contract

            DB::commit(); // Commit transaction
            
            $fresh = $contract->fresh()->load(['reservation', 'schedules', 'invoices']);
            // Notify listeners about the update
            $this->pusher->notify('contract-channel', 'updated', [
                'contract' => (new ContractResource($fresh))->toArray($request),
            ]);

            return new ContractResource($fresh);
        } catch (\Throwable $e) {
            DB::rollBack(); // Undo transaction
            return response()->json([
                'message' => 'Error al actualizar contrato',
                'error'   => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete a contract from storage.
     *
     * The deleted contract is broadcasted so connected clients can remove
     * it from their lists.     */
    public function destroy(Contract $contract)
    {
        //
        try {
            // Prepare resource for notification before deletion
            $resource = new ContractResource($contract->load(['reservation', 'schedules', 'invoices']));

            $this->repository->delete($contract); // Remove contract from database
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


    public function preview(Contract $contract, \Modules\Sales\Services\ContractPdfService $pdf)
    {
        $this->authorize('view', $contract);
        $path = $pdf->preview($contract);
        return response()->json(['pdf_path' => $path]);
    }
}
