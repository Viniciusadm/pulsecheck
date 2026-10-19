<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationType;
use App\Models\Incident;
use App\Models\Monitor;
use App\Notifications\AlertMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class AlertTranslationTest extends TestCase
{
    use RefreshDatabase;

    public function test_alerts_are_translated_for_portuguese_instances(): void
    {
        App::setLocale('pt-BR');
        $monitor = Monitor::factory()->down()->create(['name' => 'Loja', 'url' => 'https://loja.example.com']);
        $incident = Incident::factory()->for($monitor)->open()->create();

        $message = AlertMessage::fromIncident($incident, NotificationType::Down);

        $this->assertSame('🔴 Loja está FORA DO AR', $message->title);
        $this->assertArrayHasKey('Causa', $message->fields);
    }

    public function test_every_translation_keeps_its_placeholders(): void
    {
        $translations = json_decode(file_get_contents(lang_path('pt-BR.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ($translations as $source => $translation) {
            preg_match_all('/:[a-z]+/', $source, $expected);
            preg_match_all('/:[a-z]+/', $translation, $actual);
            sort($expected[0]);
            sort($actual[0]);

            $this->assertSame($expected[0], $actual[0], "Placeholders differ for \"{$source}\".");
        }
    }
}
