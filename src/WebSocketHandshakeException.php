<?php

namespace Valtzu\WebSocketMiddleware;

use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;

class WebSocketHandshakeException extends RuntimeException implements ClientExceptionInterface
{
}
