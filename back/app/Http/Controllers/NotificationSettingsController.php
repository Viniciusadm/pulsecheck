<?php

namespace App\Http\Controllers;

use App\Actions\UpdateTelegramSettings;
use App\Http\Requests\UpdateNotificationSettingsRequest;
use App\Http\Resources\NotificationSettingResource;
use App\Models\NotificationSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationSettingsController extends Controller
{
    public function show(Request $request): NotificationSettingResource
    {
        return new NotificationSettingResource($request->user()->notificationSetting ?? new NotificationSetting);
    }

    public function update(UpdateNotificationSettingsRequest $request, UpdateTelegramSettings $updateTelegramSettings): JsonResponse
    {
        $setting = $updateTelegramSettings->handle($request->user(), $request->validated());

        return (new NotificationSettingResource($setting))->response()->setStatusCode(200);
    }

    public function destroyTelegram(Request $request, UpdateTelegramSettings $updateTelegramSettings): JsonResponse
    {
        $setting = $updateTelegramSettings->remove($request->user());

        return (new NotificationSettingResource($setting))->response()->setStatusCode(200);
    }
}
