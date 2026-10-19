<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesTeamAssignment;
use App\Models\Monitor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class StoreStatusPageRequest extends FormRequest
{
    use ValidatesTeamAssignment;

    public const MAX_MONITORS = 50;

    private const RESERVED_SLUGS = ['new', 'edit', 'api', 'admin'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $this->uniqueSlug(), Rule::notIn(self::RESERVED_SLUGS)],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'is_published' => ['sometimes', 'boolean'],
            'show_latency' => ['sometimes', 'boolean'],
            'show_incidents' => ['sometimes', 'boolean'],
            'uptime_days' => ['sometimes', 'integer', Rule::in([30, 60, 90])],
            'monitors' => ['sometimes', 'array', 'max:'.self::MAX_MONITORS],
            'monitors.*.monitor_id' => ['required', 'integer', 'distinct', Rule::in(Monitor::query()->visibleTo($this->user())->pluck('id')->all())],
            'monitors.*.display_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            ...$this->teamRules(),
        ];
    }

    protected function uniqueSlug(): Unique
    {
        return Rule::unique('status_pages', 'slug');
    }
}
