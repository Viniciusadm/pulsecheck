<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreApiTokenRequest;
use App\Http\Resources\ApiTokenResource;
use App\Services\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ApiTokenController extends Controller
{
    public const MAX_TOKENS_PER_USER = 25;

    public function index(Request $request): AnonymousResourceCollection
    {
        return ApiTokenResource::collection($request->user()->tokens()->latest('id')->get());
    }

    public function store(StoreApiTokenRequest $request, AuditLog $auditLog): JsonResponse
    {
        $user = $request->user();

        if ($user->tokens()->count() >= self::MAX_TOKENS_PER_USER) {
            return response()->json([
                'message' => __('You can have at most :max API tokens. Revoke one first.', ['max' => self::MAX_TOKENS_PER_USER]),
                'errors' => ['name' => [__('You can have at most :max API tokens. Revoke one first.', ['max' => self::MAX_TOKENS_PER_USER])]],
            ], 422);
        }

        $abilities = array_values(array_unique($request->validated('abilities')));
        $days = $request->validated('expires_in_days');
        $token = $user->createToken($request->validated('name'), $abilities, $days ? now()->addDays($days) : null);
        $auditLog->record('account.api_token_created', $token->accessToken, ['abilities' => $abilities]);

        return (new ApiTokenResource($token->accessToken))
            ->additional(['plain_text_token' => $token->plainTextToken])
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Request $request, int $tokenId, AuditLog $auditLog): Response
    {
        $token = $request->user()->tokens()->whereKey($tokenId)->firstOrFail();
        $token->delete();
        $auditLog->record('account.api_token_revoked', $token);

        return response()->noContent();
    }
}
