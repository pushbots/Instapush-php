<?php
/**
* a PHP wrapper for Instapush API.
* Copyright (c) 2014 PushBots Inc.
*
* Portions of this class were borrowed from
* https://github.com/segmentio/analytics-php/blob/master/lib/Analytics/Consumer/Socket.php.
* Thanks for the work!
*
* WWWWWW||WWWWWW
* W W W||W W W
* ||
* ( OO )__________
* / | \
* /o o| MIT \
* \___/||_||__||_|| *
* || || || ||
* _||_|| _||_||
* (__|__|(__|__|
* (The MIT License)
*
* Copyright (c) 2013 Segment.io Inc. friends@segment.io
*
* Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated
* documentation files (the 'Software'), to deal in the Software without restriction, including without limitation the
* rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to
* permit persons to whom the Software is furnished to do so, subject to the following conditions:
*
* The above copyright notice and this permission notice shall be included in all copies or substantial portions of the
* Software.
*
* THE SOFTWARE IS PROVIDED 'AS IS', WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
* WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS
* OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR
* OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*
* Options
* -------------
*
* <table width="100%" cellpadding="5">
* <tr>
* <th>Option</th>
* <th>Description</th>
* <th>Default</th>
* </tr>
* <tr>
* <td>ssl</td>
* <td>Enable/disable debug mode</td>
* <td>false</td>
* </tr>
* <tr>
* <td>debug</td>
* <td>Enable/Disable SSL in the request.</td>
* <td>true</td>
* </tr>
*</table>
*
* Example: Tracking an Event
* -------------
*
* $ip = InstaPush::getInstance("APPLICATION_ID", "APPLICATION_SECRET");
*
* $ip->track("signup", array( 
* 		"email"=> "test@ss.cc"
* ));
*
*/

class InstaPush
{
	/**
	* @var string InstaPush Application ID
	*/
	private $appId;
	
	/**
	* @var string InstaPush Application secret
	*/
	private $appSecret;
	
	/**
	* Default options that can be overridden via the $options constructor arg
	* @var array
	*/
	private $_defaults = array(
		"host"		=> "api.instapush.im",
		"ssl"	    => false, // use ssl when available
		"debug"		=> false // enable/disable debug mode
	);
	
	/**
	* @var string the host to connect to (e.g. api.instapush.im)
	*/
    protected $_host;
	
	/**
	* @var string the protocol to use for the socket connection
	*/
	private $_protocol;
	
	/**
	* @var string the port to use for the socket connection
	*/
	private $_port;
	

	/**
	* @var resource holds the socket resource
	*/
	private $_socket;

	/**
	* @var int timeout the socket connection timeout in seconds
	*/
	private $_timeout;

	/**
	* @var bool whether or not to wait for a response
	*/
	private $_async;
	
	/**
	* An array of options to be used by the InstaPush library.
	* @var array
	*/
	protected $_options = array();
	
	/**
	* An instance of the InstaPush class (for singleton use)
	* @var InstaPush
	*/
	private static $_instance;
	
	const VERSION = '0.2';
    

	/**
	* Instantiates a new InstaPush instance.
	* @param $appId		Instapush Applciation Id.
	* @param $appSecret Instapush Application Secret.
	*/
	public function __construct($appId, $appSecret, $options = array()) {
		$this->appId = $appId;
		$this->appSecret = $appSecret;
		$options = array_merge($this->_defaults, $options);
		$this->_options = $options;
		$this->_host = $options['host'];
		$this->_timeout = array_key_exists('timeout', $options) ? $options['timeout'] : 4;
		$this->_async = array_key_exists('async', $options) && $options['async'] === false ? false : true;
		
		if (array_key_exists('ssl', $options) && $options['ssl'] == true) {
			$this->_protocol = "ssl";
			$this->_port = 443;
		} else {
			$this->_protocol = "tcp";
			$this->_port = 80;
		}
	}
	
	/**
	* Returns a singleton instance of InstaPush
	* @param $appId		Instapush Applciation Id.
	* @param $appSecret Instapush Application Secret.
	* @return InstaPush
	*/
	public static function getInstance($appId, $appSecret, $options = array()) {
		if(!isset(self::$_instance)) {
			self::$_instance = new InstaPush($appId, $appSecret, $options);
		}
		return self::$_instance;
	}
	
	/**
	* Returns InstaPush Application ID.
	*/
	public static function getAppId()
	{
		return self::$appId;
	}


	/**
	* Log a message to PHP's error log
	* @param $msg
	*/
	protected function _log($msg) {
		$arr = debug_backtrace();
		$class = $arr[0]['class'];
		$line = $arr[0]['line'];
		error_log ( "[ $class - line $line ] : " . $msg );
	}
	
	/**
	* Returns true if in debug mode, false if in production mode
	* @return bool
	*/
	protected function _debug() {
		return array_key_exists("debug", $this->_options) && $this->_options["debug"] == true;
	}
	
