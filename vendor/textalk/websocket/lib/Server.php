<?php

/**
 * Copyright (C) 2014, 2015 Textalk
 *
 * This file is part of Websocket PHP and is free software under the ISC License.
 * License text: https://raw.githubusercontent.com/Textalk/websocket-php/master/COPYING
 */

namespace WebSocket;

/**
 * This is just a simple stub for testing the Client.  It could be made useful…
 */
class Server extends Base {
  protected $addr, $port, $listening, $request;

  /**
   * @param array   $options
   *   Associative array containing:
   *   - timeout:  Set the socket timeout in seconds.  Default: 5
   *   - port:     Chose port for listening.
   */
  public function __construct(array $options = array()) {

    // the fragment size
    if (!array_key_exists('fragment_size', $options)) $options['fragment_size'] = 4096;


    $this->port = isset($options['port']) ? $options['port'] : 8000;
    $this->options = $options;

    do {
      $this->listening = @stream_socket_server("tcp://0.0.0.0:$this->port", $errno, $errstr);
    } while ($this->listening === false && $this->port++ < 10000);

    if (!$this->listening) {
      throw new ConnectionException("Could not open listening socket.");
    }
  }

  public function getPort()    { return $this->port;         }
  public function getPath()    { return $this->request_path; }
  public function getRequest() { return $this->request;      }

  public function getHeader($header) {
    foreach ($this->request as $row) {
      if (stripos($row, $header) !== false) {
        list($headername, $headervalue) = explode(":", $row);
        return trim($headervalue);
      }
    }
    return null;
  }

  public function accept() {
    if (empty($this->options['timeout'])) {
      $this->socket = stream_socket_accept($this->listening);
    }
    else {
      $this->socket = stream_socket_accept($this->listening, $this->options['timeout']);
      stream_set_timeout($this->socket, $this->options['timeout']);
    }

    $this->performHandshake();

    return $this->socket;
  }

  protected function performHandshake() {
    $request = '';
    do {
      $buffer = stream_get_line($this->socket, 1024, "\r\n");
      $request .= $buffer . "\n";
      $metadata = stream_get_meta_data($this->socket);
    } while (!feof($this->socket) && $metadata['unread_bytes'] > 0);

    if (!preg_match('/GET (.*) HTTP\//mUi', $request, $matches)) {
      throw new ConnectionException("No GET in request:\n" . $request);
    }
    $get_uri = trim($matches[1]);
    $uri_parts = parse_url($get_uri);

    $this->request = explode("\n", $request);
    $this->request_path = $uri_parts['path'];
    /// @todo Get query and fragment as well.

    if (!preg_match('#Sec-WebSocket-Key:\s(.*)$#mUi', $request, $matches)) {
      throw new ConnectionException("Client had no Key in upgrade request:\n" . $request);
    }

    $key = trim($matches[1]);

    /// @todo Validate key length and base 64...
    $response_key = base64_encode(pack('H*', sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));

    $header = "HTTP/1.1 101 Switching Protocols\r\n"
      . "Upgrade: websocket\r\n"
      . "Connection: Upgrade\r\n"
      . "Sec-WebSocket-Accept: $response_key\r\n"
      . "\r\n";

    $this->write($header);
    $this->is_connected = true;
  }
}
