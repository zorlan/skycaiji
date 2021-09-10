<?php

/**
 * Copyright (C) 2014-2020 Textalk/Abicart and contributors.
 *
 * This file is part of Websocket PHP and is free software under the ISC License.
 * License text: https://raw.githubusercontent.com/Textalk/websocket-php/master/COPYING
 */

namespace WebSocket;

class Server extends Base
{
    // Default options
    protected static $default_options = [
      'timeout'       => null,
      'fragment_size' => 4096,
      'port'          => 8000,
    ];

    protected $addr;
    protected $port;
    protected $listening;
    protected $request;
    protected $request_path;

    /**
     * @param array $options
     *   Associative array containing:
     *   - timeout:       Set the socket timeout in seconds.
     *   - fragment_size: Set framgemnt size.  Default: 4096
     *   - port:          Chose port for listening. Default 8000.
     */
    public function __construct(array $options = array())
    {
        $this->options = array_merge(self::$default_options, $options);
        $this->port = $this->options['port'];

        do {
            $this->listening = @stream_socket_server("tcp://0.0.0.0:$this->port", $errno, $errstr);
        } while ($this->listening === false && $this->port++ < 10000);

        if (!$this->listening) {
            throw new ConnectionException("Could not open listening socket: $errstr", $errno);
        }
    }

    public function __destruct()
    {
        if ($this->isConnected()) {
            fclose($this->socket);
        }
        $this->socket = null;
    }

    public function getPort()
    {
        return $this->port;
    }

    public function getPath()
    {
        return $this->request_path;
    }

    public function getRequest()
    {
        return $this->request;
    }

    public function getHeader($header)
    {
        foreach ($this->request as $row) {
            if (stripos($row, $header) !== false) {
                list($headername, $headervalue) = explode(":", $row);
                return trim($headervalue);
            }
        }
        return null;
    }

    public function accept()
    {
        $this->socket = null;
        return (bool)$this->listening;
    }

    protected function connect()
    {
        if (empty($this->options['timeout'])) {
            $this->socket = @stream_socket_accept($this->listening);
            if (!$this->socket) {
                throw new ConnectionException('Server failed to connect.');
            }
        } else {
            $this->socket = @stream_socket_accept($this->listening, $this->options['timeout']);
            if (!$this->socket) {
                throw new ConnectionException('Server failed to connect.');
            }
            stream_set_timeout($this->socket, $this->options['timeout']);
        }

        $this->performHandshake();
    }

    protected function performHandshake()
    {
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
    }
}
