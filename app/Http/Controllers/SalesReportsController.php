<?php

namespace App\Http\Controllers;

use App\Services\SalesReportsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class SalesReportsController extends Controller
{
    protected $salesReportsService;

    public function __construct(SalesReportsService $salesReportsService)
    {
        $this->salesReportsService = $salesReportsService;
    }

    /**
     * Get sales reports with filters
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'advisor_id' => 'nullable|integer|exists:users,id',
            'office_id' => 'nullable|integer|exists:offices,id',
            'status' => 'nullable|in:pending,confirmed,cancelled',
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
                'start_date', 'end_date', 'advisor_id', 'office_id', 'status'
            ]);
            
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 15);

            $salesData = $this->salesReportsService->getSalesReport($filters, $page, $perPage);

            return response()->json([
                'success' => true,
                'data' => $salesData['data'],
                'summary' => $salesData['summary'],
                'pagination' => $salesData['pagination']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener reporte de ventas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sales summary metrics
     */
    public function summary(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'advisor_id' => 'nullable|integer|exists:users,id',
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
            $filters = $request->only(['start_date', 'end_date', 'advisor_id', 'office_id']);
            $summary = $this->salesReportsService->getSalesSummary($filters);

            return response()->json([
                'success' => true,
                'data' => $summary
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener resumen de ventas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sales by advisor
     */
    public function byAdvisor(Request $request): JsonResponse
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
            $salesByAdvisor = $this->salesReportsService->getSalesByAdvisor($filters);

            return response()->json([
                'success' => true,
                'data' => $salesByAdvisor
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener ventas por asesor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sales trends and analytics
     */
    public function trends(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'period' => 'nullable|in:daily,weekly,monthly',
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
            $period = $request->get('period', 'monthly');
            
            $trends = $this->salesReportsService->getSalesTrends($filters, $period);

            return response()->json([
                'success' => true,
                'data' => $trends
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener tendencias de ventas',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}