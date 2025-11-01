<?php

namespace App\Http\Controllers;

use App\Services\PaymentSchedulesService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class PaymentSchedulesController extends Controller
{
    protected $paymentSchedulesService;

    public function __construct(PaymentSchedulesService $paymentSchedulesService)
    {
        $this->paymentSchedulesService = $paymentSchedulesService;
    }

    /**
     * Get payment schedules with filters
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'client_id' => 'nullable|integer|exists:clients,id',
            'contract_id' => 'nullable|integer|exists:contracts,id',
            'status' => 'nullable|in:pending,paid,overdue,cancelled',
            'due_date_from' => 'nullable|date',
            'due_date_to' => 'nullable|date|after_or_equal:due_date_from',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parámetros de validación incorrectos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $filters = $request->only([
                'client_id', 'contract_id', 'status', 'due_date_from', 'due_date_to'
            ]);
            
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 15);

            $schedulesData = $this->paymentSchedulesService->getPaymentSchedules($filters, $page, $perPage);

            return response()->json([
                'success' => true,
                'schedules' => $schedulesData['schedules'],
                'overdue_count' => $schedulesData['overdue_count'],
                'total_pending' => $schedulesData['total_pending'],
                'pagination' => $schedulesData['pagination']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener cronogramas de pago',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get overdue payments
     */
    public function overdue(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'days_overdue' => 'nullable|integer|min:1',
            'office_id' => 'nullable|integer|exists:offices,id',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parámetros de validación incorrectos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $filters = $request->only(['days_overdue', 'office_id']);
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 15);

            $overdueData = $this->paymentSchedulesService->getOverduePayments($filters, $page, $perPage);

            return response()->json([
                'success' => true,
                'data' => $overdueData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener pagos vencidos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment calendar data
     */
    public function calendar(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:2020|max:2030',
            'month' => 'required|integer|min:1|max:12',
            'office_id' => 'nullable|integer|exists:offices,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parámetros de validación incorrectos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $year = $request->get('year');
            $month = $request->get('month');
            $officeId = $request->get('office_id');

            $calendarData = $this->paymentSchedulesService->getPaymentCalendar($year, $month, $officeId);

            return response()->json([
                'success' => true,
                'data' => $calendarData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener calendario de pagos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'office_id' => 'nullable|integer|exists:offices,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parámetros de validación incorrectos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $filters = $request->only(['start_date', 'end_date', 'office_id']);
            $statistics = $this->paymentSchedulesService->getPaymentStatistics($filters);

            return response()->json([
                'success' => true,
                'data' => $statistics
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas de pagos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update payment status
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $validator = Validator::make(array_merge($request->all(), ['id' => $id]), [
            'id' => 'required|integer|exists:payment_schedules,id',
            'status' => 'required|in:pending,paid,overdue,cancelled',
            'payment_date' => 'nullable|date',
            'payment_method' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parámetros de validación incorrectos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updateData = $request->only(['status', 'payment_date', 'payment_method', 'notes']);
            $updated = $this->paymentSchedulesService->updatePaymentStatus($id, $updateData);

            return response()->json([
                'success' => true,
                'message' => 'Estado de pago actualizado correctamente',
                'data' => $updated
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar estado de pago',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}