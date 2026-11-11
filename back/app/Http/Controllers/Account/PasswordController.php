<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdatePasswordRequest;
use App\Services\AuditLog;
use Illuminate\Http\Response;

class PasswordController extends Controller
{
    public function update(UpdatePasswordRequest $request, AuditLog $auditLog): Response
    {
        $user = $request->user();
        $user->forceFill(['password' => $request->validated('password')])->save();
        $auditLog->record('account.password_changed', $user);

        return response()->noContent();
    }
}
