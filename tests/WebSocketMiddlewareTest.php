<?php

namespace Valtzu\WebSocketMiddleware\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Random\Engine;
use Random\Randomizer;
use Valtzu\WebSocketMiddleware\WebSocketHandshakeException;
use Valtzu\WebSocketMiddleware\WebSocketMiddleware;

class WebSocketMiddlewareTest extends TestCase
{
    #[Test]
    public function unexpectedHttpCode()
    {
        $guzzle = new Client(['handler' => $this->createHandlerStack(new Response(status: 200))]);

        $this->expectException(WebSocketHandshakeException::class);
        $this->expectExceptionMessage('Server replied with 200 when 101 was expected');
        $guzzle->request('GET', 'wss://whatever.local/ws');
    }

    #[Test]
    public function httpError()
    {
        $guzzle = new Client(['handler' => $this->createHandlerStack(new Response(status: 404))]);

        $this->assertSame(404, $guzzle->request('GET', 'wss://whatever.local/ws')->getStatusCode());
    }

    #[Test]
    public function handshakeHashMismatch()
    {
        $guzzle = new Client(['handler' => $this->createHandlerStack(new Response(status: 101, headers: ['Sec-Websocket-Accept' => 'foo']))]);

        $this->expectException(WebSocketHandshakeException::class);
        $this->expectExceptionMessage("Received Sec-Websocket-Accept value 'foo' did not match the expected one");
        $guzzle->request('GET', 'wss://whatever.local/ws');
    }

    private function createHandlerStack(ResponseInterface ...$mockResponse): HandlerStack
    {
        $handlerStack = new HandlerStack(new MockHandler($mockResponse));
        $handlerStack->push(new WebSocketMiddleware($this->createPredictableRandomizer()));

        return $handlerStack;
    }

    private function createPredictableRandomizer(): Randomizer
    {
        $randomEngine = $this->createMock(Engine::class);
        $randomEngine->method('generate')->willReturn("\0");
        return new Randomizer($randomEngine);
    }
}
