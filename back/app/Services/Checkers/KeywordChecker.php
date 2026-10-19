<?php

namespace App\Services\Checkers;

use App\Enums\CheckErrorType;
use App\Models\Monitor;
use App\Services\CheckOutcome;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class KeywordChecker extends HttpChecker
{
    public const MODES = ['contains', 'not_contains'];

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'options' => ['required', 'array:'.implode(',', array_keys($this->defaultOptions()))],
            'options.method' => ['sometimes', 'string', Rule::in(array_diff(self::METHODS, ['HEAD']))],
            'options.keyword' => ['required', 'string', 'between:1,255'],
            'options.keyword_mode' => ['sometimes', 'string', Rule::in(self::MODES)],
            'options.case_sensitive' => ['sometimes', 'boolean'],
        ];
    }

    public function defaultOptions(): array
    {
        return [
            ...parent::defaultOptions(),
            'keyword' => null,
            'keyword_mode' => 'contains',
            'case_sensitive' => false,
        ];
    }

    public function check(Monitor $monitor): CheckOutcome
    {
        [$outcome, $response] = $this->perform($monitor, (int) config('pulsecheck.keyword_max_body_bytes'));

        if (! $outcome->isSuccess || $response === null) {
            return $outcome;
        }

        $keyword = (string) $monitor->option('keyword');
        $found = $monitor->option('case_sensitive')
            ? str_contains((string) $response->body, $keyword)
            : mb_stripos((string) $response->body, $keyword) !== false;
        $expectsKeyword = $monitor->option('keyword_mode') !== 'not_contains';

        if ($found === $expectsKeyword) {
            return $outcome;
        }

        return new CheckOutcome(
            $outcome->startedAt,
            $outcome->finishedAt,
            $outcome->latencyMs,
            $outcome->statusCode,
            false,
            $expectsKeyword ? CheckErrorType::KeywordMissing : CheckErrorType::KeywordPresent,
            $expectsKeyword
                ? __('The response does not contain ":keyword".', ['keyword' => Str::limit($keyword, 100)])
                : __('The response contains ":keyword".', ['keyword' => Str::limit($keyword, 100)]),
        );
    }
}
