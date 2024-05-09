<?php

namespace Valtzu\WebSocketMiddleware\Tests;

use GuzzleHttp\Psr7\Stream;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Random\Engine;
use Random\Randomizer;
use Valtzu\WebSocketMiddleware\WebSocketStream;

class WebSocketStreamTest extends TestCase
{
    #[Test]
    public function synchronousReadWrite()
    {
        [$server, $client] = $this->createWebSocketPair();

        $server->write('Hello');
        $this->assertSame('Hello', $client->read(5));
    }

    #[Test]
    public function asynchronousReadWrite()
    {
        [$server, $client] = $this->createWebSocketPair();

        $client->ping();
        $client->write('Hello');

        $this->assertSame('Hello', $server->read(5));

        $client->close();
        $server->close();
    }

    private function createWebSocketPair(): array
    {
        $randomizer = new Randomizer(new Engine\Mt19937());

        return array_map(fn ($s) => new WebSocketStream(new Stream($s), ['synchronous' => true], $randomizer), stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP));
    }
}
