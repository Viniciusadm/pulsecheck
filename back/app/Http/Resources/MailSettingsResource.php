<?php

namespace App\Http\Resources;

use App\Mail\InstanceMailConfigurator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class MailSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if ($this->resource === null) {
            return [
                'source' => 'environment',
                'environment_driver' => app(InstanceMailConfigurator::class)->environmentMailer(),
                'driver' => null,
                'from_address' => null,
                'from_name' => null,
                'config' => (object) [],
                'secrets_set' => [],
            ];
        }

        $config = $this->resource->config ?? [];
        $secretKeys = $this->resource->driver->secretKeys();

        return [
            'source' => 'panel',
            'environment_driver' => app(InstanceMailConfigurator::class)->environmentMailer(),
            'driver' => $this->resource->driver,
            'from_address' => $this->resource->from_address,
            'from_name' => $this->resource->from_name,
            'config' => (object) Arr::except($config, $secretKeys),
            'secrets_set' => array_values(array_filter($secretKeys, fn (string $key) => ! empty($config[$key]))),
        ];
    }
}
