<?php

namespace Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\CRM\Http\Requests\StoreFamilyMemberRequest;
use Modules\CRM\Http\Requests\UpdateFamilyMemberRequest;
use Modules\CRM\Models\FamilyMember;
use Modules\CRM\Repositories\FamilyMemberRepository;
use Modules\CRM\Transformers\FamilyMemberResource;
use Modules\services\PusherNotifier;

class FamilyMemberController extends Controller
{
    public function __construct(
        private FamilyMemberRepository $members,
        private PusherNotifier $pusher
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('can:crm.access');
        $this->middleware('permission:crm.clients.view')->only(['index', 'show']);
        $this->middleware('permission:crm.clients.create')->only('store');
        $this->middleware('permission:crm.clients.update')->only('update');
        $this->middleware('permission:crm.clients.delete')->only('destroy');
    }

    public function index(Request $request)
    {
        return FamilyMemberResource::collection($this->members->paginate($request->all()));
    }

    public function store(StoreFamilyMemberRequest $req)
    {
        $member = $this->members->create($req->validated());
        $this->pusher->notify('family_member', 'created', ['family_member' => new FamilyMemberResource($member)]);
        return new FamilyMemberResource($member);
    }

    public function show(FamilyMember $familyMember)
    {
        return new FamilyMemberResource($familyMember);
    }

    public function update(UpdateFamilyMemberRequest $req, FamilyMember $familyMember)
    {
        $updated = $this->members->update($familyMember, $req->validated());
        $this->pusher->notify('family_member', 'updated', ['family_member' => new FamilyMemberResource($updated)]);
        return new FamilyMemberResource($updated);
    }

    public function destroy(FamilyMember $familyMember)
    {
        $id = $familyMember->family_member_id;
        $this->members->delete($familyMember);
        $this->pusher->notify('family_member', 'deleted', ['family_member' => ['family_member_id' => $id]]);
        return response()->json(['message' => 'Miembro eliminado', 'family_member_id' => $id]);
    }
}
