<?php

/**
 * @package Applications
 * @subpackage ICMPClient
 *
 * @author Zorin Vasily <kak.serpom.po.yaitsam@gmail.com>
 */
class ICMPClient extends NetworkClient {

	/**
	 * Establishes connection
	 * @param string Address
	 * @return integer Connection ID
	 */
	
	public function sendPing($host, $cb) {
		$this->connect('raw://' . $host, function($conn) use ($cb) {
			$conn->sendEcho($cb);
		});
	}	
}

class ICMPClientConnection extends NetworkClientConnection {
	/**
	 * Packet sequence
	 * @var integer
	 */
	public $seq = 0;

	/**
	 * Enable bevConnect?
	 * @var boolean
	 */
	public $bevConnectEnabled = false;

	/**
	 * Send echo-request
	 * @param callable Callback
	 * @param [string Data
	 * @return void
	 */
	public function sendEcho($cb, $data = 'phpdaemon') {
		++$this->seq;
		if (strlen($data) % 2 !== 0) {
			$data .= "\x00";
		}
		$packet = pack('ccnnn',
			8, // type (c)
			0, // code (c)
			0, // checksum (n)
			Daemon::$process->getPid(), // pid (n)
			$this->seq  // seq (n)
		) . $data;
		$packet = substr_replace($packet, self::checksum($packet), 2, 2);
		$this->write($packet);
		$this->onResponse->push(array($cb, microtime(true)));
	}
		
	/**
	 * Build checksum
	 * @static
	 * @param string Source
	 * @return string Checksum
	 */
	protected static function checksum($data) {
		$bit = unpack('n*', $data);
		$sum = array_sum($bit);
		if (strlen($data) % 2) {
			$temp = unpack('C*', $data[strlen($data) - 1]);
			$sum += $temp[1];
		}
		$sum = ($sum >> 16) + ($sum & 0xffff);
		$sum += ($sum >> 16);
		return pack('n*', ~$sum);
	}
	

	/**
	 * Called when new data received
	 * @return void
	 */
	public function onRead() {
		$packet = $this->read(1024);
		$type = Binary::getByte($packet);
		$code = Binary::getByte($packet);
		$checksum = Binary::getStrWord($packet);
		$id = Binary::getWord($packet);
		$seq = Binary::getWord($packet);

		while (!$this->onResponse->isEmpty()) {
			$el = $this->onResponse->shift();
			if ($el instanceof CallbackWrapper) {
				$el = $el->unwrap();
			}
			list ($cb, $st) = $el;
			call_user_func($cb, microtime(true) - $st);
		}
		$this->finish();
	}
}
