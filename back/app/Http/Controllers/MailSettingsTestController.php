<?php

namespace App\Http\Controllers;

use App\Mail\MailSettingsTestMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class MailSettingsTestController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $email = $request->user()->email;

        try {
            Mail::to($email)->send(new MailSettingsTestMail);
        } catch (Throwable $exception) {
            Log::warning('Mail settings test failed', ['exception' => $exception::class, 'message' => $exception->getMessage()]);

            return response()->json(['message' => __('The test email could not be sent. Check the mail settings and try again.')], 422);
        }

        return response()->json(['message' => __('Test email sent to :email.', ['email' => $email])]);
    }
}
