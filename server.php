<?php
declare(strict_types=1);

use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;


$http = new Server('0.0.0.0', 80);
$http->on('request', function (Request $request, Response $response) {
    //
    switch ($request->server['request_uri']) {
        case '/':
            $response->header('Content-Type', 'text/html; charset=utf-8');
            $response->end(file_get_contents(__DIR__. '/index.html'));
            break;
        case '/test':
            $response->write(print_r($request, true) . PHP_EOL);
            $response->write(print_r($response, true) . PHP_EOL);
            $response->end('');
            break;
    }
});
$http->start();
