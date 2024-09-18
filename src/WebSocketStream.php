<?php

namespace Valtzu\WebSocketMiddleware;

use GuzzleHttp\Psr7\BufferStream;
use GuzzleHttp\Psr7\Stream;
use Psr\Http\Message\StreamInterface;
use Random\Randomizer;
use RuntimeException;

readonly class WebSocketStream implements StreamInterface
{
    public function __construct(
        private StreamInterface $connection,
        array $options = [],
        private Randomizer $randomizer = new Randomizer(),
        private BufferStream $buffer = new BufferStream(),
    ) {
        if ($connection instanceof Stream) {
            (function (bool $blocking) {
                $this->writable = true;
                stream_set_blocking($this->stream, $blocking);
            })->bindTo($connection, Stream::class)->call($connection, $options['synchronous']);
        }
    }

    public function __toString(): string
    {
        return $this->getContents();
    }

    public function close(): void
    {
        // We attempt a clean close, but don't fail if it causes an error
        try {
            $this->send("\x08", "\x03\xE9"); // close with going-away status
            $this->read(2);
        } catch (RuntimeException) {}

        $this->connection->close();
    }

    public function detach()
    {
        return $this->connection->detach();
    }

    public function getSize(): ?int
    {
        return null;
    }

    public function tell(): int
    {
        throw new RuntimeException('Stream is not seekable');
    }

    public function eof(): bool
    {
        return $this->connection->eof();
    }

    public function isSeekable(): bool
    {
        return false;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        throw new RuntimeException('Stream is not seekable');
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function isWritable(): bool
    {
        return $this->connection->isWritable();
    }

    public function write(string $string): int
    {
        return $this->send("\x01", $string);
    }

    public function isReadable(): bool
    {
        return $this->connection->isReadable();
    }

    public function read(?int $length = null): string
    {
        $length ??= ($this->buffer->getMetadata('hwm') - $this->buffer->getSize());

        if ($length > 0 && $this->buffer->getSize() >= $length) {
            return $this->buffer->read($length);
        }

        $blocking = $this->connection->getMetadata('blocking');

        do {
            $payload = '';
            $opcode = null;
            do {
                $header = $this->readExact(2);
                if ($opcode === null && $header === '') {
                    return '';
                }
                $opcode ??= $header[0] & "\x0f";
                [, $payloadLength] = match ($payloadLength = ($header[1] & "\x7f")) {
                    "\x7e" => unpack('n', $this->readExact(2)),
                    "\x7f" => unpack('J', $this->readExact(8)),
                    default => unpack('C', $payloadLength),
                };

                $maskingKey = ($header[1] & "\x80") === "\x80" ? $this->readExact(4) : "\0\0\0\0";
                if ($payloadLength > 0) {
                    $payload .= $this->readExact($payloadLength) ^ str_repeat($maskingKey, ($payloadLength >> 2) + 1);
                }
            } while (!($header[0] & "\x80"));

            $this->reply($opcode, $payload);
        } while ($opcode === "\x09" || !in_array($opcode, ["\1", "\2"]) && $blocking);

        $this->buffer->write($payload);

        return $this->buffer->read($length);
    }

    public function getContents(): string
    {
        return $this->read();
    }

    public function getMetadata(?string $key = null)
    {
        return $this->connection->getMetadata($key);
    }

    public function ping(string $payload = ''): void
    {
        $this->send("\x09", $payload);
    }

    private function send(string $opCode, string $payload): int
    {
        $payloadLength = strlen($payload);

        $this->connection->write(
            ("\x80" | $opCode)
            . match (true) {
                $payloadLength < 0x7e => pack('C', $payloadLength | 0x80),
                $payloadLength <= 0xffff => pack('Cn', 0xfe, $payloadLength),
                default => pack('CJ', 0xff, $payloadLength),
            }
            . ($maskingKey = $this->randomizer->getBytes(4))
            . ($payload ^ str_repeat($maskingKey, ($payloadLength >> 2) + 1)),
        );

        return $payloadLength;
    }

    private function reply(string $opcode, string $payload): void
    {
        match ($opcode) {
            "\x09" => $this->send("\x0A", $payload),
            default => 0,
        };
    }

    private function readExact(int $length): string
    {
        for ($data = '', $left = $length; $left > 0; $data .= $buffer, $left -= strlen($buffer)) {
            $buffer = $this->connection->read($left);
            if ($this->handleReadError($buffer, $length, $left) === '') {
                return '';
            }
        }

        return $data;
    }

    private function handleReadError(string|false $readResult, int $requestedBytes, int $bytesLeft): string
    {
        if ($readResult === false) {
            if ($this->connection->getMetadata('timed_out')) {
                throw new RuntimeException('Read timeout');
            }

            throw new RuntimeException("Wanted to read $requestedBytes bytes but missed $bytesLeft");
        }

        if ($readResult === '' && $requestedBytes !== $bytesLeft) {
            throw new RuntimeException("Empty read; connection dead?");
        }

        return $readResult;
    }
}
