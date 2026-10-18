<?php

namespace App\Http\Controllers;

use App\Actions\CreateUser;
use App\Actions\DeleteUser;
use App\Actions\UpdateUser;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class UserController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return UserResource::collection(User::query()->with('teams:id')->orderBy('name')->orderBy('id')->get());
    }

    public function store(StoreUserRequest $request, CreateUser $createUser): UserResource
    {
        return new UserResource($createUser->handle($request->validated()));
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUser $updateUser): UserResource
    {
        return new UserResource($updateUser->handle($user, $request->attributesToUpdate()));
    }

    public function destroy(Request $request, User $user, DeleteUser $deleteUser): Response
    {
        $deleteUser->handle($request->user(), $user);

        return response()->noContent();
    }
}
