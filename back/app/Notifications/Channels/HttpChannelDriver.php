<?php

namespace App\Notifications\Channels;

use App\Exceptions\NotificationDeliveryException;
use App\Security\Exceptions\UnsafeUrlException;
use App\Security\SafeHttpClient;
use Throwable;

abstract class HttpChannelDriver implements ChannelDriver
{
    public function __construct(private SafeHttpClient $client) {}

    protected function post(string $url, array $payload, array $headers = []): void
    {
        try {
            $response = $this->client->post($url, $payload, $headers);
        } catch (UnsafeUrlException) {
            throw NotificationDeliveryException::rejected();
        } catch (Throwable) {
            throw NotificationDeliveryException::unavailable();
        }

        if ($response->statusCode < 200 || $response->statusCode >= 300) {
            throw NotificationDeliveryException::forStatus($response->statusCode);
        }
    }
}
