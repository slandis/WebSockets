<?php
/*
 *  WebSockets - A PHP WebSocket Implementation
 *  WebSocketServer.php
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

require_once('User.php');

class WebSocketServer {
	private $userClass = 'User';
	private $master;
	private $sockets                              = array();
	private $users                                = array();
	private $events 							  = array();

	public function __construct($address, $port) {
		$this->master = stream_socket_server("tcp://$address:$port", $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN);
		$this->sockets[] = $this->master;
		Debug::Write("Server started ($address:$port/".$this->master.")");
	}

	public function run() {
		while(true) {
			if (empty($this->sockets)) {
				$this->sockets[] = $master;
			}
			
			$read = $this->sockets;
			$write = $except = null;
			
			if (@stream_select($read, $write, $except, null) === FALSE) {
				break;
			}
			
			foreach ($read as $socket) {
				if ($socket == $this->master) {
					$client = stream_socket_accept($socket);

					if ($client < 0) {
						Debug::Write("Failed: socket_accept()");
						continue;
					} else {
						$this->connect($client);
					}
				} else {
					$buffer = @fread($socket, 2048);

					if (strlen($buffer) === 0 || $buffer === FALSE) {
						Debug::Write("Receeived 0 bytes, closing.");
						$this->disconnect($socket);
					} else {
						$user = $this->getUserBySocket($socket);

						if (!$user->handshake) {
							$this->doHandshake($user, $buffer);
						} else {
							if ($message = $this->deframe($buffer, $user)) {
								$this->raiseEvent(WebSocketEvent::EventType_Received, $user, $message);

								if($user->hasSentClose) {
									$this->disconnect($socket);
								}
							}
						}
					}
				}
			}
		}
	}

	public function send($user, $message) {
		$message = $this->frame($message, $user);
		fwrite($user->socket, $message);
	}

	private function connect($socket) {
		$user = new $this->userClass($socket);
		array_push($this->users, $user);
		array_push($this->sockets, $socket);
		
		$this->raiseEvent(WebSocketEvent::EventType_Connecting, $user);
	}

	private function disconnect($socket, $triggerClosed=true) {
		$foundUser = null;
		$foundSocket = null;

		foreach ($this->users as $key => $user) {
			if ($user->socket == $socket) {
				$foundUser = $key;
				$disconnectedUser = $user;
				break;
			}
		}

		if ($foundUser !== null) {
			unset($this->users[$foundUser]);
			$this->users = array_values($this->users);
		}

		foreach ($this->sockets as $key => $sock) {
			if ($sock == $socket) {
				$foundSocket = $key;
				break;
			}
		}

		if ($foundSocket !== null) {
			unset($this->sockets[$foundSocket]);
			$this->sockets = array_values($this->sockets);
		}

		if ($triggerClosed) {
			Debug::Write("Client closed socket.");
			$this->raiseEvent(WebSocketEvent::EventType_Disconnected, $user);
		}
	}

	private function doHandshake($user, $buffer) {
		$magicGUID = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11";
		$headers = array();
		$lines = explode("\n", $buffer);

		foreach ($lines as $line) {
			if (strpos($line, ":") !== false) {
				$header = explode(":", $line, 2);
				$headers[strtolower(trim($header[0]))] = trim($header[1]);
			} else if (stripos($line, "get ") !== false) {
				preg_match("/GET (.*) HTTP/i", $buffer, $reqResource);
				$headers['get'] = trim($reqResource[1]);
			}
		}

		if (isset($headers['get'])) {
			$user->requestedResource = $headers['get'];
		} else {
			$handshakeResponse = "HTTP/1.1 405 Method Not Allowed\r\n\r\n";			
		}

		if (!isset($headers['host'])) {
			$handshakeResponse = "HTTP/1.1 400 Bad Request";
		}

		if (!isset($headers['upgrade']) || strtolower($headers['upgrade']) != 'websocket') {
			$handshakeResponse = "HTTP/1.1 400 Bad Request";
		}

		if (!isset($headers['connection']) || strpos(strtolower($headers['connection']), 'upgrade') === FALSE) {
			$handshakeResponse = "HTTP/1.1 400 Bad Request";
		}

		if (!isset($headers['sec-websocket-key'])) {
			$handshakeResponse = "HTTP/1.1 400 Bad Request";
		} else {

		}

		if (!isset($headers['sec-websocket-version']) || strtolower($headers['sec-websocket-version']) != 13) {
			$handshakeResponse = "HTTP/1.1 426 Upgrade Required\r\nSec-WebSocketVersion: 13";
		}

		if (isset($handshakeResponse)) {
			fwrite($user->socket, $handshakeResponse);
			$this->disconnect($user->socket);
			return false;
		}

		$user->headers = $headers;

		$webSocketKeyHash = sha1($headers['sec-websocket-key'] . $magicGUID);

		$rawToken = "";
		for ($i = 0; $i < 20; $i++) {
			$rawToken .= chr(hexdec(substr($webSocketKeyHash,$i*2, 2)));
		}
		$handshakeToken = base64_encode($rawToken) . "\r\n";
		$handshakeResponse = "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: $handshakeToken\r\n";

		fwrite($user->socket, $handshakeResponse);
    	
    	$user->handshake = true;
    	$this->raiseEvent(WebSocketEvent::EventType_Connected, $user);
	}

	public function getUserBySocket($socket) {
		foreach ($this->users as $user) {
			if ($user->socket == $socket) {
				return $user;
			}
		}

		return null;
	}

	private function frame($message, $user, $messageType='text', $messageContinues=false) {
		switch ($messageType) {
			case 'continuous':
				$b1 = 0;
				break;
			case 'text':
				$b1 = ($user->sendingContinuous) ? 0 : 1;
				break;
			case 'binary':
				$b1 = ($user->sendingContinuous) ? 0 : 2;
				break;
			case 'close':
				$b1 = 8;
				break;
			case 'ping':
				$b1 = 9;
				break;
			case 'pong':
				$b1 = 10;
				break;
		}
		if ($messageContinues) {
			$user->sendingContinuous = true;
		} else {
			$b1 += 128;
			$user->sendingContinuous = false;
		}

		$length = strlen($message);
		$lengthField = "";

		if ($length < 126) {
			$b2 = $length;
		} elseif ($length <= 65536) {
			$b2 = 126;
			$hexLength = dechex($length);

			if (strlen($hexLength)%2 == 1) {
				$hexLength = '0' . $hexLength;
			} 

			$n = strlen($hexLength) - 2;

			for ($i = $n; $i >= 0; $i=$i-2) {
				$lengthField = chr(hexdec(substr($hexLength, $i, 2))) . $lengthField;
			}

			while (strlen($lengthField) < 2) {
				$lengthField = chr(0) . $lengthField;
			}
		} else {
			$b2 = 127;
			$hexLength = dechex($length);

			if (strlen($hexLength)%2 == 1) {
				$hexLength = '0' . $hexLength;
			} 

			$n = strlen($hexLength) - 2;

			for ($i = $n; $i >= 0; $i=$i-2) {
				$lengthField = chr(hexdec(substr($hexLength, $i, 2))) . $lengthField;
			}

			while (strlen($lengthField) < 8) {
				$lengthField = chr(0) . $lengthField;
			}
		}

		return chr($b1) . chr($b2) . $lengthField . $message;
	}

	private function deframe($message, $user) {
		$headers = $this->extractHeaders($message);
		$pongReply = false;
		$willClose = false;
		switch($headers['opcode']) {
			case 0:
			case 1:
			case 2:
				break;
			case 8:
				$user->hasSentClose = true;
				return "";
			case 9:
				$pongReply = true;
			case 10:
				break;
			default:
				$willClose = true;
				break;
		}

		if ($user->handlingPartialPacket) {
			$message = $user->partialBuffer . $message;
			$user->handlingPartialPacket = false;
			return $this->deframe($message, $user);
		}

		if ($this->checkRSVBits($headers,$user)) {
			return false;
		}

		if ($willClose) {
			return false;
		}

		$payload = $user->partialMessage . $this->extractPayload($message,$headers);

		if ($pongReply) {
			$reply = $this->frame($payload,$user,'pong');
			socket_write($user->socket,$reply,strlen($reply));
			return false;
		}

		if (extension_loaded('mbstring')) {
			if ($headers['length'] > mb_strlen($payload)) {
				$user->handlingPartialPacket = true;
				$user->partialBuffer = $message;
				return false;
			}
		} else {
			if ($headers['length'] > strlen($payload)) {
				$user->handlingPartialPacket = true;
				$user->partialBuffer = $message;
				return false;
			}
		}

		$payload = $this->applyMask($headers,$payload);

		if ($headers['fin']) {
			$user->partialMessage = "";
			return $payload;
		}

		$user->partialMessage = $payload;
		return false;
	}

	private function extractHeaders($message) {
		$header = array('fin'     => $message[0] & chr(128),
						'rsv1'    => $message[0] & chr(64),
						'rsv2'    => $message[0] & chr(32),
						'rsv3'    => $message[0] & chr(16),
						'opcode'  => ord($message[0]) & 15,
						'hasmask' => $message[1] & chr(128),
						'length'  => 0,
						'mask'    => "");
		$header['length'] = (ord($message[1]) >= 128) ? ord($message[1]) - 128 : ord($message[1]);

		if ($header['length'] == 126) {
			if ($header['hasmask']) {
				$header['mask'] = $message[4] . $message[5] . $message[6] . $message[7];
			}
			$header['length'] = ord($message[2]) * 256 
							  + ord($message[3]);
		} elseif ($header['length'] == 127) {
			if ($header['hasmask']) {
				$header['mask'] = $message[10] . $message[11] . $message[12] . $message[13];
			}
			$header['length'] = ord($message[2]) * 65536 * 65536 * 65536 * 256 
							  + ord($message[3]) * 65536 * 65536 * 65536
							  + ord($message[4]) * 65536 * 65536 * 256
							  + ord($message[5]) * 65536 * 65536
							  + ord($message[6]) * 65536 * 256
							  + ord($message[7]) * 65536 
							  + ord($message[8]) * 256
							  + ord($message[9]);
		} elseif ($header['hasmask']) {
			$header['mask'] = $message[2] . $message[3] . $message[4] . $message[5];
		}

		return $header;
	}

	private function extractPayload($message, $headers) {
		$offset = 2;

		if ($headers['hasmask']) {
			$offset += 4;
		}

		if ($headers['length'] > 65535) {
			$offset += 8;
		} elseif ($headers['length'] > 125) {
			$offset += 2;
		}

		return substr($message,$offset);
	}

	private function applyMask($headers, $payload) {
		$effectiveMask = "";
		if ($headers['hasmask']) {
			$mask = $headers['mask'];
		} else {
			return $payload;
		}

		while (strlen($effectiveMask) < strlen($payload)) {
			$effectiveMask .= $mask;
		}
		while (strlen($effectiveMask) > strlen($payload)) {
			$effectiveMask = substr($effectiveMask,0,-1);
		}
		return $effectiveMask ^ $payload;
	}
	private function checkRSVBits($headers,$user) { // override this method if you are using an extension where the RSV bits are used.
		if (ord($headers['rsv1']) + ord($headers['rsv2']) + ord($headers['rsv3']) > 0) {
			//$this->disconnect($user); // todo: fail connection
			return true;
		}
		return false;
	}

	private function strtohex($str) {
		$strout = "";
		for ($i = 0; $i < strlen($str); $i++) {
			$strout .= (ord($str[$i])<16) ? "0" . dechex(ord($str[$i])) : dechex(ord($str[$i]));
			$strout .= " ";
			if ($i%32 == 7) {
				$strout .= ": ";
			}
			if ($i%32 == 15) {
				$strout .= ": ";
			}
			if ($i%32 == 23) {
				$strout .= ": ";
			}
			if ($i%32 == 31) {
				$strout .= "\n";
			}
		}
		return $strout . "\n";
	}

	private function printHeaders($headers) {
		echo "Array\n(\n";
		foreach ($headers as $key => $value) {
			if ($key == 'length' || $key == 'opcode') {
				echo "\t[$key] => $value\n\n";
			} else {
				echo "\t[$key] => ".$this->strtohex($value)."\n";

			}

		}
		echo ")\n";
	}

	public function registerEvent($EventType, $EventCallback) {
		$this->events[] = new WebSocketCallback($EventType, $EventCallback);
	}

	private function raiseEvent($EventType, $EventUser, $EventData = null) {
		foreach ($this->events as $event) {
			if ($event->EventType == $EventType) {
				call_user_func($event->EventCallback, new WebSocketEvent($EventUser, $EventData));
			}
		}
	}
}
?>