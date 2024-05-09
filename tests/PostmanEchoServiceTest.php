<?php

namespace Valtzu\WebSocketMiddleware\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\StreamHandler;
use GuzzleHttp\HandlerStack;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Valtzu\WebSocketMiddleware\WebSocketMiddleware;
use Valtzu\WebSocketMiddleware\WebSocketStream;

class PostmanEchoServiceTest extends TestCase
{
    #[Test]
    public function synchronousOpenSendAndReceive()
    {
        $handlerStack = new HandlerStack(new StreamHandler());
        $handlerStack->push(new WebSocketMiddleware());

        $guzzle = new Client(['handler' => $handlerStack]);

        $handshakeResponse = $guzzle->request('GET', 'wss://ws.postman-echo.com/raw');
        $ws = $handshakeResponse->getBody();

        $this->assertTrue($ws->isWritable());
        $this->assertSame(5, $ws->write("Hello"));
        $this->assertSame("Hel", $ws->read(3));
        $this->assertSame("lo", $ws->read(2));
        $this->assertSame(6, $ws->write("Worlds"));
        $this->assertSame("Worlds", $ws->read(128));
        $ws->close();
    }

    #[Test]
    public function asynchronousOpenSendAndReceive()
    {
        $handlerStack = new HandlerStack(new StreamHandler());
        $handlerStack->push(new WebSocketMiddleware());

        $guzzle = new Client(['handler' => $handlerStack]);

        /** @var WebSocketStream $asyncWs */
        $asyncWs = $guzzle->requestAsync('GET', 'wss://ws.postman-echo.com/raw')
            ->then(fn (ResponseInterface $handshakeResponse) => $handshakeResponse->getBody())
            ->wait();

        $this->assertTrue($asyncWs->isWritable());
        $this->assertSame(5, $asyncWs->write("Hello"));
        sleep(1);
        $this->assertSame("Hel", $asyncWs->read(3));
        $this->assertSame("lo", $asyncWs->read(2));
        $this->assertSame(6, $asyncWs->write("Worlds"));
        sleep(1);
        $this->assertSame("Worlds", $asyncWs->read(128));
        $asyncWs->close();
    }
}
