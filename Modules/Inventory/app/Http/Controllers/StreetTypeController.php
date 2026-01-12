<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Http\Requests\StreetTypeRequest;
use Modules\Inventory\Models\StreetType;
use Modules\Inventory\Repositories\StreetTypeRepository;
use Modules\Inventory\Transformers\StreetTypeResource;
use Modules\Services\PusherNotifier;
use Pusher\Pusher;

class StreetTypeController extends Controller
{

    /* ---------- Helper Pusher ---------- */
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

    public function __construct(private StreetTypeRepository $repository)
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:inventory.street-types.view')->only(['index', 'show']);
        $this->middleware('permission:inventory.street-types.store')->only(['store']);
        $this->middleware('permission:inventory.street-types.update')->only(['update']);
        $this->middleware('permission:inventory.street-types.destroy')->only(['destroy']);

        $this->authorizeResource(StreetType::class, 'street_type');
    }

    /* ---------- CRUD ---------- */

    /** Listar tipos de calle */
    public function index()
    {
        return StreetTypeResource::collection(
            $this->repository->all()
        );
    }

    /** Crear tipo de calle */
    public function store(StreetTypeRequest $request)
    {
        try {
            DB::beginTransaction();

            $streetType = $this->repository->create($request->validated());

            DB::commit();

            $pusher = $this->pusherInstance();
            $pusher->trigger('street-type-channel', 'created', [
                'street_type' => (new StreetTypeResource($streetType))->toArray($request),
            ]);

            return (new StreetTypeResource($streetType))
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear tipo de calle',
                'error'   => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /** Mostrar tipo de calle */
    public function show(StreetType $street_type)
    {
        return new StreetTypeResource($street_type);
    }

    /** Actualizar tipo de calle */
    public function update(StreetTypeRequest $request, StreetType $street_type)
    {
        try {
            DB::beginTransaction();

            $this->repository->update($street_type, $request->validated());

            DB::commit();

            $fresh = $street_type->fresh();
            $pusher= $this->pusherInstance();
            $pusher->trigger('street-type-channel', 'updated', [
                'street_type' => (new StreetTypeResource($fresh))->toArray($request),
            ]);

            return new StreetTypeResource($fresh);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar tipo de calle',
                'error'   => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /** Eliminar tipo de calle */
    public function destroy(StreetType $street_type)
    {
        try {
            $resource = new StreetTypeResource($street_type);

            $this->repository->delete($street_type);

            $pusher = $this->pusherInstance();

            $pusher->trigger('street-type-channel', 'deleted', [
                'street_type' => $resource->toArray(request()),
            ]);

            return response()->json(['message' => 'Tipo de calle eliminado correctamente']);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error al eliminar tipo de calle',
                'error'   => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
