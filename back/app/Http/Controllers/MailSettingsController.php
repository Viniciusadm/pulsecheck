<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMailSettingsRequest;
use App\Http\Resources\MailSettingsResource;
use App\Models\MailSettings;
use App\Support\SecretMerger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class MailSettingsController extends Controller
{
    public function show(): MailSettingsResource
    {
        return new MailSettingsResource(MailSettings::current());
    }

    public function update(UpdateMailSettingsRequest $request): JsonResponse
    {
        $settings = MailSettings::current() ?? new MailSettings;
        $driver = $request->driver();
        $config = $request->config();

        if ($settings->exists && $settings->driver === $driver) {
            $config = SecretMerger::merge($settings->config ?? [], $config, $driver->secretKeys(), $request->validated('clear_secrets', []));
        }

        $settings->fill([
            'driver' => $driver,
            'from_address' => $request->validated('from_address'),
            'from_name' => $request->validated('from_name'),
            'config' => array_filter($config, fn (mixed $value) => $value !== null && $value !== ''),
        ])->save();

        return (new MailSettingsResource($settings))->response()->setStatusCode(Response::HTTP_OK);
    }

    public function destroy(): Response
    {
        MailSettings::current()?->delete();

        return response()->noContent();
    }
}
