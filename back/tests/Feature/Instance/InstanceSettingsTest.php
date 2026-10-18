<?php

namespace Tests\Feature\Instance;

use App\Models\InstanceSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InstanceSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_updates_the_instance(): void
    {
        InstanceSettings::factory()->create(['name' => 'Old', 'timezone' => 'UTC', 'locale' => 'en']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->fromSpa()->putJson('/api/instance', [
            'name' => 'Acme Ops',
            'timezone' => 'Europe/Lisbon',
            'locale' => 'en',
        ])
            ->assertOk()
            ->assertExactJson(['data' => ['name' => 'Acme Ops', 'timezone' => 'Europe/Lisbon', 'locale' => 'en']]);

        $this->assertSame('Europe/Lisbon', InstanceSettings::sole()->timezone);
        $this->fromSpa()->getJson('/api/config')->assertJsonPath('data.instance.name', 'Acme Ops');
    }

    public static function invalidPayloads(): array
    {
        return [
            'missing name' => [['name' => null], 'name'],
            'name too long' => [['name' => str_repeat('a', 101)], 'name'],
            'invalid timezone' => [['timezone' => 'Nowhere/City'], 'timezone'],
            'unsupported locale' => [['locale' => 'xx'], 'locale'],
        ];
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_payloads_are_rejected(array $overrides, string $field): void
    {
        InstanceSettings::factory()->create();

        $this->actingAs(User::factory()->admin()->create())->fromSpa()
            ->putJson('/api/instance', array_merge(['name' => 'Acme', 'timezone' => 'UTC', 'locale' => 'en'], $overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    }

    public function test_members_cannot_update_the_instance(): void
    {
        InstanceSettings::factory()->create(['name' => 'Old']);

        $this->actingAs(User::factory()->create())->fromSpa()
            ->putJson('/api/instance', ['name' => 'New', 'timezone' => 'UTC'])
            ->assertForbidden();

        $this->assertSame('Old', InstanceSettings::sole()->name);
    }

    public function test_api_requests_use_the_instance_locale(): void
    {
        config(['pulsecheck.locales' => ['en', 'pt']]);
        Route::middleware('api')->get('api/test-locale', fn () => ['locale' => app()->getLocale()]);

        $this->fromSpa()->getJson('/api/test-locale')->assertExactJson(['locale' => 'en']);

        InstanceSettings::factory()->create(['locale' => 'pt']);

        $this->fromSpa()->getJson('/api/test-locale')->assertExactJson(['locale' => 'pt']);
    }

    public function test_unsupported_stored_locales_fall_back_to_the_default(): void
    {
        Route::middleware('api')->get('api/test-locale', fn () => ['locale' => app()->getLocale()]);
        InstanceSettings::factory()->create(['locale' => 'xx']);

        $this->fromSpa()->getJson('/api/test-locale')->assertExactJson(['locale' => 'en']);
    }
}
