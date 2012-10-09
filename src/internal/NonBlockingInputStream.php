<?php
/*
 * Copyright (c) 2002-2012, the original author or authors.
 *
 * This software is distributable under the BSD license. See the terms of the
 * BSD license in the documentation provided with this software.
 *
 * http://www.opensource.org/licenses/bsd-license.php
 */
namespace phpline\internal;

/**
 * Provides a non blocking stream to read from.
 * Reads that do not see data for a certain timeout return without data.
 * 
 * @author Tim Düsterhus
 */
class NonBlockingInputStream {
	private $in, $isNonBlockingEnabled;
	private $c = -2;
	public function __construct($in, $isNonBlockingEnabled) {
		$this->in = $in;
		$this->isNonBlockingEnabled = $isNonBlockingEnabled;
	}
	
	public function close() {
		fclose($this->in);
	}
	
	public function read($timeout = null, $isPeek = false) {
		if ($timeout !== null && !$this->isNonBlockingEnabled) {
			throw new \BadFunctionCallException("nonblocking must be enabled");
		}

		if (feof($this->in)) return -1;
		if ($this->c !== -2) {
			
		}
		else if ($timeout == 0 || !$this->isNonBlockingEnabled) {
			return fgetc($this->in);
		}
		else {
			$end = microtime(true) + ($timeout / 1000);
			while (microtime(true) < $end) {
				if (feof($this->in)) {
					$this->c = -1;
					break;
				}
				
				$read = array();
				$read[] = $this->in;
				$write = $except = null;
				$tv = 0;
				stream_select($read, $write, $except, $tv);
				if (count($read)) {
					$this->c = fgetc($this->in);
					break; 
				}
				usleep(1000);
			}
		}
		
		$ret = $this->c;
		if (!$isPeek) {
			$this->c = -2;
		}
		return $ret;
	}
	
	public function peek($timeout) {
		return $this->read($timeout, true);
	}
	
	public function isNonBlockingEnabled() {
		return $this->isNonBlockingEnabled;
	}
}