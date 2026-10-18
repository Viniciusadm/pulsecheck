<?php

namespace Tests\Concerns;

use App\Security\DnsResolver;
use ArrayObject;

trait FakesDns
{
    protected ArrayObject $dnsLookups;

    protected function fakeDns(array $records): void
    {
        $this->dnsLookups = new ArrayObject;

        $this->app->instance(DnsResolver::class, new class($records, $this->dnsLookups) extends DnsResolver
        {
            public function __construct(private array $records, private ArrayObject $lookups) {}

            public function resolve(string $host): array
            {
                $this->lookups->append($host);

                return $this->records[$host] ?? [];
            }
        });
    }
}
