<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

try {

    $config = new SocketIO\Engine\Payload\ConfigPayload();
    $config
        // server worker_num
        ->setWorkerNum(2)
        // server daemonize
        ->setDaemonize(0);

    $io = new SocketIO\Server(9501, $config);
    $io->of('/test')->on('new message', function (SocketIO\Server $socket) {
        $socket->emit('new message', [
            'data' => $socket->getMessage()
        ]);
    });

    $io->on('new user', function (SocketIO\Server $socket) {
        $socket->broadcast('hello');
    });

    $io->start();

} catch (Exception $e) {
    echo $e->getMessage();
    echo $e->getTraceAsString();
}