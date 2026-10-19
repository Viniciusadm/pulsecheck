<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTeamRequest;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

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

    public function show(Team $team): TeamResource
    {
        return $this->detailed($team);
    }

    public function store(StoreTeamRequest $request): TeamResource
    {
        return $this->detailed($this->save(new Team, $request));
    }

    public function update(StoreTeamRequest $request, Team $team): TeamResource
    {
        return $this->detailed($this->save($team, $request));
    }

    public function destroy(Team $team): Response
    {
        $team->delete();

        return response()->noContent();
    }

    private function save(Team $team, StoreTeamRequest $request): Team
    {
        return DB::transaction(function () use ($team, $request) {
            $team->fill(['name' => $request->validated('name')])->save();

            if ($request->has('user_ids')) {
                $team->users()->sync($request->validated('user_ids'));
            }

            if ($request->has('monitor_ids')) {
                $team->monitors()->sync($request->validated('monitor_ids'));
            }

            return $team;
        });
    }

    private function detailed(Team $team): TeamResource
    {
        return new TeamResource($team->load(['users:id', 'monitors:id'])->loadCount(['users', 'monitors']));
    }
}
