<?php

namespace App\Mail;

use App\Models\InstanceSettings;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class MailSettingsTestMail extends Mailable
{
    public string $instanceName;

    public function __construct()
    {
        $this->instanceName = InstanceSettings::currentName();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "[{$this->instanceName}] ".__('Test email'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.settings-test');
    }
}
