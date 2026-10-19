<?php

namespace Tests\Concerns;

use App\Security\SocketOpener;
use ArrayObject;
use Throwable;

trait FakesSockets
{
    protected ArrayObject $socketProbes;

    protected function fakeSockets(array|Throwable|null ...$results): void
    {
        $this->socketProbes = new ArrayObject;

        $this->app->instance(SocketOpener::class, new class($results, $this->socketProbes) extends SocketOpener
        {
            public function __construct(private array $results, private ArrayObject $probes) {}

            public function probe(string $address, float $timeout, array $sslOptions = []): ?array
            {
                $this->probes->append(['address' => $address, 'timeout' => $timeout, 'ssl' => $sslOptions]);
                $result = array_shift($this->results);

                if ($result instanceof Throwable) {
                    throw $result;
                }

                return $result;
            }
        });
    }
}
