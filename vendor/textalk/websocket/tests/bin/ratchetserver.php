<?php

/**
 * This file is constructed to avoid issues with using the provided Client towards a Ratchet server.
 */

require(dirname(dirname(dirname(__FILE__))) . '/vendor/autoload.php');

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class Chat implements MessageComponentInterface {
  public function onMessage(ConnectionInterface $from, $message) {
    if ($message === 'exit') exit;

    if ($message === 'Dump headers') $from->send($from->WebSocket->request->getRawHeaders());
    elseif ($auth = $from->WebSocket->request->getHeader('Authorization')) {
      $from->send("$auth - $message");
    }
    else $from->send($message);
  }

  public function onOpen(ConnectionInterface $conn) {}
  public function onClose(ConnectionInterface $conn) {}
  public function onError(ConnectionInterface $conn, \Exception $e) {}
}

$port = 8000;
$server = null;

while (!$server && $port++ < 8100) {
  try {
    $server = IoServer::factory(new HttpServer(new WsServer(new Chat())), $port);
    echo "$port\n";
  }
  catch (React\Socket\ConnectionException $e) {}
}

$server->run();
