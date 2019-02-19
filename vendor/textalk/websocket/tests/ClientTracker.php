<?php

/**
 * Copyright (C) 2015 Patrick McCarren
 *
 * This file is part of Websocket PHP and is free software under the ISC License.
 * License text: https://raw.githubusercontent.com/Textalk/websocket-php/master/COPYING
 */

namespace WebSocket\Tests;

use WebSocket\Client;

/**
 * A class to track the send and receive operations of the client
 */
class ClientTracker extends Client {
	public $fragment_count;

	public function __construct($uri, $options = array()) {
		$this->fragment_count = array(
			'send' => 0,
			'receive' => 0,
		);

		return parent::__construct($uri, $options);
	}

	/**
	 * Reset the send counter
	 */
	public function send($payload, $opcode = 'text', $masked = true) {
		$this->fragment_count['send'] = 0;
		return parent::send($payload, $opcode, $masked);
	}

	/**
	 * Increment the fragment send counter
	 */
	protected function send_fragment($final, $payload, $opcode, $masked) {
		$this->fragment_count['send']++;
		return parent::send_fragment($final, $payload, $opcode, $masked);
	}

	/**
	 * Reset the receive counter
	 */
	public function receive() {
		$this->fragment_count['receive'] = 0;
		return parent::receive();
	}

	/**
	 * Increment the fragment recieve counter
	 */
	protected function receive_fragment() {
		$this->fragment_count['receive']++;
		return parent::receive_fragment();
	}
}