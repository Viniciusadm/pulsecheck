<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateInstanceSettingsRequest;
use App\Http\Resources\InstanceSettingsResource;
use App\Models\InstanceSettings;
use Symfony\Component\HttpFoundation\Response;

class InstanceSettingsController extends Controller
{
    public function update(UpdateInstanceSettingsRequest $request): InstanceSettingsResource
    {
        $settings = InstanceSettings::current();

        abort_if($settings === null, Response::HTTP_CONFLICT, __('PulseCheck has not been set up yet.'));

        $settings->update($request->validated());

        return new InstanceSettingsResource($settings);
    }
}
