<?php

namespace Tests\Feature\Setup;

use App\Enums\UserRole;
use App\Models\InstanceSettings;
use App\Models\User;
use App\Support\SetupToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SetupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['pulsecheck.setup_token' => null]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'instance_name' => 'Acme Ops',
            'timezone' => 'America/Sao_Paulo',
            'locale' => 'en',
            'name' => 'Ada Lovelace',
            'email' => 'ADA@example.com',
            'password' => 'secret-pass-42',
            'password_confirmation' => 'secret-pass-42',
        ], $overrides);
    }

    public function test_setup_creates_the_instance_and_the_admin_and_logs_in(): void
    {
        $response = $this->fromSpa()->postJson('/api/setup', $this->validPayload());

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Ada Lovelace')
            ->assertJsonPath('data.email', 'ada@example.com')
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonMissingPath('data.password');

        $settings = InstanceSettings::sole();
        $this->assertSame('Acme Ops', $settings->name);
        $this->assertSame('America/Sao_Paulo', $settings->timezone);
        $this->assertSame('en', $settings->locale);

        $admin = User::sole();
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertAuthenticatedAs($admin);
    }

    public function test_locale_defaults_to_the_default_locale(): void
    {
        $payload = $this->validPayload();
        unset($payload['locale']);

        $this->fromSpa()->postJson('/api/setup', $payload)->assertCreated();

        $this->assertSame('en', InstanceSettings::sole()->locale);
    }

    public function test_setup_regenerates_the_session(): void
    {
        $guestSessionId = $this->sessionIdFrom($this->fromSpa()->get('/sanctum/csrf-cookie'));

        $response = $this->useSession($guestSessionId)->fromSpa()->postJson('/api/setup', $this->validPayload())->assertCreated();

        $this->assertNotSame($guestSessionId, $this->sessionIdFrom($response));
    }

    public function test_setup_cannot_run_twice(): void
    {
        $this->fromSpa()->postJson('/api/setup', $this->validPayload())->assertCreated();
        $this->withoutSession();

        $this->fromSpa()->postJson('/api/setup', $this->validPayload(['email' => 'mallory@example.com', 'instance_name' => 'Hijacked']))
            ->assertConflict()
            ->assertJsonStructure(['message']);

        $this->assertSame('Acme Ops', InstanceSettings::sole()->name);
        $this->assertDatabaseCount('users', 1);
    }

    public static function invalidPayloads(): array
    {
        return [
            'missing instance name' => [['instance_name' => null], 'instance_name'],
            'instance name too long' => [['instance_name' => str_repeat('a', 101)], 'instance_name'],
            'missing timezone' => [['timezone' => null], 'timezone'],
            'invalid timezone' => [['timezone' => 'Mars/Olympus_Mons'], 'timezone'],
            'unsupported locale' => [['locale' => 'xx'], 'locale'],
            'missing name' => [['name' => null], 'name'],
            'invalid email' => [['email' => 'not-an-email'], 'email'],
            'weak password' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
            'unconfirmed password' => [['password_confirmation' => 'different-pass-42'], 'password'],
        ];
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_payloads_are_rejected(array $overrides, string $field): void
    {
        $this->fromSpa()->postJson('/api/setup', $this->validPayload($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        $this->assertDatabaseCount('instance_settings', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_token_is_required_when_configured(): void
    {
        config(['pulsecheck.setup_token' => 'let-me-in']);

        $this->fromSpa()->getJson('/api/config')->assertJsonPath('data.setup_token_required', true);

        $this->fromSpa()->postJson('/api/setup', $this->validPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('token');

        $this->fromSpa()->postJson('/api/setup', $this->validPayload(['token' => 'wrong']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('token');

        $this->assertDatabaseCount('instance_settings', 0);

        $this->fromSpa()->postJson('/api/setup', $this->validPayload(['token' => 'let-me-in']))->assertCreated();
    }

    public function test_token_is_ignored_when_not_configured(): void
    {
        $this->fromSpa()->getJson('/api/config')->assertJsonPath('data.setup_token_required', false);

        $this->fromSpa()->postJson('/api/setup', $this->validPayload(['token' => 'anything']))->assertCreated();
    }

    public function test_a_token_is_generated_when_enabled_and_none_is_configured(): void
    {
        config(['pulsecheck.generate_setup_token' => true]);
        $token = SetupToken::current();

        $this->assertIsString($token);
        $this->assertSame(32, strlen($token));
        $this->fromSpa()->getJson('/api/config')->assertJsonPath('data.setup_token_required', true);
        $this->artisan('pulsecheck:setup-token')->expectsOutputToContain($token)->assertSuccessful();

        $this->fromSpa()->postJson('/api/setup', $this->validPayload(['token' => 'wrong']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('token');

        $this->fromSpa()->postJson('/api/setup', $this->validPayload(['token' => $token]))->assertCreated();

        $this->assertNull(SetupToken::current());
        $this->artisan('pulsecheck:setup-token')->expectsOutputToContain('already complete')->assertSuccessful();
    }

    public function test_configured_token_takes_precedence_over_the_generated_one(): void
    {
        config(['pulsecheck.generate_setup_token' => true, 'pulsecheck.setup_token' => 'let-me-in']);

        $this->assertSame('let-me-in', SetupToken::current());
    }

    public function test_config_reports_whether_setup_is_required(): void
    {
        $this->fromSpa()->getJson('/api/config')
            ->assertOk()
            ->assertJsonPath('data.setup_required', true)
            ->assertJsonPath('data.instance.name', 'PulseCheck');

        $this->fromSpa()->postJson('/api/setup', $this->validPayload())->assertCreated();

        $this->fromSpa()->getJson('/api/config')
            ->assertOk()
            ->assertJsonPath('data.setup_required', false)
            ->assertJsonPath('data.instance', ['name' => 'Acme Ops', 'timezone' => 'America/Sao_Paulo', 'locale' => 'en']);
    }

    public function test_setup_is_rate_limited(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->fromSpa()->postJson('/api/setup', ['instance_name' => ''])->assertUnprocessable();
        }

        $this->fromSpa()->postJson('/api/setup', $this->validPayload())->assertTooManyRequests();
    }
}
