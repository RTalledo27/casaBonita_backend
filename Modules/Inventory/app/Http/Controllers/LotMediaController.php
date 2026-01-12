<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Inventory\Http\Requests\LotMediaRequest;
use Modules\Inventory\Models\LotMedia;
use Modules\Inventory\Repositories\LotMediaRepository;
use Modules\Inventory\Transformers\lotMediaResource;
use Modules\Services\PusherNotifier;
use Pusher\Pusher;

class LotMediaController extends Controller
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

    /* ---------- DI + políticas / permisos ---------- */
    public function __construct(private LotMediaRepository $repository)
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:inventory.media.index')->only(['index', 'show']);
        $this->middleware('permission:inventory.media.store')->only(['store']);
        $this->middleware('permission:inventory.media.update')->only(['update']);
        $this->middleware('permission:inventory.media.destroy')->only(['destroy']);

        $this->authorizeResource(LotMedia::class, 'lot_media');
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return LotMediaResource::collection(LotMedia::all());
    }

    /** Crear media */
    public function store(LotMediaRequest $request)
    {
        try {
            /*DB::beginTransaction();

            $media = $this->repository->create($request->validated());

            DB::commit();
            $pusher = $this->pusherInstance();
            $pusher->trigger('lot-media-channel', 'created', [
                'media' => (new LotMediaResource($media))->toArray($request),
            ]);
            

            return (new LotMediaResource($media))
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear media',
                'error'   => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }*/

            DB::beginTransaction();

            // 1) Subir archivo
            $path = $request->file('file')
                ->store('lots/media', 'public'); // storage/app/public/lots/media

            // 2) Crear registro con URL generada
            $media = LotMedia::create([
                'lot_id'   => $request->lot_id,
                'url'      => Storage::url($path), // genera /storage/…
                'type'     => $request->type,
                'position' => LotMedia::where('lot_id', $request->lot_id)->max('position') + 1,
                'uploaded_at' => now(),
            ]);

            DB::commit();

            // Pusher
            $this->pusherInstance()->trigger(
                'lot-media-channel',
                'created',
                ['media' => new LotMediaResource($media)]
            );

            return response()->json(['data' => new LotMediaResource($media)], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear media',
                'error'   => $e->getMessage(),
            ], 500);
        
        }
    }

    /** Mostrar media */
    public function show(LotMedia $lot_media)
    {
        return new LotMediaResource($lot_media);
    }

    /** Actualizar media */
    public function update(LotMediaRequest $request, LotMedia $lot_media)
    {
        try {
            DB::beginTransaction();

            $this->repository->update($lot_media, $request->validated());

            DB::commit();

            $fresh = $lot_media->fresh();
            $pusher = $this->pusherInstance();
            
            $pusher->trigger('lot-media-channel', 'updated', [
                'media' => (new LotMediaResource($fresh))->toArray($request),
            ]);

            return new LotMediaResource($fresh);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar media',
                'error'   => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /** Eliminar media */
    public function destroy(LotMedia $lot_media)
    {
        try {
            $mediaData = (new lotMediaResource($lot_media))->toArray(request());

            $this->repository->delete($lot_media);

            $pusher = $this->pusherInstance();
            $pusher->trigger('lot-media-channel', 'deleted', [
                'media' => $mediaData,
            ]);

            

            return response()->json(['message' => 'Media eliminada correctamente']);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error al eliminar media',
                'error'   => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
