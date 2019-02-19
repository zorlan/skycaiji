<?php

/**
 * Copyright (C) 2014, 2015 Textalk
 * Copyright (C) 2015 Patrick McCarren - added testSetFragmentSize
 * Copyright (C) 2015 Ignas Bernotas - added *StreamContextOptions
 *
 * This file is part of Websocket PHP and is free software under the ISC License.
 * License text: https://raw.githubusercontent.com/Textalk/websocket-php/master/COPYING
 */

use WebSocket\Client;
use WebSocket\Tests\ClientTracker;

class WebSocketTest extends PHPUnit_Framework_TestCase {
  protected static $ports;

  public static function setUpBeforeClass() {
    // Start local echoserver to run client tests on
    $cmd = 'php examples/echoserver.php';
    $outputfile = 'build/serveroutput.txt';
    $pidfile    = 'build/server.pid';
    exec(sprintf("%s > %s 2>&1 & echo $! >> %s", $cmd, $outputfile, $pidfile));

    usleep(500000);
    self::$ports[] = trim(file_get_contents($outputfile));

    echo "Native server started with port: ", self::$ports[0], "\n";

    // Start ratchet echoserver to run client tests on
    $cmd = 'php tests/bin/ratchetserver.php';
    $outputfile = 'build/rserveroutput.txt';
    $pidfile    = 'build/rserver.pid';
    exec(sprintf("%s > %s 2>&1 & echo $! >> %s", $cmd, $outputfile, $pidfile));

    usleep(500000);
    self::$ports[] = trim(file_get_contents($outputfile));

    echo "Ratchet server started with port: ", self::$ports[1], "\n";
  }

  public static function tearDownAfterClass() {
    $ws = new Client('ws://localhost:' . self::$ports[0]);
    $ws->send('exit');
    $ws = new Client('ws://localhost:' . self::$ports[1]);
    $ws->send('exit');
  }

  public function setup() {
    // Setup server side coverage catching
    $this->test_id = rand();
  }

  protected function getCodeCoverage() {
    $files = glob(dirname(dirname(dirname(__FILE__))) . '/build/tmp/' . $this->test_id . '.*');

    if (count($files) > 1) {
      echo "We have more than one coverage file...\n";
    }

    foreach ($files as $file) {
      $buffer = file_get_contents($file);
      $coverage_data = unserialize($buffer);
    }

    if (!isset($coverage_data)) return array();

    return $coverage_data;
  }

  public function run(PHPUnit_Framework_TestResult $result = NULL) {
    if ($result === NULL) {
      $result = $this->createResult();
    }

    $this->collectCodeCoverageInformation = $result->getCollectCodeCoverageInformation();

    parent::run($result);

    if ($this->collectCodeCoverageInformation) {
      $result->getCodeCoverage()->append(
        $this->getCodeCoverage(), $this
      );
    }

    return $result;
  }

  /**
   * @dataProvider serverPortProvider
   */
  public function testInstantiation($port) {
    $ws = new Client('ws://localhost:' . self::$ports[$port] . '/' . $this->test_id);

    $this->assertInstanceOf('WebSocket\Client', $ws);
  }

  /**
   * Data provider for testEcho
   *
   * @return  array of data
   */
  public function dataLengthProvider() {
    $lengths = array(8, 126, 127, 128, 65000, 66000);
    $ports   = $this->serverPortProvider();
    $provide = array();

    foreach ($lengths as $length) {
      foreach ($ports as $port_array) {
        $provide[] = array($length, $port_array[0]);
      }
    }

    return $provide;
  }

  /**
   * Data provider to run tests on both servers.
   */
  public function serverPortProvider() {
    return array(array(0), array(1));
  }

  /**
   * @dataProvider dataLengthProvider
   */
  public function testEcho($data_length, $port) {
    $ws = new ClientTracker('ws://localhost:' . self::$ports[$port] . '/' . $this->test_id);
    $ws->setFragmentSize(rand(10,512));

    $greeting = '';
    for ($i = 0; $i < $data_length; $i++) $greeting .= 'o';

    $ws->send($greeting);
    $response = $ws->receive();

    $this->assertEquals($greeting, $response);
    $this->assertEquals($ws->fragment_count['send'], ceil($data_length / $ws->getFragmentSize()));
    //$this->assertEquals($ws->fragment_count['receive'], ceil($data_length / 4096)); // the server sends with size 4096
  }

