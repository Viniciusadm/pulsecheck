<?php

namespace App\Http\Controllers;

use App\Actions\SaveStatusPage;
use App\Http\Requests\StoreStatusPageRequest;
use App\Http\Requests\UpdateStatusPageRequest;
use App\Http\Resources\StatusPageResource;
use App\Models\StatusPage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class StatusPageController extends Controller
{
    private const RELATIONS = ['teams:id', 'monitors:id,name,status', 'monitors.teams:id'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $statusPages = StatusPage::query()
            ->visibleTo($request->user())
            ->with(self::RELATIONS)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return StatusPageResource::collection($statusPages);
    }

    public function store(StoreStatusPageRequest $request, SaveStatusPage $saveStatusPage): StatusPageResource
    {
        return new StatusPageResource($saveStatusPage->save($request->user(), new StatusPage, $request->validated())->load(self::RELATIONS));
    }

    public function show(StatusPage $statusPage): StatusPageResource
    {
        return new StatusPageResource($statusPage->load(self::RELATIONS));
    }

    public function update(UpdateStatusPageRequest $request, StatusPage $statusPage, SaveStatusPage $saveStatusPage): StatusPageResource
    {
        return new StatusPageResource($saveStatusPage->save($request->user(), $statusPage, $request->validated())->load(self::RELATIONS));
    }

    public function destroy(StatusPage $statusPage): Response
    {
        $statusPage->delete();

        return response()->noContent();
    }
}
