<?php

namespace NeuroFoxPro\Swoole\Runner\Http;

use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use Swoole\Http\Request;

final readonly class RequestFactory
{

    public function __construct(
        private ServerRequestFactoryInterface $serverRequestFactory,
        private UriFactoryInterface $uriFactory,
        private UploadedFileFactoryInterface $uploadedFileFactory,
        private StreamFactoryInterface $streamFactory,
    )
    {
    }

    public function create(Request $request): ServerRequestInterface
    {
        $server = $request->server;
        $headers = $request->header;
        $method = $server['request_method'] ?? null;
        if ($method === null) {
            throw new RuntimeException('Unable to determine HTTP request method.');
        }

        $serverRequest =
            $this->serverRequestFactory->createServerRequest($method, $this->createUri($server, $headers), $server);

        foreach ($headers as $name => $value) {
            if ($name === 'host' && $serverRequest->hasHeader('host')) {
                continue;
            }
            $serverRequest = $serverRequest->withAddedHeader($name, $value);
        }

        $protocol = '1.1';
        if (array_key_exists('server_protocol', $server) && $server['server_protocol'] !== '') {
            $protocol = str_replace('HTTP/', '', $server['server_protocol']);
        }
        $serverRequest = $serverRequest->withProtocolVersion($protocol);

        $body = $request->getContent();
        if ($body !== false) {
            $serverRequest = $serverRequest->withBody(
                $this->streamFactory->createStream($body)
            );
        }

        if ($method === 'POST') {
            $contentType = $serverRequest->getHeaderLine('content-type');
            if (preg_match('~^application/x-www-form-urlencoded($| |;)~', $contentType) || preg_match(
                    '~^multipart/form-data($| |;)~',
                    $contentType
                )) {
                $serverRequest = $serverRequest->withParsedBody($_POST);
            }
        }

        $serverRequest = $serverRequest->withQueryParams($request->get)->withCookieParams($request->cookie);

        $files = [];
        /** @psalm-suppress PossiblyInvalidArrayAccess,PossiblyInvalidArrayOffset It's bug in Psalm < 5 */
        foreach ($_FILES as $class => $info) {
            $files[$class] = [];
            $this->populateUploadedFileRecursive(
                $files[$class],
                $info['name'],
                $info['tmp_name'],
                $info['type'],
                $info['size'],
                $info['error'],
            );
        }
        return $serverRequest->withUploadedFiles($files);
    }

    private function createUri($server, $headers): UriInterface
    {
        $uri = $this->uriFactory->createUri();

        if (array_key_exists('https', $server) && $server['https'] !== '' && $server['https'] !== 'off') {
            $uri = $uri->withScheme('https');
        }
        else {
            $uri = $uri->withScheme('http');
        }

        $uri = isset($server['server_port']) ? $uri->withPort((int) $server['server_port']) : $uri->withPort(
            $uri->getScheme() === 'https' ? 443 : 80
        );


        $uri = preg_match('/^(.+):(\d+)$/', $headers['host'], $matches) === 1 ? $uri->withHost($matches[1])->withPort(
            (int) $matches[2]
        ) : $uri->withHost($headers['host']);


        if (isset($server['request_uri'])) {
            $uri = $uri->withPath(explode('?', $server['request_uri'])[0]);
        }

        if (isset($server['query_string'])) {
            $uri = $uri->withQuery($server['query_string']);
        }

        return $uri;
    }

    private function populateUploadedFileRecursive(
        array &$files,
        mixed $names,
        mixed $tempNames,
        mixed $types,
        mixed $sizes,
        mixed $errors
    ): void {
        if (is_array($names)) {
            /** @var array|string $name */
            foreach ($names as $i => $name) {
                $files[$i] = [];
                /** @psalm-suppress MixedArrayAccess */
                $this->populateUploadedFileRecursive(
                    $files[$i],
                    $name,
                    $tempNames[$i],
                    $types[$i],
                    $sizes[$i],
                    $errors[$i],
                );
            }

            return;
        }

        try {
            $stream = $this->streamFactory->createStreamFromFile($tempNames);
        } catch (RuntimeException) {
            $stream = $this->streamFactory->createStream();
        }

        $files = $this->uploadedFileFactory->createUploadedFile(
            $stream,
            (int) $sizes,
            (int) $errors,
            $names,
            $types
        );
    }
}
