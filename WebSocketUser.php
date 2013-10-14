<?php
class WebSocketUser {
	public $socket;
	public $id;
	public $headers = array();

	public $handlingPartialPacket = false;
	public $partialBuffer = "";

	public $sendingContinuous = false;
	public $partialMessage = "";

	public $hasSentClose = false;
	public $handshake = false;

	function __construct($socket) {
		$this->socket = $socket;
	}
}
?>