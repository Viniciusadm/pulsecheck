<?php

namespace App\Console\Commands;

use App\Models\InstanceSettings;
use App\Support\SetupToken;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('pulsecheck:setup-token')]
#[Description('Show the token required to complete the first-run setup')]
class ShowSetupToken extends Command
{
    public function handle(): int
    {
        if (InstanceSettings::current() !== null) {
            $this->components->info('Setup is already complete.');

            return self::SUCCESS;
        }

        $token = SetupToken::current();

        if ($token === null) {
            $this->components->warn('Setup does not require a token. Anyone who reaches this instance can complete it.');

            return self::SUCCESS;
        }

        $this->components->info("Setup token: {$token}");

        return self::SUCCESS;
    }
}
