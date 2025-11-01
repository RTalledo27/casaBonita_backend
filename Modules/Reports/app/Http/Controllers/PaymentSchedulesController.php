<?php

namespace Modules\Reports\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\Reports\Services\PaymentSchedulesService;

class PaymentSchedulesController extends Controller
{
    protected $paymentSchedulesService;

    public function __construct(PaymentSchedulesService $paymentSchedulesService)
    {
        $this->paymentSchedulesService = $paymentSchedulesService;
    }

    /**
     * Get payment schedules overview
     */
    public function getOverview(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'status' => 'nullable|string|in:pending,paid,overdue,cancelled'
        ]);

        try {
            $data = $this->paymentSchedulesService->getOverview(
                $request->input('date_from'),
                $request->input('date_to'),
                $request->input('status')
            );

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener resumen de cronogramas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment schedules by status
     */
    public function getByStatus(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'required|string|in:pending,paid,overdue,cancelled',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'client_id' => 'nullable|integer',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        try {
            $data = $this->paymentSchedulesService->getByStatus(
                $request->input('status'),
                $request->input('date_from'),
                $request->input('date_to'),
                $request->input('client_id'),
                $request->input('page', 1),
                $request->input('per_page', 20)
            );

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener cronogramas por estado: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get overdue payments
     */
    public function getOverdue(Request $request): JsonResponse
    {
        $request->validate([
            'days_overdue' => 'nullable|integer|min:1',
            'client_id' => 'nullable|integer',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        try {
            $data = $this->paymentSchedulesService->getOverdue(
                $request->input('days_overdue'),
                $request->input('client_id'),
                $request->input('page', 1),
                $request->input('per_page', 20)
            );

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener pagos vencidos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment trends
     */
    public function getPaymentTrends(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'required|string|in:daily,weekly,monthly,quarterly',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date'
        ]);

        try {
            $data = $this->paymentSchedulesService->getPaymentTrends(
                $request->input('period'),
                $request->input('date_from'),
                $request->input('date_to')
            );

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener tendencias de pagos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get collection efficiency
     */
    public function getCollectionEfficiency(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'employee_id' => 'nullable|integer'
        ]);

        try {
            $data = $this->paymentSchedulesService->getCollectionEfficiency(
                $request->input('date_from'),
                $request->input('date_to'),
                $request->input('employee_id')
            );

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener eficiencia de cobranza: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get upcoming payments
     */
    public function getUpcoming(Request $request): JsonResponse
    {
        $request->validate([
            'days_ahead' => 'nullable|integer|min:1|max:365',
            'client_id' => 'nullable|integer',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        try {
            $data = $this->paymentSchedulesService->getUpcoming(
                $request->input('days_ahead', 30),
                $request->input('client_id'),
                $request->input('page', 1),
                $request->input('per_page', 20)
            );

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener próximos pagos: ' . $e->getMessage()
            ], 500);
        }
    }
}
