<?
/*
 *  WebSockets - A PHP WebSocket Implementation
 *  Example.php
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

require_once('Debug.php');
require_once('WebSocketServer.php');
require_once('WebSocketEvent.php');

class Server {
	private $server;

	public function __construct($address, $port) {
		$server = new WebSocketServer($address, $port);
		$server->registerEvent(WebSocketEvent::EventType_Received, array($this, 'onReceived'));
		$server->registerEvent(WebSocketEvent::EventType_Connected, array($this, 'onConnected'));
		$server->registerEvent(WebSocketEvent::EventType_Connecting, array($this, 'onConnecting'));
		$server->registerEvent(WebSocketEvent::EventType_Disconnected, array($this, 'OnDisconnected'));
		$this->server = $server;
		$server->run();
	}

	function onConnected(WebSocketEvent $event) {
		Debug::Write("Client connected (".$event->EventUser->socket.")");
	}

	function onConnecting(WebSocketEvent $event) {
		Debug::Write("Client connecting (".$event->EventUser->socket.")");
	}

	function onDisconnected(WebSocketEvent $event) {
		Debug::Write("Client disconnected (".$event->EventUser->socket.")");
	}

	function onReceived(WebSocketEvent $event) {
		Debug::Write("Received client data: ".$event->EventData);
	}
}

$game = new Server('localhost', 9999);
?> 