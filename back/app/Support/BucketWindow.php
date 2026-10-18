<?php

namespace App\Support;

use App\Enums\StatsPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

class BucketWindow
{
    public readonly CarbonImmutable $now;

    public readonly CarbonImmutable $first;

    public readonly CarbonImmutable $last;

    private readonly int $bucketSeconds;

    public function __construct(private readonly StatsPeriod $period)
    {
        $this->bucketSeconds = $period->bucketMinutes() * 60;
        $this->now = CarbonImmutable::now('UTC');
        $this->first = $this->floor($this->now->subHours($period->hours()));
        $this->last = $this->floor($this->now);
    }

    public function selectBucket(Builder $query, string $column): Builder
    {
        return $query
            ->where($column, '>=', $this->first)
            ->where($column, '<=', $this->now)
            ->selectRaw("extract(epoch from date_bin(?::interval, {$column}, timestamptz '1970-01-01 00:00:00+00'))::bigint as bucket", ["{$this->period->bucketMinutes()} minutes"])
            ->groupBy('bucket');
    }

    public function fill(Collection $rows, callable $map): array
    {
        $rows = $rows->keyBy('bucket');
        $buckets = [];

        for ($bucket = $this->first; $bucket->lessThanOrEqualTo($this->last); $bucket = $bucket->addSeconds($this->bucketSeconds)) {
            $buckets[] = ['bucket_start' => $bucket->toIso8601ZuluString(), ...$map($rows[$bucket->getTimestamp()] ?? null)];
        }

        return $buckets;
    }

    private function floor(CarbonImmutable $moment): CarbonImmutable
    {
        $timestamp = $moment->getTimestamp();

        return CarbonImmutable::createFromTimestampUTC($timestamp - ($timestamp % $this->bucketSeconds));
    }
}
