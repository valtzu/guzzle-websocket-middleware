<?php

namespace Valtzu\WebSocketMiddleware;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Random\Randomizer;

readonly class WebSocketMiddleware
{
    public function __construct(
        private Randomizer $randomizer = new Randomizer(),
    ) {
    }

    public function __invoke(callable $handler): callable
    {
        $randomizer = $this->randomizer;

        return static function (RequestInterface $request, array $options) use ($handler, $randomizer) {
            $scheme = $request->getUri()->getScheme();

            if (!in_array($scheme, ['ws', 'wss'])) {
                return $handler($request, $options);
            }

            $options['synchronous'] ??= false;
            $options['stream'] = true;
            $request = $request
                ->withUri($request->getUri()->withScheme(match ($scheme) { 'ws' => 'http', 'wss' => 'https' }))
                ->withHeader('Connection', 'Upgrade')
                ->withHeader('Upgrade', 'websocket')
                ->withHeader('Sec-WebSocket-Version', '13')
                ->withHeader('Sec-WebSocket-Key', $key = base64_encode($randomizer->getBytes(16)));

            return $handler($request, $options)
                ->then(
                    static function (ResponseInterface $response) use ($key, $options, $randomizer): ResponseInterface {
                        $responseCode = $response->getStatusCode();

                        if (!$responseCode || $responseCode >= 300) {
                            // HTTP errors are ignored / dealt with http_errors middleware
                            return $response;
                        }

                        if ($responseCode !== 101) {
                            throw new WebSocketHandshakeException("Server replied with $responseCode when 101 was expected");
                        }

                        $receivedToken = $response->getHeader('Sec-Websocket-Accept')[0] ?? '';
                        $expectedToken = base64_encode(sha1("{$key}258EAFA5-E914-47DA-95CA-C5AB0DC85B11", true));

                        if ($expectedToken !== $receivedToken) {
                            throw new WebSocketHandshakeException("Received Sec-Websocket-Accept value '$receivedToken' did not match the expected one");
                        }

                        return $response->withBody(new WebSocketStream($response->getBody(), $options, $randomizer));
                    }
                );
        };
    }
}
