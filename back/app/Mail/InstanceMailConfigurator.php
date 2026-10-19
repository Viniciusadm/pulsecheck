<?php

namespace App\Mail;

use App\Models\MailSettings;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Mail\Factory;

class InstanceMailConfigurator
{
    public const MAILER = 'panel';

    private string $environmentMailer;

    private array $environmentFrom;

    public function __construct(private Repository $config)
    {
        $this->environmentMailer = (string) $config->get('mail.default');
        $this->environmentFrom = (array) $config->get('mail.from');
    }

    public function environmentMailer(): string
    {
        return $this->environmentMailer;
    }

    public function apply(Factory $manager): void
    {
        $settings = MailSettings::current();

        $manager->purge(self::MAILER);

        if ($settings === null) {
            $this->config->set('mail.default', $this->environmentMailer);
            $this->config->set('mail.from', $this->environmentFrom);
            $this->config->set('mail.mailers.'.self::MAILER, null);

            return;
        }

        $from = ['address' => $settings->from_address, 'name' => $settings->from_name ?: $this->environmentFrom['name'] ?? null];

        $this->config->set('mail.mailers.'.self::MAILER, [...$settings->driver->toMailerConfig($settings->config ?? []), 'from' => $from]);
        $this->config->set('mail.from', $from);
        $this->config->set('mail.default', self::MAILER);
    }
}
