<?php

namespace Modules\Collections\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CollectionsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Collections module is active',
            'data' => []
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Create method not implemented'
        ], 501);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Store method not implemented'
        ], 501);
    }

    /**
     * Show the specified resource.
     */
    public function show($id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Collection resource found',
            'data' => [
                'id' => $id,
                'type' => 'collection'
            ]
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Edit method not implemented'
        ], 501);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Update method not implemented'
        ], 501);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Destroy method not implemented'
        ], 501);
    }
}