	/**
	* Track an event.
	* @param string $event	Event title e.g. registration, subscription, fatal error..etc
	* @param array $trackers  What to track for that event.
	*/
	public function track($event, $trackers) {
		$this->event['event'] = $event;
		foreach ($trackers as $tracker => $value) {
			$this->event[$tracker] = $value;
		}
		$Push = $this->_consume();
		return $Push;
	}
	
	/**
	* Attempt to open a new socket connection, cache it, and return the resource
	* @param bool $retry
	* @return bool|resource
	*/
	private function _createSocket($retry = true) {
		try {
			$socket = fsockopen($this->_protocol . "://" . $this->_host, $this->_port, $err_no, $err_msg, $this->_timeout);

			if ($this->_debug()) {
				$this->_log("Opening socket connection to " . $this->_protocol . "://" . $this->_host . ":" . $this->_port);
			}

			if ($err_no != 0) {
				$this->_handleError($err_no, $err_msg);
				return $retry == true ? $this->_createSocket(false) : false;
			} else {
				// cache the socket
				$this->_socket = $socket;
				return $socket;
			}

		} catch (Exception $e) {
			$this->_handleError($e->getCode(), $e->getMessage());
			return $retry == true ? $this->_createSocket(false) : false;
		}
	}
	

	/**
	* Attempt to close and dereference a socket resource
	*/
	private function _destroySocket() {
		$socket = $this->_socket;
		$this->_socket = null;
		fclose($socket);
	}

	/**
	* Return cached socket if open or create a new socket
	* @return bool|resource
	*/
	private function _getSocket() {
		if(is_resource($this->_socket)) {

			if ($this->_debug()) {
				$this->_log("Using existing socket");
			}

			return $this->_socket;
		} else {

			if ($this->_debug()) {
				$this->_log("Creating new socket at ".time());
			}

			return $this->_createSocket();
		}
	}
	
	/**
	* Write $data through the given $socket
	* @param $socket
	* @param $data
	* @param bool $retry
	* @return bool
	*/
	private function _write($socket, $data, $retry = true) {

		$bytes_sent = 0;
		$bytes_total = strlen($data);
		$socket_closed = false;
		$success = true;
		$max_bytes_per_write = 8192;

		// if we have no data to write just return true
		if ($bytes_total == 0) {
			return true;
		}

		// try to write the data
		while (!$socket_closed && $bytes_sent < $bytes_total) {

			try {
				$bytes = fwrite($socket, $data, $max_bytes_per_write);

				if ($this->_debug()) {
					$this->_log("Socket wrote ".$bytes." bytes");
				}

				// if we actually wrote data, then remove the written portion from $data left to write
				if ($bytes > 0) {
					$data = substr($data, $max_bytes_per_write);
				}

			} catch (Exception $e) {
				$this->_handleError($e->getCode(), $e->getMessage());
				$socket_closed = true;
			}

			if (isset($bytes) && $bytes) {
				$bytes_sent += $bytes;
			} else {
				$socket_closed = true;
			}
		}

		// create a new socket if the current one is closed and retry the message
		if ($socket_closed) {

			$this->_destroySocket();

			if ($retry) {
				if ($this->_debug()) {
					$this->_log("Retrying socket write...");
				}
				$socket = $this->_getSocket();
				if ($socket) return $this->_write($socket, $data, false);
			}

			return false;
		}


		// only wait for the response in debug mode or if we explicitly want to be synchronous
		if ($this->_debug() || !$this->_async) {
			//@TODO Handle Server response
			
		}

		return $success;
	}
	
	/**
	* Handles errors that occur in a consumer
	* @param $code
	* @param $msg
	*/
	protected function _handleError($code, $msg) {
		if (isset($this->_options['error_callback'])) {
			$handler = $this->_options['error_callback'];
			$handler($code, $msg);
		}

		if ($this->_debug()) {
			$arr = debug_backtrace();
			$class = get_class($arr[0]['object']);
			$line = $arr[0]['line'];
			error_log ( "[ $class - line $line ] : " . print_r($msg, true) );
		}
	}	
	
	/**
	* Consumes messages and writes them to Instapush API using a socket
	*/
	private function _consume(){
		
		$socket = $this->_getSocket();
		
		if (!is_resource($socket)) {
			return false;
		}
		
		// Request POST String
		foreach ($this->event as $key => &$val) {
			if (is_array($val)) $val = implode(',', $val);
			$post_params[] = $key.'='.urlencode($val);
		}
		
		$data = implode('&', $post_params);
		
		
		$body = "POST /php HTTP/1.1\r\n";
        $body.= "Host: " . $this->_host . "\r\n";
		$body.= "X-INSTAPUSH-APPID: " . $this->appId ."\r\n";
		$body.= "X-INSTAPUSH-APPSECRET: " . $this->appSecret ."\r\n";
		$body.= "Content-Type: application/x-www-form-urlencoded\r\n";
		$body.= "Content-Length: ".strlen($data)."\r\n";
		$body.= "Connection: Close\r\n\r\n";
		$body.= $data;
		
		return $this->_write($socket, $body);
	}
    
}