  /**
   * @dataProvider serverPortProvider
   */
  public function testBasicAuth($port) {
    $user = 'JohnDoe';
    $pass = 'eoDnhoJ';

    $ws = new Client("ws://$user:$pass@localhost:" . self::$ports[$port] . '/' . $this->test_id);

    $greeting = 'Howdy';
    $ws->send($greeting);
    $response = $ws->receive();

    // Echo server will prefix basic authed requests.
    $this->assertEquals("Basic Sm9obkRvZTplb0RuaG9K - $greeting", $response);
  }

  /**
   * @dataProvider serverPortProvider
   */
  public function testOrgEchoTwice($port) {
    $ws = new Client('ws://localhost:' . self::$ports[$port] . '/' . $this->test_id);

    for ($i = 0; $i < 2; $i++) {
      $greeting = 'Hello WebSockets ' . $i;
      $ws->send($greeting);
      $response = $ws->receive();
      $this->assertEquals($greeting, $response);
    }
  }

  public function testClose() {
    // Start a NEW dedicated server for this test
    $cmd = 'php examples/echoserver.php';
    $outputfile = 'build/serveroutput_close.txt';
    $pidfile    = 'build/server_close.pid';
    exec(sprintf("%s > %s 2>&1 & echo $! >> %s", $cmd, $outputfile, $pidfile));

    usleep(500000);
    $port = trim(file_get_contents($outputfile));

    $ws = new Client('ws://localhost:' . $port . '/' . $this->test_id);
    $ws->send('exit');
    $response = $ws->receive();

    $this->assertEquals('ttfn', $response);
    $this->assertEquals(1000, $ws->getCloseStatus());
    $this->assertFalse($ws->isConnected());
  }

  /**
   * @expectedException        WebSocket\BadUriException
   * @expectedExceptionMessage Url should have scheme ws or wss
   */
  public function testBadUrl() {
    $ws = new Client('http://echo.websocket.org');
    $ws->send('foo');
  }

  /**
   * @expectedException        WebSocket\ConnectionException
   */
  public function testNonWSSite() {
    $ws = new Client('ws://example.org');
    $ws->send('foo');
  }

  public function testSslUrl() {
    $ws = new Client('wss://echo.websocket.org');

    $greeting = 'Hello WebSockets';
    $ws->send($greeting);
    $response = $ws->receive();
    $this->assertEquals($greeting, $response);
  }

  public function testSslUrlMasked() {
    $ws = new Client('wss://echo.websocket.org');

    $greeting = 'Hello WebSockets';
    $ws->send($greeting, 'text', true);
    $response = $ws->receive();
    $this->assertEquals($greeting, $response);
  }

  /**
   * @dataProvider serverPortProvider
   */
  public function testMaskedEcho($port) {
    $ws = new Client('ws://localhost:' . self::$ports[$port] . '/' . $this->test_id);

    $greeting = 'Hello WebSockets';
    $ws->send($greeting, 'text', true);
    $response = $ws->receive();
    $this->assertEquals($greeting, $response);
  }

  /**
   * @dataProvider timeoutProvider
   */
  public function testTimeout($timeout) {
    try {
      $ws = new Client('ws://example.org:1111', array('timeout' => $timeout));
      $start_time = microtime(true);
      $ws->send('foo');
    }
    catch (WebSocket\ConnectionException $e) {
      $this->assertLessThan($timeout + 0.2, microtime(true) - $start_time);
      $this->assertGreaterThan($timeout - 0.2, microtime(true) - $start_time);
    }

    if (!isset($e)) $this->fail('Should have timed out and thrown a ConnectionException');
  }

  public function timeoutProvider() {
    return array(
      array(1),
      array(2),
    );
  }

  public function testChangeTimeout() {
    $timeout = 1;

    try {
      $ws = new Client('ws://example.org:1111', array('timeout' => 5));
      $ws->setTimeout($timeout);
      $start_time = microtime(true);
      $ws->send('foo');
    }
    catch (WebSocket\ConnectionException $e) {
      $this->assertLessThan($timeout + 0.2, microtime(true) - $start_time);
      $this->assertGreaterThan($timeout - 0.2, microtime(true) - $start_time);
    }

    if (!isset($e)) $this->fail('Should have timed out and thrown a ConnectionException');
  }

