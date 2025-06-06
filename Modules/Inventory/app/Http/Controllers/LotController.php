<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Http\Requests\StoreLotRequest;
use Modules\Inventory\Http\Requests\UpdateLotRequest;
use Modules\Inventory\Models\Lot;
use Modules\Inventory\Repositories\LotRepository;
use Modules\Inventory\Transformers\lotResource;
use Modules\services\PusherNotifier;
use Pusher\Pusher;

class LotController extends Controller
{
    private function pusherInstance(): Pusher
    {
        return new Pusher(
            config('broadcasting.connections.pusher.key'),
            config('broadcasting.connections.pusher.secret'),
            config('broadcasting.connections.pusher.app_id'),
            [
                'cluster' => config('broadcasting.connections.pusher.options.cluster'),
                'useTLS'  => true,
            ]
        );
    }

    public function __construct(private LotRepository $repository)
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:inventory.lots.index')->only(['index', 'show']);
        $this->middleware('permission:inventory.lots.store')->only(['store']);
        $this->middleware('permission:inventory.lots.update')->only(['update']);
        $this->middleware('permission:inventory.lots.destroy')->only(['destroy']);

        // Si usas políticas
        $this->authorizeResource(Lot::class, 'lot');
    }

    /** Listar lotes (paginado + filtros opcionales) */
    public function index(Request $request)
    {
        $lots = $this->repository->paginate($request->all());

        return LotResource::collection($lots);
    }

    /** Crear un lote */
    public function store(StoreLotRequest $request)
    {
        try {
            DB::beginTransaction();

            $lot = $this->repository->create($request->validated());

            DB::commit();

            // Push evento "created"
            $pusher =  $this->pusherInstance();
            
            $pusher->trigger('lot-channel', 'created', [
                'lot' => (new LotResource($lot->load(['manzana', 'streetType', 'media'])))->toArray($request),
            ]);

            return (new LotResource($lot))
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear lote',
                'error'   => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /** Mostrar un lote */
    public function show(Lot $lot)
    {
        return new LotResource($lot->load(['manzana', 'streetType', 'media']));
    }

    /** Actualizar un lote */
    public function update(UpdateLotRequest $request, Lot $lot)
    {
        try {
            DB::beginTransaction();

            $this->repository->update($lot, $request->validated());

            DB::commit();

            $pusher= $this->pusherInstance();
             
            $pusher->trigger('lot-channel', 'updated', [
                'lot' => (new LotResource($lot->fresh()->load(['manzana', 'streetType', 'media'])))->toArray($request),
            ]);

            return new LotResource($lot->fresh());
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar lote',
                'error'   => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /** Eliminar un lote */
    public function destroy(Lot $lot)
    {
        try {
            $lotData = (new LotResource($lot->load(['manzana', 'streetType', 'media'])))->toArray(request());

            $this->repository->delete($lot);

            $pusher=$this->pusherInstance();
            $pusher->trigger('lot-channel', 'deleted', [
                'lot' => $lotData,
            ]);

            return response()->json(['message' => 'Lote eliminado correctamente']);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error al eliminar lote',
                'error'   => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
