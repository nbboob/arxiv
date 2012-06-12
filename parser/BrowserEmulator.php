<?php

class BrowserEmulator {

	var $headerLines = Array ();
	var $postData = Array ();
	var $authUser = "";
	var $authPass = "";
	var $port;
	var $lastResponse = Array ();
	var $debug = false; // lwp 2009.03.12 false => true

	function BrowserEmulator() {
		$this->resetHeaderLines ();
		$this->resetPort ();
	}
	/**
	 * Adds a single header field to the HTTP request header. The resulting header
	 * line will have the format
	 * $name: $value\n
	 **/
	function addHeaderLine($name, $value) {
		$this->headerLines [$name] = $value;
	}

	/**
	 * Deletes all custom header lines. This will not remove the User-Agent header field,
	 * which is necessary for correct operation.
	 **/
	function resetHeaderLines() {
		$this->headerLines = Array ();

		/*******************************************************************************/
		/**************   YOU MAX SET THE USER AGENT STRING HERE   *******************/
		/*                                                   */
		/* default is "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)",         */
		/* which means Internet Explorer 6.0 on WinXP                       */

		//$this->headerLines ["User-Agent"] = "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)";
		//		$this->headerLines["User-Agent"] = $_SERVER['HTTP_USER_AGENT'];

		/*******************************************************************************/

	}

	/**
	 * Add a post parameter. Post parameters are sent in the body of an HTTP POST request.
	 **/
	function addPostData($name, $value) {
		$this->postData [$name] = $value;
	}

	/**
	 * Deletes all custom post parameters.
	 **/
	function resetPostData() {
		$this->postData = Array ();
	}

	/**
	 * Sets an auth user and password to use for the request.
	 * Set both as empty strings to disable authentication.
	 **/
	function setAuth($user, $pass) {
		$this->authUser = $user;
		$this->authPass = $pass;
	}
	/**
	 * Selects a custom port to use for the request.
	 **/
	function setPort($portNumber) {
		$this->port = $portNumber;
	}

	/**
	 * Resets the port used for request to the HTTP default (80).
	 **/
	function resetPort() {
		$this->port = 80;
	}

	static function openArxiv($id) {
		$be = new BrowserEmulator();
		$be->addHeaderLine("Referer", "http://google.com/");
		$be->addHeaderLine("User-Agent", $_SERVER['HTTP_USER_AGENT']);

		$file = $be->fopen($id);

		// if failed to connect to arxiv.org, try 5 times more.
		for($i=0; $i<5 && (!$file); $i++) {
			//sleep(1);
			echo "$i...";
			$file = $be->fopen($id);
		}

		if(!$file)
		return false;

		//$response = $be->getLastResponseHeaders();

		$html = '';

		$line = fgets($file, 4096);
		while ($line != null && $line != '') {
			$html .= $line;
			$line = fgets($file, 4096);
		}

		fclose($file);

		return $html;
	}

	/**
	 * Make an fopen call to $url with the parameters set by previous member
	 * method calls. Send all set headers, post data and user authentication data.
	 * Returns a file handle on success, or false on failure.
	 **/
	function fopen($url) {
		$this->lastResponse = Array ();

		/*
		 preg_match( "~([a-z]*://)?([^:^/]*)(:([0-9]{1,5}))?(/.*)?~i", $url, $matches );

		 $protocol = $matches [1];
		 $server = $matches [2];
		 $port = $matches [4];
		 $path = $matches [5];
		 */

		//$protocol = "http";
		$server = "arxiv.org";
		$port = 80;
		$path = "/abs/$url";

		if ($port != "") {
			$this->setPort ( $port );
		}
		if ($path == "")
		$path = "/";
			
		$socket = fsockopen ( $server, $this->port );
		if ($socket) {
			if ($this->debug)
			echo '<br><b>socket opened...</b>';
				
			$this->headerLines ["Host"] = $server;
				
			if ($this->authUser != "" && $this->authPass != "") {
				$this->headerLines ["Authorization"] = "Basic " . base64_encode ( $this->authUser . ":" . $this->authPass );
			}
				
			/*
			 if (count ( $this->postData ) == 0) {
				$request = "GET $path HTTP/1.0\r\n";
				} else {
				$request = "POST $path HTTP/1.0\r\n";
				}
				*/
			$request = "GET $path HTTP/1.1\r\n";
				
				
			if ($this->debug)
			echo "<br><b>REQUEST:</b> $request";
			fputs ( $socket, $request );
				
			if($this->debug) {
				echo '<b>count ( $this->postData ):</b> ' . count ( $this->postData );
			}
			if (count ( $this->postData ) > 0) {
				$PostStringArray = Array ();
				foreach ( $this->postData as $key => $value ) {
					$PostStringArray [] = "$key=$value";
				}
				$PostString = join ( "&", $PostStringArray );
				$this->headerLines ["Content-Length"] = strlen ( $PostString );
			}
				
			if ($this->debug)
			echo "<br><b>key & values:</b> ";
			foreach ( $this->headerLines as $key => $value ) {
				if ($this->debug)
				echo "$key: $value\n";
				fputs ( $socket, "$key: $value\r\n" );
			}
				
			if ($this->debug)
			echo "\n";
			fputs ( $socket, "\r\n" );
				
				
			if (count ( $this->postData ) > 0) {
				if ($this->debug)
				echo "$PostString";
				fputs ( $socket, $PostString . "\r\n" );
			}
				
		}
		if ($this->debug)
		echo "\n";
		if ($socket) {
			if ($this->debug)
			echo "<br><b>SOCKET:</b> $socket";
				
			$line = fgets ( $socket, 1000 );
			// try 5 times more
			if($line == '') {
				return FALSE;
				/*
				 for($i = 0; $i < 5; $i++) {
					sleep(1);
					$line = fgets ( $socket, 1000 );
					if($line != '') {
					if ($this->debug)
					echo "<br>Times tried: $i";
					break;
					}
					}
					*/
			}
			if ($this->debug)
			echo "<br>Line 1: $line";

			$this->lastResponse [] = $line;
			$status = substr ( $line, 9, 3 );
			$i = 1;
			while ( trim ( $line = fgets ( $socket, 1000 ) ) != "" ) {
				$i++;
				if ($this->debug)
				echo "<br>Line $i: $line";
				$this->lastResponse [] = $line;
				if ($status == "401" and strpos ( $line, "WWW-Authenticate: Basic realm=\"" ) === 0) {
					if ($this->debug)
					echo "401\n";
					fclose ( $socket );
					return FALSE;
				}
			}
		} else {
			if ($this->debug)
			echo "<br><b>SOCKET Broken</b>";
		}
		return $socket;
	}

	/**
	 * Make an file call to $url with the parameters set by previous member
	 * method calls. Send all set headers, post data and user authentication data.
	 * Returns the requested file as an array on success, or false on failure.
	 **/
	function file($url) {
		$file = Array ();
		$socket = $this->fopen ( $url );
		if ($socket) {
			$file = Array ();
			while ( ! feof ( $socket ) ) {
				$file [] = fgets ( $socket, 10000 );
			}
		} else {
			return FALSE;
		}
		return $file;
	}

	function getLastResponseHeaders() {
		return $this->lastResponse;
	}
}