  /**
   * @dataProvider serverPortProvider
   */
  public function testDefaultHeaders($port) {
    $ws = new Client('ws://localhost:' . self::$ports[$port] . '/' . $this->test_id);

    $ws->send('Dump headers');

    $this->assertRegExp(
      "/GET \/$this->test_id HTTP\/1.1\r\n"
      . "host: localhost:" . self::$ports[$port] . "\r\n"
      . "user-agent: websocket-client-php\r\n"
      . "connection: Upgrade\r\n"
      . "upgrade: websocket\r\n"
      . "sec-websocket-key: .*\r\n"
      . "sec-websocket-version: 13\r\n/",
      $ws->receive()
    );
  }

  /**
   * @dataProvider serverPortProvider
   */
  public function testUserAgentOverride($port) {
    $ws = new Client(
      'ws://localhost:' . self::$ports[$port] . '/' . $this->test_id,
      array('headers' => array('User-Agent' => 'Deep thought'))
    );

    $ws->send('Dump headers');

    $this->assertRegExp(
      "/GET \/$this->test_id HTTP\/1.1\r\n"
      . "host: localhost:" . self::$ports[$port] . "\r\n"
      . "user-agent: Deep thought\r\n"
      . "connection: Upgrade\r\n"
      . "upgrade: websocket\r\n"
      . "sec-websocket-key: .*\r\n"
      . "sec-websocket-version: 13\r\n/",
      $ws->receive()
    );
  }

  /**
   * @dataProvider serverPortProvider
   */
  public function testAddingHeaders($port) {
    $ws = new Client(
      'ws://localhost:' . self::$ports[$port] . '/' . $this->test_id,
      array('headers' => array('X-Cooler-Than-Beeblebrox' => 'Slartibartfast'))
    );

    $ws->send('Dump headers');

    $this->assertRegExp(
      "/GET \/$this->test_id HTTP\/1.1\r\n"
      . "host: localhost:" . self::$ports[$port] . "\r\n"
      . "user-agent: websocket-client-php\r\n"
      . "connection: Upgrade\r\n"
      . "upgrade: websocket\r\n"
      . "sec-websocket-key: .*\r\n"
      . "sec-websocket-version: 13\r\n"
      . "x-cooler-than-beeblebrox: Slartibartfast\r\n/",
      $ws->receive()
    );
  }

  /**
   * @expectedException WebSocket\BadOpcodeException
   * @expectedExceptionMessage Bad opcode 'bad_opcode'
   * @dataProvider serverPortProvider
   */
  public function testSendBadOpcode($port) {
    $ws = new Client('ws://localhost:' . self::$ports[$port]);
    $ws->send('foo', 'bad_opcode');
  }

  /**
   * @dataProvider serverPortProvider
   */
  public function testSetFragmentSize($port) {
    $ws = new Client('ws://localhost:' . self::$ports[$port]);
    $size = $ws->setFragmentSize(123)->getFragmentSize();
    $this->assertSame(123, $size);
  }

  /**
   * @dataProvider serverPortProvider
   */
  public function testSetStreamContextOptions($port) {
    $context = stream_context_create();
    stream_context_set_option($context, 'ssl', 'verify_peer', true);
    stream_context_set_option($context, 'ssl', 'verify_host', true);
    stream_context_set_option($context, 'ssl', 'allow_self_signed', true);

    $options = array(
      'context' => $context
    );

    $ws = new Client('ws://localhost:' . self::$ports[$port], $options);
    // Do a send to touch the context using code in connect.  We can't really assert that the
    // stream has the correct context, but we make sure it doesn't crash.
    $ws->send('foo');
    $this->assertTrue(get_resource_type($ws->options['context']) === 'stream-context');
  }

  /**
   * @dataProvider serverPortProvider
   *
   * @expectedException \InvalidArgumentException
   * @expectedExceptionMessage Stream context in $options['context'] isn't a valid context
   */
  public function testSetInvalidStreamContextOptions($port) {
    $context = false;

    $options = array(
      'context' => $context
    );

    $ws = new Client('ws://localhost:' . self::$ports[$port], $options);
    $ws->send('foo');
  }
}
