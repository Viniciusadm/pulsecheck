<?php

namespace App\Http\Controllers;

use App\Actions\SaveNotificationChannel;
use App\Http\Requests\StoreNotificationChannelRequest;
use App\Http\Requests\UpdateNotificationChannelRequest;
use App\Http\Resources\NotificationChannelResource;
use App\Models\NotificationChannel;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class NotificationChannelController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $channels = NotificationChannel::query()
            ->withCount('monitors')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return NotificationChannelResource::collection($channels);
    }

    public function store(StoreNotificationChannelRequest $request, SaveNotificationChannel $saveNotificationChannel): NotificationChannelResource
    {
        $channel = $saveNotificationChannel->create($request->validated());

        return new NotificationChannelResource($channel->loadCount('monitors'));
    }

    public function update(UpdateNotificationChannelRequest $request, NotificationChannel $notificationChannel, SaveNotificationChannel $saveNotificationChannel): NotificationChannelResource
    {
        $channel = $saveNotificationChannel->update($notificationChannel, $request->validated());

        return new NotificationChannelResource($channel->loadCount('monitors'));
    }

    public function destroy(NotificationChannel $notificationChannel): Response
    {
        $notificationChannel->delete();

        return response()->noContent();
    }
}
