<?php

namespace Tests\Feature\Mail;

use App\Enums\MailDriver;
use App\Mail\MailSettingsTestMail;
use App\Models\MailSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MailSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    private function smtpPayload(array $config = []): array
    {
        return [
            'driver' => 'smtp',
            'from_address' => 'alerts@acme.test',
            'from_name' => 'Acme Alerts',
            'config' => ['host' => 'smtp.acme.test', 'port' => 587, 'scheme' => null, 'username' => 'mailer', 'password' => 's3cret', ...$config],
        ];
    }

    public function test_show_reports_environment_when_nothing_is_saved(): void
    {
        $this->actingAs(User::factory()->admin()->create())->fromSpa()
            ->getJson('/api/settings/mail')
            ->assertOk()
            ->assertJsonPath('data.source', 'environment')
            ->assertJsonPath('data.driver', null)
            ->assertJsonPath('data.environment_driver', config('mail.default'));
    }

    public function test_admin_saves_smtp_settings_without_exposing_the_password(): void
    {
        $this->actingAs(User::factory()->admin()->create())->fromSpa()
            ->putJson('/api/settings/mail', $this->smtpPayload())
            ->assertOk()
            ->assertJsonPath('data.source', 'panel')
            ->assertJsonPath('data.driver', 'smtp')
            ->assertJsonPath('data.config.host', 'smtp.acme.test')
            ->assertJsonPath('data.secrets_set', ['password'])
            ->assertJsonMissingPath('data.config.password');

        $settings = MailSettings::sole();
        $this->assertSame('s3cret', $settings->config['password']);
        $this->assertStringNotContainsString('s3cret', (string) $settings->getRawOriginal('config'));
    }

    public function test_blank_secret_keeps_the_stored_value(): void
    {
        MailSettings::create(['driver' => MailDriver::Smtp, 'from_address' => 'a@acme.test', 'config' => ['host' => 'old.test', 'port' => 25, 'password' => 'kept']]);

        $this->actingAs(User::factory()->admin()->create())->fromSpa()
            ->putJson('/api/settings/mail', $this->smtpPayload(['password' => null]))
            ->assertOk();

        $config = MailSettings::sole()->config;
        $this->assertSame('kept', $config['password']);
        $this->assertSame('smtp.acme.test', $config['host']);
    }

    public function test_changing_driver_replaces_the_config_and_requires_the_new_secret(): void
    {
        MailSettings::create(['driver' => MailDriver::Smtp, 'from_address' => 'a@acme.test', 'config' => ['host' => 'old.test', 'port' => 25, 'password' => 'old']]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->fromSpa()
            ->putJson('/api/settings/mail', ['driver' => 'resend', 'from_address' => 'a@acme.test', 'config' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('config.key');

        $this->actingAs($admin)->fromSpa()
            ->putJson('/api/settings/mail', ['driver' => 'resend', 'from_address' => 'a@acme.test', 'config' => ['key' => 're_123', 'host' => 'ignored']])
            ->assertOk()
            ->assertJsonPath('data.secrets_set', ['key']);

        $this->assertSame(['key' => 're_123'], MailSettings::sole()->config);
    }

    public static function invalidPayloads(): array
    {
        return [
            'unknown driver' => [['driver' => 'pigeon'], 'driver'],
            'invalid from address' => [['from_address' => 'nope'], 'from_address'],
            'smtp without host' => [['config' => ['port' => 25]], 'config.host'],
            'smtp invalid port' => [['config' => ['host' => 'h', 'port' => 70000]], 'config.port'],
            'smtp invalid scheme' => [['config' => ['host' => 'h', 'port' => 25, 'scheme' => 'tls']], 'config.scheme'],
            'mailgun invalid endpoint' => [['driver' => 'mailgun', 'config' => ['domain' => 'mg.acme.test', 'secret' => 'x', 'endpoint' => 'evil.test']], 'config.endpoint'],
            'mailgun without secret' => [['driver' => 'mailgun', 'config' => ['domain' => 'mg.acme.test', 'endpoint' => 'api.mailgun.net']], 'config.secret'],
            'postmark without token' => [['driver' => 'postmark', 'config' => []], 'config.token'],
            'ses invalid region' => [['driver' => 'ses', 'config' => ['key' => 'AKIA', 'secret' => 'x', 'region' => 'mars']], 'config.region'],
        ];
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_payloads_are_rejected(array $overrides, string $field): void
    {
        $this->actingAs(User::factory()->admin()->create())->fromSpa()
            ->putJson('/api/settings/mail', array_merge($this->smtpPayload(), $overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    }

    public function test_destroy_returns_to_environment_settings(): void
    {
        MailSettings::create(['driver' => MailDriver::Resend, 'from_address' => 'a@acme.test', 'config' => ['key' => 're_123']]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->fromSpa()->deleteJson('/api/settings/mail')->assertNoContent();

        $this->assertDatabaseCount('mail_settings', 0);
        $this->actingAs($admin)->fromSpa()->getJson('/api/settings/mail')->assertJsonPath('data.source', 'environment');
    }

    public function test_test_endpoint_sends_an_email_to_the_admin(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create(['email' => 'admin@acme.test']);

        $this->actingAs($admin)->fromSpa()
            ->postJson('/api/settings/mail/test')
            ->assertOk();

        Mail::assertSent(MailSettingsTestMail::class, fn (MailSettingsTestMail $mail) => $mail->hasTo('admin@acme.test'));
    }

    public function test_test_endpoint_reports_delivery_failures(): void
    {
        MailSettings::create(['driver' => MailDriver::Smtp, 'from_address' => 'a@acme.test', 'config' => ['host' => '127.0.0.1', 'port' => 1, 'password' => 'hidden-pass']]);

        $this->actingAs(User::factory()->admin()->create())->fromSpa()
            ->postJson('/api/settings/mail/test')
            ->assertUnprocessable()
            ->assertJsonMissing(['hidden-pass']);
    }

    public function test_non_admins_cannot_manage_mail_settings(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->fromSpa()->getJson('/api/settings/mail')->assertForbidden();
        $this->actingAs($member)->fromSpa()->putJson('/api/settings/mail', $this->smtpPayload())->assertForbidden();
        $this->actingAs($member)->fromSpa()->deleteJson('/api/settings/mail')->assertForbidden();
        $this->actingAs($member)->fromSpa()->postJson('/api/settings/mail/test')->assertForbidden();
    }

    public function test_public_config_does_not_expose_mail_settings(): void
    {
        MailSettings::create(['driver' => MailDriver::Smtp, 'from_address' => 'a@acme.test', 'config' => ['host' => 'smtp.secret-host.test', 'port' => 25, 'password' => 'p']]);

        $this->fromSpa()->getJson('/api/config')->assertDontSee('secret-host');
    }
}
