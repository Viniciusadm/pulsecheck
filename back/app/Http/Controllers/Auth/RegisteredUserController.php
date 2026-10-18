<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;

class RegisteredUserController extends Controller
{
    public function store(RegisterRequest $request): UserResource
    {
        $user = User::create($request->validated());

        event(new Registered($user));

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return new UserResource($user);
    }
}
