<?php

namespace NeuroFoxPro\Swoole\Runner\Http;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Swoole\Http\Response;
use Yiisoft\Http\Status;

final class SapiEmitter
{
    private const array NO_BODY_RESPONSE_CODES = [
        Status::CONTINUE,
        Status::SWITCHING_PROTOCOLS,
        Status::PROCESSING,
        Status::NO_CONTENT,
        Status::RESET_CONTENT,
        Status::NOT_MODIFIED,
    ];

    private const int DEFAULT_BUFFER_SIZE = 8_388_608; // 8MB

    private int $bufferSize;
    private Response $response;

    public function __construct(Response $response, int $bufferSize = null)
    {
        if ($bufferSize !== null && $bufferSize < 1) {
            throw new InvalidArgumentException('Buffer size must be greater than zero.');
        }

        $this->response = $response;
        $this->bufferSize = $bufferSize ?? self::DEFAULT_BUFFER_SIZE;
    }

    public function emit(ResponseInterface $response, bool $withoutBody = false): void
    {
        $status = $response->getStatusCode();
        $withoutBody = $withoutBody || !$this->shouldOutputBody($response);
        $withoutContentLength = $withoutBody || $response->hasHeader('Transfer-Encoding');

        if ($withoutContentLength) {
            $response = $response->withoutHeader('Content-Length');
        }

        foreach ($response->getHeaders() as $header => $values) {
            foreach ($values as $value) {
                $this->response->header($header, $value);
            }
        }

        $this->response->status($status, Status::TEXTS[$status] ?? '');

        if ($withoutBody) {
            $this->response->end();
            return;
        }

        if (!$withoutContentLength && !$response->hasHeader('Content-Length')) {
            $contentLength = $response->getBody()->getSize();

            if ($contentLength !== null) {
                $this->response->header("Content-Length", $contentLength);
            }
        }

        $this->emitBody($response);
        $this->response->end();
    }

    private function emitBody(ResponseInterface $response): void
    {
        $body = $response->getBody();

        if ($body->isSeekable()) {
            $body->rewind();
        }

        $size = $body->getSize();
        if ($size !== null && $size <= $this->bufferSize) {
            $this->response->write($body->getContents());
            return;
        }

        while (!$body->eof()) {
            $this->response->write($body->read($this->bufferSize));
        }

    }


    private function shouldOutputBody(ResponseInterface $response): bool
    {
        if (in_array($response->getStatusCode(), self::NO_BODY_RESPONSE_CODES, true)) {
            return false;
        }

        $body = $response->getBody();

        if (!$body->isReadable()) {
            return false;
        }

        $size = $body->getSize();

        if ($size !== null) {
            return $size > 0;
        }

        if ($body->isSeekable()) {
            $body->rewind();
            $byte = $body->read(1);

            if ($byte === '' || $body->eof()) {
                return false;
            }
        }

        return true;
    }


}
