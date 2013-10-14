<?
/*
 *  WebSockets - A PHP WebSocket Implementation
 *  WebSocketEvent.php
 *  Copyright (C) 2013  Shaun Landis
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License along
 *  with this program; if not, write to the Free Software Foundation, Inc.,
 *  51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
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