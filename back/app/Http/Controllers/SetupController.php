<?php

namespace App\Http\Controllers;

use App\Actions\CompleteSetup;
use App\Http\Requests\SetupRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class SetupController extends Controller
{
    public function __invoke(SetupRequest $request, CompleteSetup $completeSetup): JsonResponse
    {
        $user = $completeSetup->handle($request->validated());

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return (new UserResource($user))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
