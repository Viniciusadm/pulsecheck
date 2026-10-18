<?php

namespace App\Actions;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SyncScopedRelation
{
    public function handle(BelongsToMany $relation, array $requestedIds, array $scopeIds): void
    {
        $outOfScopeIds = array_diff($relation->pluck($relation->getRelatedPivotKeyName())->all(), $scopeIds);

        $relation->sync([...$outOfScopeIds, ...array_intersect($requestedIds, $scopeIds)]);
    }
}
