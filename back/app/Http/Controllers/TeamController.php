<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTeamRequest;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TeamController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $teams = Team::query()
            ->unless($user->canViewEverything(), fn (Builder $query) => $query->whereHas('users', fn (Builder $users) => $users->whereKey($user->id)))
            ->when($user->isAdmin(), fn (Builder $query) => $query->withCount(['users', 'monitors']))
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return TeamResource::collection($teams);
    }

    public function store(StoreTeamRequest $request): TeamResource
    {
        return new TeamResource(Team::create($request->validated())->loadCount(['users', 'monitors']));
    }

    public function update(StoreTeamRequest $request, Team $team): TeamResource
    {
        $team->update($request->validated());

        return new TeamResource($team->loadCount(['users', 'monitors']));
    }

    public function destroy(Team $team): Response
    {
        $team->delete();

        return response()->noContent();
    }
}
