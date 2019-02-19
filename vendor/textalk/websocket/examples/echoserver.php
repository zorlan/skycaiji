<?php

/**
 * This file is used for the tests, but can also serve as an example of a WebSocket\Server.
 */

$GLOBALS['PHPUNIT_COVERAGE_DATA_DIRECTORY'] = dirname(dirname(__FILE__)) . '/build/tmp';

require(dirname(dirname(__FILE__)) . '/vendor/autoload.php');

use WebSocket\Server;

// Setting timeout to 200 seconds to make time for all tests and manual runs.
$server = new Server(array('timeout' => 200));

echo $server->getPort(), "\n";

while ($connection = $server->accept()) {
  $test_id = $server->getPath();
  $test_id = substr($test_id, 1);

  if (function_exists('xdebug_get_code_coverage'))
    xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);

  if (class_exists('PHPUnit_Extensions_SeleniumCommon_ExitHandler'))
    PHPUnit_Extensions_SeleniumCommon_ExitHandler::init();

  try {
    while(1) {
      $message = $server->receive();
      echo "Received $message\n\n";

      if ($message === 'exit') {
        echo microtime(true), " Client told me to quit.  Bye bye.\n";
        echo microtime(true), " Close response: ", $server->close(), "\n";
        echo microtime(true), " Close status: ", $server->getCloseStatus(), "\n";
        save_coverage_data($test_id);
        exit;
      }

      if ($message === 'Dump headers') {
        $server->send(implode("\r\n", $server->getRequest()));
      }
      elseif ($auth = $server->getHeader('Authorization')) {
        $server->send("$auth - $message", 'text', false);
      }
      else {
        $server->send($message, 'text', false);
      }
    }
  }
  catch (WebSocket\ConnectionException $e) {
    echo "\n", microtime(true), " Client died: $e\n";
    save_coverage_data($test_id);
  }
}

exit;


function save_coverage_data($test_id) {

  if (!function_exists('xdebug_get_code_coverage')) return;

  $data = xdebug_get_code_coverage();
  xdebug_stop_code_coverage();

  if (!is_dir($GLOBALS['PHPUNIT_COVERAGE_DATA_DIRECTORY'])) {
    mkdir($GLOBALS['PHPUNIT_COVERAGE_DATA_DIRECTORY'], 0777, true);
  }
  $file = $GLOBALS['PHPUNIT_COVERAGE_DATA_DIRECTORY'] . '/' . $test_id
    . '.' . md5(uniqid(rand(), true));

  echo "Saving coverage data to $file...\n";
  file_put_contents($file, serialize($data));
}
