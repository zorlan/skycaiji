<?php

require(dirname(dirname(__FILE__)) . '/vendor/autoload.php');

use WebSocket\Client;

$client = new Client("ws://localhost:{$argv[1]}");

$client->send($argv[2]);

echo $client->receive();
