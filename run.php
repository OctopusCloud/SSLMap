<?php

// run like php run.php 8080

require __DIR__ . '/vendor/autoload.php';
$loop = React\EventLoop\Factory::create();
$browser = new \Clue\React\Buzz\Browser($loop);


$staticFileDeliveryHelper = new \APPNAME\Helper\StaticFileDeliveryHelper();
$errorPageHelper = new \APPNAME\Helper\ErrorPageHelper();
$sseConnectionHelper = new \APPNAME\Helper\SSEConnectionHelper();




$broadcastStream = new \React\Stream\ThroughStream(function ($data) {
    return $data;
});


$loop = \React\EventLoop\Factory::create();
$reactConnector = new \React\Socket\Connector($loop, [
    'dns' => '8.8.8.8',
    'timeout' => 10
]);
$connector = new \Ratchet\Client\Connector($loop, $reactConnector);



$connector('wss://certstream.calidog.io/')
    ->then(function(Ratchet\Client\WebSocket $conn) use($broadcastStream) {
        $conn->on('message', function(\Ratchet\RFC6455\Messaging\MessageInterface $msg) use ($conn, $broadcastStream) {
            $content = $msg->getPayload();

            $data = json_decode($content,true);
            if ($data) {
                if (count($data) === 0 || $data['message_type'] === 'heartbeat') {
                    return;
                }

                $broadcastStream->write($data);
            }
        });

        $conn->on('close', function($code = null, $reason = null) {
            echo "Connection closed ({$code} - {$reason})\n";
        });

        $conn->send('Hello World!');
    }, function(\Exception $e) use ($loop) {
        echo "Could not connect: {$e->getMessage()}\n";
        $loop->stop();
    });






$server = new \React\Http\Server(function (\Psr\Http\Message\ServerRequestInterface $request) use ($browser,$broadcastStream, $loop,$staticFileDeliveryHelper, $errorPageHelper, $sseConnectionHelper) {
    // normal http requests
    if ($staticFileDeliveryHelper->isStaticFile($request)) {
        return $staticFileDeliveryHelper->deliverStaticFile($request);
    }
    // filter non sse connections
    if (!$sseConnectionHelper->isSSEConnectionRequest($request)) {
        return $errorPageHelper->return404Page($request);
    }

    return $sseConnectionHelper->handleIncommingConnection($request, $loop,$browser, $broadcastStream);
});

$port = isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : 0;

$socket = new React\Socket\Server($port, $loop);
$server->listen($socket);

$server->on('error', function (Throwable $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});

$loop->run();
