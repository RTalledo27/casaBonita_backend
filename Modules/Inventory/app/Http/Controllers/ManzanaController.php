<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Http\Requests\ManzanaRequest;
use Modules\Inventory\Models\Manzana;
use Modules\Inventory\Repositories\ManzanaRepository;
use Modules\Inventory\Transformers\ManzanaResource;
use Modules\Services\PusherNotifier;
use Pusher\Pusher;

class ManzanaController extends Controller
{
    /* ---------- Helper Pusher idéntico al resto de controladores ---------- */
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

    public function __construct(private ManzanaRepository $repository)
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:inventory.manzanas.view')->only(['index', 'show']);
        $this->middleware('permission:inventory.manzanas.store')->only(['store']);
        $this->middleware('permission:inventory.manzanas.update')->only(['update']);
        $this->middleware('permission:inventory.manzanas.destroy')->only(['destroy']);

        // Política
        $this->authorizeResource(Manzana::class, 'manzana');
    }

    /* ---------- CRUD ---------- */

    /** Listar manzanas */
    public function index()
    {
        return ManzanaResource::collection(
            $this->repository->all()
        );
    }

    /** Crear manzana */
    public function store(ManzanaRequest $request)
    {
        try {
            DB::beginTransaction();

            $manzana = $this->repository->create($request->validated());

            DB::commit();

            $pusher = $this->pusherInstance();
            $pusher->trigger('manzana-channel', 'created', [
                'manzana' => (new ManzanaResource($manzana))->toArray($request),
            ]);

            return (new ManzanaResource($manzana))
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear manzana',
                'error'   => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /** Mostrar manzana */
    public function show(Manzana $manzana)
    {
        return new ManzanaResource($manzana);
    }

    /** Actualizar manzana */
    public function update(ManzanaRequest $request, Manzana $manzana)
    {
        try {
            DB::beginTransaction();

            $this->repository->update($manzana, $request->validated());

            DB::commit();

            $fresh = $manzana->fresh();
            $pusher = $this->pusherInstance();
            
            $pusher->trigger('manzana-channel', 'updated', [
                'manzana' => (new ManzanaResource($fresh))->toArray($request),
            ]);

            return new ManzanaResource($fresh);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar manzana',
                'error'   => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /** Eliminar manzana */
    public function destroy(Manzana $manzana)
    {
        try {
            $manzanaData = (new ManzanaResource($manzana))->toArray(request());

            $this->repository->delete($manzana);
            $pusher = $this->pusherInstance();
            $pusher->trigger('manzana-channel', 'deleted', [
                'manzana' => $manzanaData,
            ]);

            return response()->json(['message' => 'Manzana eliminada correctamente']);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error al eliminar manzana',
                'error'   => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
