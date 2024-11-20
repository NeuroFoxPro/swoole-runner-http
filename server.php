<?php
declare(strict_types=1);

use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;


$http = new Server('0.0.0.0', 9501);
$http->on('request', function (Request $request, Response $response) {
    $response->end('Composer OK');
});
$http->start();