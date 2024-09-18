## Connect to WebSocket endpoints with Guzzle HTTP client

**NOTE:** Only `StreamHandler` supported (so not `CurlHandler`!).

### Installation

```
composer install valtzu/guzzle-websocket-middleware
```

### Usage

Guzzle's `synchronous` option is used to configure the stream blocking option.

#### Synchronous usage

```php
$handlerStack = new HandlerStack(new StreamHandler());
$handlerStack->push(new WebSocketMiddleware());

$guzzle = new Client(['handler' => $handlerStack]);

$handshakeResponse = $guzzle->request('GET', 'wss://ws.postman-echo.com/raw');
$ws = $handshakeResponse->getBody();

$ws->write("Hello world");
$helloWorld = $ws->read(); // This will block until the reply frame is received
```

#### Asynchronous usage

```php
$handlerStack = new HandlerStack(new StreamHandler());
$handlerStack->push(new WebSocketMiddleware());

$guzzle = new Client(['handler' => $handlerStack]);

$handshakeResponse = $guzzle->requestAsync('GET', 'wss://ws.postman-echo.com/raw')->wait();
$ws = $handshakeResponse->getBody();

$ws->write("Hello world");
$helloWorld = $ws->read(); // Here you may get an empty string if data wasn't received yet
```

#### Connection upkeep / ping-pong

Whenever you read from a websocket stream, instead of receiving a text/binary frame, you may receive "ping" instead.
When this happens, we automatically respond with "pong". However, due to being PHP (usually) being single-threaded,
this means that you must make sure `read` (even with `0` length) is done frequently enough.

It's also possible to manually send a ping – however, it does not wait for the other party to reply.

```php
$ws->ping();
```
