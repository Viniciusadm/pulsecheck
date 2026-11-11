<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesTeamAssignment;
use App\Models\Monitor;
use App\Models\StatusPage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class StoreStatusPageRequest extends FormRequest
{
    use ValidatesTeamAssignment;

    public const MAX_MONITORS = 50;

    private const RESERVED_SLUGS = ['new', 'edit', 'api', 'admin'];

    private const DOMAIN_PATTERN = '/^(?=.{4,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/D';

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
            'custom_domain' => ['sometimes', 'nullable', 'string', 'max:253', 'regex:'.self::DOMAIN_PATTERN, $this->uniqueCustomDomain(), Rule::notIn([StatusPage::applicationHost()])],
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

    protected function uniqueCustomDomain(): Unique
    {
        return Rule::unique('status_pages', 'custom_domain');
    }

    protected function prepareForValidation(): void
    {
        $domain = $this->input('custom_domain');

        if (! is_string($domain)) {
            return;
        }

        $domain = mb_strtolower(trim($domain));
        $domain = str_contains($domain, '://') ? (string) parse_url($domain, PHP_URL_HOST) : rtrim($domain, '/');

        $this->merge(['custom_domain' => $domain === '' ? null : rtrim($domain, '.')]);
    }
}
