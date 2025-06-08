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
        $this->middleware('permission:sales.contracts.index')->only(['index', 'show']);
        $this->middleware('permission:sales.contracts.store')->only(['store']);
        $this->middleware('permission:sales.contracts.update')->only(['update']);
        $this->middleware('permission:sales.contracts.destroy')->only(['destroy']);

        $this->authorizeResource(Contract::class, 'contract');
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //

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

            $contract = $this->repository->create($request->validated());

            DB::commit();

            $this->pusher->notify('contract-channel', 'created', [
                'contract' => (new ContractResource($contract->load(['reservation', 'schedules', 'invoices'])))->toArray($request),
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
     * Show the specified resource.
     */
    public function show(Contract $contract)
    {
        return new ContractResource(
            $contract->load(['reservation', 'schedules', 'invoices'])
        );
        }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateContractRequest $request, Contract $contract)
    {
        try {
            DB::beginTransaction();

            $this->repository->update($contract, $request->validated());

            DB::commit();

            $fresh = $contract->fresh()->load(['reservation', 'schedules', 'invoices']);

            $this->pusher->notify('contract-channel', 'updated', [
                'contract' => (new ContractResource($fresh))->toArray($request),
            ]);

            return new ContractResource($fresh);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar contrato',
                'error'   => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Contract $contract)
    {
        //
        try {
            $resource = new ContractResource($contract->load(['reservation', 'schedules', 'invoices']));

            $this->repository->delete($contract);

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
}
