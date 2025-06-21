<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Modules\Sales\Http\Requests\PaymentScheduleRequest;
use Modules\Sales\Http\Requests\UpdatePaymentScheduleRequest;
use Modules\Sales\Models\PaymentSchedule;
use Modules\Sales\Repositories\PaymentScheduleRepository;
use Modules\Sales\Transformers\PaymentScheduleResource;
use Modules\services\PusherNotifier;

class PaymentScheduleController extends Controller
{

    public function __construct(
        private PaymentScheduleRepository $schedules,
        private PusherNotifier $pusher
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:sales.schedules.index')->only(['index', 'show']);
        $this->middleware('permission:sales.schedules.store')->only('store');
        $this->middleware('permission:sales.schedules.update')->only('update');
        $this->middleware('permission:sales.schedules.destroy')->only('destroy');
    }

    public function index()
    {
        return PaymentScheduleResource::collection(
            $this->schedules->paginate()
        );    }

    public function store(PaymentScheduleRequest $request)
    {
        DB::beginTransaction();
        try {
            $schedule = $this->schedules->create($request->validated());
            DB::commit();

            $this->pusher->notify('schedule-channel', 'created', [
                'schedule' => (new PaymentScheduleResource($schedule))->toArray($request),
            ]);

            return (new PaymentScheduleResource($schedule))
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear cronograma',
                'error'   => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(PaymentSchedule $schedule)
    {
        return new PaymentScheduleResource($schedule->load(['contract', 'payments']));
    }

    public function update(UpdatePaymentScheduleRequest $request, PaymentSchedule $schedule)
    {
        DB::beginTransaction();
        try {
            $updated = $this->schedules->update($schedule, $request->validated());
            DB::commit();

            $this->pusher->notify('schedule-channel', 'updated', [
                'schedule' => (new PaymentScheduleResource($updated))->toArray($request),
            ]);

            return new PaymentScheduleResource($updated);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar cronograma',
                'error'   => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(PaymentSchedule $schedule)
    {
        try {
            DB::beginTransaction();
            $resource = new PaymentScheduleResource($schedule->load(['contract', 'payments']));
            $this->schedules->delete($schedule);
            DB::commit();

            $this->pusher->notify('schedule-channel', 'deleted', [
                'schedule' => $resource->toArray(request()),
            ]);

            return response()->json(['message' => 'Cronograma eliminado correctamente']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al eliminar cronograma',
                'error'   => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}