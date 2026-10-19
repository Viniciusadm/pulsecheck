<?php

namespace Tests\Feature\Security;

use App\Security\Exceptions\SocketProbeException;
use App\Security\SocketOpener;
use Tests\TestCase;

class SocketOpenerTest extends TestCase
{
    public function test_open_port_is_reached_and_closed_port_is_refused(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        $address = 'tcp://'.stream_socket_get_name($server, false);

        $this->assertNull((new SocketOpener)->probe($address, 2));

        fclose($server);

        try {
            (new SocketOpener)->probe($address, 2);
            $this->fail('The closed port should refuse the connection.');
        } catch (SocketProbeException $exception) {
            $this->assertSame(SocketProbeException::REFUSED, $exception->kind);
        }
    }
}
