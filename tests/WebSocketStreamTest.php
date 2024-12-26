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
        [$server, $client] = $this->createWebSocketPair(sync: true);

        $server->write('Hello');
        $this->assertSame('Hello', $client->read(5));
    }

    #[Test]
    public function asynchronousReadWrite()
    {
        [$server, $client] = $this->createWebSocketPair(sync: false);

        $client->ping();
        $client->write('Hello');

        $this->assertSame('Hello', $server->read(5));

        $client->close();
        $server->close();
    }

    /**
     * @see https://github.com/valtzu/guzzle-websocket-middleware/issues/2
     */
    #[Test]
    public function multipleMessagesInBuffer()
    {
        [$server, $client] = $this->createWebSocketPair(sync: true);

        $this->assertSame(5, $server->write('Hello'));
        $this->assertSame(5, $server->write('World'));
        $client->read(0);
        $this->assertSame('Hello', $client->read());
        $this->assertSame('World', $client->read());

        $client->close();
        $server->close();
    }

    /**
     * @return array{0: WebSocketStream, 1: WebSocketStream}
     */
    private function createWebSocketPair(bool $sync): array
    {
        $randomizer = new Randomizer(new Engine\Mt19937());

        $socketPair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        array_walk($socketPair, fn ($socket) => stream_set_timeout($socket, 1));

        return array_map(fn ($s) => new WebSocketStream(new Stream($s), ['synchronous' => $sync], $randomizer), $socketPair);
    }
}
