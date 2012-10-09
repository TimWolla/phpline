<?php
/*
 * Copyright (c) 2002-2012, the original author or authors.
 *
 * This software is distributable under the BSD license. See the terms of the
 * BSD license in the documentation provided with this software.
 *
 * http://www.opensource.org/licenses/bsd-license.php
 */
namespace phpline\console;
use phpline\javaApi\StringBuilder;

/**
 * A holder for a {@link StringBuilder} that also contains the current cursor position.
 *
 * @author <a href="mailto:mwp1@cornell.edu">Marc Prud'hommeaux</a>
 * @author <a href="mailto:jason@planet57.com">Jason Dillon</a>
 * @author <a href="mailto:timwolla@bastelstu.be">Tim Düsterhus</a> 
 * @since 2.0
 */
class CursorBuffer
{
	private $overTyping = false;
	
	public $cursor = 0;
	
	public $buffer = null;
	
	public function __construct() {
		$this->buffer = new StringBuilder();
	}
	
	public function copy () {
		$that = new CursorBuffer();
		$that->overTyping = $this->overTyping;
		$that->cursor = $this->cursor;
		$that->buffer->append($this->__toString());
		
		return $that;
	}
	
	public function isOverTyping() {
		return $this->overTyping;
	}
	
	public function setOverTyping($b) {
		$this->overTyping = $b;
	}
	
	public function length() {
		return $this->buffer->length();
	}
	
	public function nextChar() {
		if ($this->cursor === $this->buffer->length()) {
			return "\x00";
		} else {
			return $this->buffer->charAt($this->cursor);
		}
	}
	
	public function current() {
		if ($this->cursor <= 0) {
			return "\x00";
		}
		
		return $this->buffer->charAt($this->cursor - 1);
	}
	
	/**
	 * Insert the specified chars into the buffer, setting the cursor to the end of the insertion point.
	 */
	public function write($str) {
		if ($str === null) throw new \InvalidArgumentException("Expected \$str to be non-null");
		
		if ($this->buffer->length() == 0) {
			$this->buffer->append($str);
		}
		else {
			$this->buffer->insert($this->cursor, $str);
		}
		
		$this->cursor += strlen($str);
		
		if ($this->isOverTyping() && $this->cursor < $this->buffer->length()) {
			$this->buffer->delete($this->cursor, $this->cursor + strlen($str));
		}
	}
	
	public function clear() {
		if ($this->buffer->length() == 0) {
			return false;
		}
		
		$this->buffer = new StringBuilder();
		$this->cursor = 0;
		return true;
	}
	
	public function __toString() {
		return $this->buffer->__toString();
	}
}
