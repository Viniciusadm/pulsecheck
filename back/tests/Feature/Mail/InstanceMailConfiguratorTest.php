<?php

namespace Tests\Feature\Mail;

use App\Enums\MailDriver;
use App\Mail\InstanceMailConfigurator;
use App\Models\MailSettings;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class InstanceMailConfiguratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_saved_settings_override_the_environment_mailer(): void
    {
        $environmentMailer = config('mail.default');

        MailSettings::create(['driver' => MailDriver::Smtp, 'from_address' => 'alerts@acme.test', 'from_name' => 'Acme', 'config' => ['host' => 'smtp.acme.test', 'port' => '2525', 'password' => 'p']]);
        app('mail.manager');

        $this->assertSame(InstanceMailConfigurator::MAILER, config('mail.default'));
        $this->assertSame('smtp.acme.test', config('mail.mailers.panel.host'));
        $this->assertSame(2525, config('mail.mailers.panel.port'));
        $this->assertSame(['address' => 'alerts@acme.test', 'name' => 'Acme'], config('mail.from'));

        MailSettings::sole()->delete();

        $this->assertSame($environmentMailer, config('mail.default'));
        $this->assertNull(config('mail.mailers.panel'));
    }

    public function test_the_mailer_uses_the_configured_transport(): void
    {
        MailSettings::create(['driver' => MailDriver::Resend, 'from_address' => 'alerts@acme.test', 'config' => ['key' => 're_123']]);

        $this->assertStringContainsString('resend', (string) app('mail.manager')->mailer()->getSymfonyTransport());
    }

    public function test_queue_jobs_pick_up_changes_made_elsewhere(): void
    {
        MailSettings::create(['driver' => MailDriver::Smtp, 'from_address' => 'old@acme.test', 'config' => ['host' => 'old.test', 'port' => 25]]);
        app('mail.manager');

        DB::table('mail_settings')->update(['from_address' => 'new@acme.test']);
        $this->assertSame('old@acme.test', config('mail.from.address'));

        event(new JobProcessing('redis', Mockery::mock(Job::class)->shouldIgnoreMissing()));

        $this->assertSame('new@acme.test', config('mail.from.address'));
    }
}
