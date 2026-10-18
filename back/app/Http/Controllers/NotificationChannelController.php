<?php

namespace App\Http\Controllers;

use App\Actions\SaveNotificationChannel;
use App\Http\Requests\StoreNotificationChannelRequest;
use App\Http\Requests\UpdateNotificationChannelRequest;
use App\Http\Resources\NotificationChannelResource;
use App\Models\NotificationChannel;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class NotificationChannelController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $channels = NotificationChannel::query()
            ->visibleTo($request->user())
            ->with('teams:id')
            ->withCount($this->visibleMonitorsCount($request->user()))
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return NotificationChannelResource::collection($channels);
    }

    public function store(StoreNotificationChannelRequest $request, SaveNotificationChannel $saveNotificationChannel): NotificationChannelResource
    {
        $channel = $saveNotificationChannel->create($request->validated());

        return new NotificationChannelResource($channel->loadCount($this->visibleMonitorsCount($request->user())));
    }

    public function update(UpdateNotificationChannelRequest $request, NotificationChannel $notificationChannel, SaveNotificationChannel $saveNotificationChannel): NotificationChannelResource
    {
        $channel = $saveNotificationChannel->update($request->user(), $notificationChannel, $request->validated());

        return new NotificationChannelResource($channel->loadCount($this->visibleMonitorsCount($request->user())));
    }

    public function destroy(NotificationChannel $notificationChannel): Response
    {
        $notificationChannel->delete();

        return response()->noContent();
    }

    private function visibleMonitorsCount(User $user): array
    {
        return ['monitors' => fn (Builder $monitors) => $monitors->visibleTo($user)];
    }
}
