<?php
namespace phpline\javaApi;

/**
 * Equivalent to Java's StringBuilder.
 * http://docs.oracle.com/javase/6/docs/api/java/lang/StringBuilder.html
 * 
 * @author Tim Düsterhus
 */
final class StringBuilder implements CharSequence {
	private $str = "";
	public function __construct($str = "") {
		$this->str = (string) $str;
	}
	
	public function append($str, $offset = null, $len = null) {
		$this->insert($this->length(), $str);
	}
	
	public function charAt($index) {
		if ($index > $this->length()) throw new \OutOfBoundsException("\$index is out of range");
		return $this->str{$index};
	}
	
	public function delete($start, $end) {
		if ($start > $this->length()) throw new \OutOfBoundsException("\$start is out of range");
		if ($start < 0) throw new \OutOfBoundsException("\$start is out of range");
		if ($start > $end) throw new \OutOfBoundsException("\$start is out of range");
		
		$this->replace($start, $end, '');
	}
	
	public function setCharAt($index, $char) {
		if ($index > $this->length()) throw new \OutOfBoundsException("\$index is out of range");
		$this->replace($index, $index + 1, $char);
	}
	
	public function deleteCharAt($index) {
		if ($index > $this->length()) throw new \OutOfBoundsException("\$index is out of range");
		$this->delete($index, $index + 1);
	}
	
	public function indexOf($str, $fromIndex = 0) {
		return strpos($this->str, $str, $fromIndex);
	}
	
	public function lastIndexOf($str, $fromIndex = 0) {
		return strrpos($this->str, $str, $fromIndex);
	}
	
	public function insert($index, $str, $offset = null, $len = null) {
		if ($offset === null && $len !== null || $offset !== null && $len === null) throw new \InvalidArgumentException('Both $offset and $len have to be set');
		
		if (is_array($str)) {
			if ($offset !== null) {
				$str = array_slice($str, $offset, $len);
			}
			
			$str = implode('', $str);
		}
		
		$this->replace($index, $index, $str);
	}
	
	public function length() {
		return strlen($this->str);
	}
	
	public function replace($start, $end, $str) {
		$this->str = $this->substring(0, $start).((string) $str).$this->substring($end);
	}
	
	public function substring($start, $end = null) {
		if ($start < 0) throw new \OutOfBoundsException('$start must be greater than zero');
		if ($start > $this->length()) throw new \OutOfBoundsException('$start must be smaller than the length');
		if ($end === null) $end = $this->length();
		
		return substr($this->str, $start, $end - $start);
	}
	
	public function setLength($newLength) {
		if ($newLength < 0) throw new \OutOfBoundsException('$newLength must be greater than zero');
		
		$this->str = $this->substring(0, $newLength);
		$this->str = str_pad($this->str, $newLength, chr(0));
	}
	
	public function __toString() {
		return $this->str;
	}
}
