<?php

namespace Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Security\Http\Requests\StoreUserRequest;
use Modules\Security\Http\Requests\UpdateUserRequest;
use Modules\Security\Models\User;
use Modules\Security\Repositories\UserRepository;
use Modules\Security\Transformers\UserResource;

class UserController extends Controller
{


    //CONSTRUCTOR
    public function __construct(private UserRepository $users)
    {
        $this->middleware('auth:sanctum');
        $this->authorizeResource(User::class, 'user');
    }


    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $filters = request()->only(['search', 'sort_by', 'sort_dir', 'per_page']);
        $paginated = $this->users->paginate($filters);
        return UserResource::collection($paginated);
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

    public function store(StoreUserRequest $request)
    {
        $user = $this->users->create($request->validated());
        return new UserResource($user);
    }

    /**
     * Show the specified resource.
     */
    public function show(User $user)
    {
        return new UserResource($user->load('roles'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('security::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        $user = $this->users->update($user, $request->validated());
        return new UserResource($user);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $this->users->delete($user);
        return response()->json([
            'message' => 'User deleted successfully'
        ])->status(200);
    }
}
