<?php

namespace App\Support;

class SecretMerger
{
    public static function merge(array $stored, array $incoming, array $secretKeys): array
    {
        $preserved = collect($secretKeys)
            ->filter(fn (string $key) => ($incoming[$key] ?? null) === null && array_key_exists($key, $stored))
            ->mapWithKeys(fn (string $key) => [$key => $stored[$key]])
            ->all();

        return [...array_filter($incoming, fn (mixed $value) => $value !== null), ...$preserved];
    }
}
