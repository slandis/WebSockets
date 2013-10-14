<?
/*
 * WebSocketEvent
 *
 * Basic wrapper for generating events
 */

class WebSocketEvent {
	const EventType_None 			= 0x00;
	const EventType_Connecting 		= 0x01;
	const EventType_Connected  		= 0x02;
	const EventType_Disconnected 	= 0x04;
	const EventType_Received		= 0x08;
	const EventType_Sent			= 0x16;

	public $EventUser;
	public $EventData;

	public function __construct($EventUser, $EventData = null) {
		$this->EventUser = $EventUser;
		$this->EventData = $EventData;
	}
}

class WebSocketCallback {
	public $EventType;
	public $EventCallback;

	public function __construct($EventType, $EventCallback) {
		$this->EventType = $EventType;
		$this->EventCallback = $EventCallback;
	}
}
?>