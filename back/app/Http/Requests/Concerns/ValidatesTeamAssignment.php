<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

trait ValidatesTeamAssignment
{
    protected function teamRules(): array
    {
        $user = $this->user();

        return [
            'team_ids' => $user->canViewEverything() ? ['sometimes', 'array'] : ['required', 'array', 'min:1'],
            'team_ids.*' => ['integer', 'distinct', Rule::in($user->visibleTeamIds())],
        ];
    }
}
