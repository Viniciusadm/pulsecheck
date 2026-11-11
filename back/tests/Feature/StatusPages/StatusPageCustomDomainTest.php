<?php

namespace Tests\Feature\StatusPages;

use App\Models\InstanceSettings;
use App\Models\Monitor;
use App\Models\StatusPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StatusPageCustomDomainTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://monitor.example.com']);
        InstanceSettings::factory()->create();
    }

    public function test_manager_sets_a_custom_domain_normalized_from_a_url(): void
    {
        $this->actingAs(User::factory()->admin()->create())->fromSpa();

        $this->postJson('/api/status-pages', ['name' => 'Acme', 'slug' => 'acme', 'custom_domain' => ' https://Status.Acme.com/ '])
            ->assertCreated()
            ->assertJsonPath('data.custom_domain', 'status.acme.com')
            ->assertJsonPath('data.public_url', 'https://status.acme.com');

        $this->postJson('/api/status-pages', ['name' => 'Other', 'slug' => 'other'])
            ->assertCreated()
            ->assertJsonPath('data.custom_domain', null)
            ->assertJsonPath('data.public_url', 'https://monitor.example.com/status/other');
    }

    public static function invalidDomains(): array
    {
        return [
            'single label' => ['localhost'],
            'with path' => ['status.acme.com/page'],
            'with port' => ['status.acme.com:8080'],
            'underscore' => ['status_acme.com'],
            'ip address' => ['10.0.0.1'],
            'application host' => ['monitor.example.com'],
            'taken' => ['taken.acme.com'],
        ];
    }

    #[DataProvider('invalidDomains')]
    public function test_invalid_or_taken_domains_are_rejected(string $domain): void
    {
        StatusPage::factory()->create(['custom_domain' => 'taken.acme.com']);
        $this->actingAs(User::factory()->admin()->create())->fromSpa();

        $this->postJson('/api/status-pages', ['name' => 'Acme', 'slug' => 'acme', 'custom_domain' => $domain])
            ->assertJsonValidationErrors('custom_domain');
    }

    public function test_a_page_keeps_its_own_domain_on_update_and_can_clear_it(): void
    {
        $page = StatusPage::factory()->create(['custom_domain' => 'status.acme.com']);
        $this->actingAs(User::factory()->admin()->create())->fromSpa();

        $this->patchJson("/api/status-pages/{$page->id}", ['custom_domain' => 'status.acme.com'])->assertOk();
        $this->patchJson("/api/status-pages/{$page->id}", ['custom_domain' => ''])->assertOk()->assertJsonPath('data.custom_domain', null);
    }

    public function test_config_on_a_custom_domain_points_to_its_page(): void
    {
        StatusPage::factory()->create(['slug' => 'acme', 'custom_domain' => 'status.acme.com', 'is_published' => true]);

        $this->getJson('http://status.acme.com/api/config')->assertOk()->assertJsonPath('data.status_page_domain', ['slug' => 'acme']);
        $this->getJson('http://STATUS.acme.com/api/config')->assertJsonPath('data.status_page_domain', ['slug' => 'acme']);
        $this->getJson('http://monitor.example.com/api/config')->assertJsonPath('data.status_page_domain', null);
        $this->getJson('http://unknown.example.org/api/config')->assertJsonPath('data.status_page_domain', null);
    }

    public function test_only_the_public_data_of_its_page_is_served_on_a_custom_domain(): void
    {
        $monitor = Monitor::factory()->create(['badge_token' => str_repeat('b', 40)]);
        $page = StatusPage::factory()->create(['slug' => 'acme', 'custom_domain' => 'status.acme.com', 'is_published' => true]);
        $page->monitors()->attach($monitor, ['sort_order' => 0]);
        StatusPage::factory()->create(['slug' => 'internal', 'is_published' => true]);
        $admin = User::factory()->admin()->create();

        $this->getJson('http://status.acme.com/api/public/status-pages/acme')->assertOk()->assertJsonPath('data.name', $page->name);
        $this->get('http://status.acme.com/api/public/badges/'.str_repeat('b', 40).'/status.svg')->assertOk();
        $this->getJson('http://status.acme.com/api/public/status-pages/internal')->assertNotFound();
        $this->postJson('http://status.acme.com/api/auth/login', ['email' => $admin->email, 'password' => 'password'])->assertNotFound();
        $this->postJson('http://status.acme.com/api/setup', [])->assertNotFound();
        $this->actingAs($admin)->getJson('http://status.acme.com/api/monitors')->assertNotFound();
        $this->getJson('http://status.acme.com/api/heartbeat/'.str_repeat('a', 40))->assertNotFound();
        $this->getJson('http://status.acme.com/api/monitors')->assertNotFound();
        $this->getJson('http://status.acme.com/api/metrics')->assertNotFound();
        $this->get('http://status.acme.com/sanctum/csrf-cookie')->assertNotFound();
        $this->get('http://status.acme.com/up')->assertOk();

        $this->actingAs($admin)->getJson('http://monitor.example.com/api/monitors')->assertOk();
    }

    public function test_an_unpublished_page_on_its_domain_is_not_found(): void
    {
        StatusPage::factory()->create(['slug' => 'acme', 'custom_domain' => 'status.acme.com', 'is_published' => false]);

        $this->getJson('http://status.acme.com/api/config')->assertJsonPath('data.status_page_domain', ['slug' => 'acme']);
        $this->getJson('http://status.acme.com/api/public/status-pages/acme')->assertNotFound();
    }
}
