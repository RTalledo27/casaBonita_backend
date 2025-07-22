<?php

namespace Modules\HumanResources\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\HumanResources\Http\Requests\StoreBonusGoalRequest;
use Modules\HumanResources\Http\Requests\UpdateBonusGoalRequest;
use Modules\HumanResources\Repositories\BonusGoalRepository;
use Modules\HumanResources\Transformers\BonusGoalResource;

class BonusGoalController extends Controller
{
    protected BonusGoalRepository $bonusGoalRepo;

    public function __construct(BonusGoalRepository $bonusGoalRepository)
    {
        $this->bonusGoalRepo = $bonusGoalRepository;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $bonusGoals = $this->bonusGoalRepo->getAll();
        return BonusGoalResource::collection($bonusGoals);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBonusGoalRequest $request) {
        $bonusGoal = $this->bonusGoalRepo->create($request->validated());
        return new BonusGoalResource($bonusGoal);
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        //
        $bonusGoal = $this->bonusGoalRepo->find((int) $id);
        return new BonusGoalResource($bonusGoal);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        //return view('humanresources::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBonusGoalRequest $request, $id) {
        $bonusGoal = $this->bonusGoalRepo->update((int) $id, $request->validated());
        return new BonusGoalResource($bonusGoal);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id) {
        $this->bonusGoalRepo->delete((int) $id);
        return response()->noContent();
    }
}
