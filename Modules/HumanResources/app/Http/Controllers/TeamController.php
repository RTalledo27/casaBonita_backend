<?php

namespace Modules\HumanResources\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HumanResources\Http\Requests\StoreTeamRequest;
use Modules\HumanResources\Http\Requests\UpdateTeamRequest;
use Modules\HumanResources\Repositories\TeamRepository;
use Modules\HumanResources\Transformers\TeamResource;

class TeamController extends Controller
{
    public function __construct(
        protected TeamRepository $teamRepo
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $teams = $this->teamRepo->getAll();

            return response()->json([
                'success' => true,
                'data' => TeamResource::collection($teams),
                'message' => 'Equipos obtenidos exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener equipos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTeamRequest $request): JsonResponse
    {
        try {
            $team = $this->teamRepo->create($request->validated());

            return response()->json([
                'success' => true,
                'data' => new TeamResource($team->load(['leader'])),
                'message' => 'Equipo creado exitosamente'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear equipo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id): JsonResponse
    {
        try {
            $team = $this->teamRepo->find((int) $id);

            if (!$team) {
                return response()->json([
                    'success' => false,
                    'message' => 'Equipo no encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new TeamResource($team),
                'message' => 'Equipo obtenido exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener equipo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTeamRequest $request, $id): JsonResponse
    {
        try {
            $team = $this->teamRepo->update((int) $id, $request->validated());

            if (!$team) {
                return response()->json([
                    'success' => false,
                    'message' => 'Equipo no encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new TeamResource($team),
                'message' => 'Equipo actualizado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar equipo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id): JsonResponse
    {
        try {
            $deleted = $this->teamRepo->delete((int) $id);

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Equipo no encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Equipo eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar equipo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get team members
     */
    public function members($id): JsonResponse
    {
        try {
            $members = $this->teamRepo->getTeamMembers((int) $id);

            return response()->json([
                'success' => true,
                'data' => $members,
                'message' => 'Miembros del equipo obtenidos exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener miembros del equipo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign leader to team
     */
    public function assignLeader(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'team_leader_id' => 'required|exists:employees,employee_id'
            ]);

            $team = $this->teamRepo->assignLeader((int) $id, $request->team_leader_id);

            if (!$team) {
                return response()->json([
                    'success' => false,
                    'message' => 'Equipo no encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new TeamResource($team),
                'message' => 'Líder asignado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al asignar líder: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle team status
     */
    public function toggleStatus($id): JsonResponse
    {
        try {
            $team = $this->teamRepo->toggleStatus((int) $id);

            if (!$team) {
                return response()->json([
                    'success' => false,
                    'message' => 'Equipo no encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new TeamResource($team),
                'message' => 'Estado del equipo actualizado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar estado del equipo: ' . $e->getMessage()
            ], 500);
        }
    }
}