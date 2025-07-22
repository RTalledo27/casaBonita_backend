<?php

namespace Modules\HumanResources\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\HumanResources\Http\Requests\storeBonusRequest;
use Modules\HumanResources\Http\Requests\StoreBonusTypeRequest;
use Modules\HumanResources\Http\Requests\UpdateBonusTypeRequest;
use Modules\HumanResources\Repositories\BonusTypeRepository;
use Modules\HumanResources\Transformers\BonusTypeResource;

class BonusTypeController extends Controller
{
    protected BonusTypeRepository $bonusTypeRepo;

    public function __construct( BonusTypeRepository $bonusTypeRepo)
    {
        //LUEGO AGREGAMOS POLICIES
        // $this->middleware('policy:view,Modules\HumanResources\Models\BonusType');

        //REPOSITORy
        $this->bonusTypeRepo = $bonusTypeRepo;
    }   

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $bonusTypes = $this->bonusTypeRepo->getAll();
        return BonusTypeResource::collection($bonusTypes);
    }

    /**
     * Get only active bonus types.
     */
    public function active()
    {
        $activeBonusTypes = $this->bonusTypeRepo->getActive();
        return BonusTypeResource::collection($activeBonusTypes);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBonusTypeRequest $request) {
        $bonusType = $this->bonusTypeRepo->create($request->validated());
        return new BonusTypeResource($bonusType);
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        $bonusType = $this->bonusTypeRepo->find((int) $id);
        return new BonusTypeResource($bonusType);
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
    public function update(UpdateBonusTypeRequest $request, $id) {
        $bonusType = $this->bonusTypeRepo->update((int) $id, $request->validated());
        return new BonusTypeResource($bonusType);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id) {
        $this->bonusTypeRepo->delete((int) $id);
        return response()->noContent();
    }
}
