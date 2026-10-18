<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateDashboardLayoutRequest;
use App\Support\DashboardLayout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardLayoutController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => DashboardLayout::for($request->user())]);
    }

    public function update(UpdateDashboardLayoutRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->dashboard_period = $request->period();

        if ($request->has('widgets')) {
            $user->dashboard_layout = $request->widgets();
        }

        $user->save();

        return response()->json(['data' => DashboardLayout::for($user)]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->forceFill(['dashboard_layout' => null])->save();

        return response()->json(['data' => DashboardLayout::for($user)]);
    }
}
