<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMailSettingsRequest;
use App\Http\Resources\MailSettingsResource;
use App\Models\MailSettings;
use App\Support\SecretMerger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response;

class MailSettingsController extends Controller
{
    private const LOCK_KEY = 7_371_000_002;

    public function show(): MailSettingsResource
    {
        return new MailSettingsResource(MailSettings::current());
    }

    public function update(UpdateMailSettingsRequest $request): JsonResponse
    {
        $settings = DB::transaction(function () use ($request): MailSettings {
            DB::statement('select pg_advisory_xact_lock(?)', [self::LOCK_KEY]);

            $settings = MailSettings::query()->oldest('id')->first() ?? new MailSettings;
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

            return $settings;
        });

        return (new MailSettingsResource($settings))->response()->setStatusCode(Response::HTTP_OK);
    }

    public function destroy(): Response
    {
        MailSettings::current()?->delete();

        return response()->noContent();
    }
}
