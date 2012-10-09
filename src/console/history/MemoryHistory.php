<?php
/*
 * Copyright (c) 2002-2012, the original author or authors.
 *
 * This software is distributable under the BSD license. See the terms of the
 * BSD license in the documentation provided with this software.
 *
 * http://www.opensource.org/licenses/bsd-license.php
 */
namespace phpline\console\history;

/**
 * Non-persistent {@link History}.
 *
 * @author <a href="mailto:mwp1@cornell.edu">Marc Prud'hommeaux</a>
 * @author <a href="mailto:jason@planet57.com">Jason Dillon</a>
 * @author <a href="mailto:timwolla@bastelstu.be">Tim Düsterhus</a>
 * @since 2.3
 */
class MemoryHistory implements History
{
	const DEFAULT_MAX_SIZE = 500;

	private $items = array();

	private $maxSize = self::DEFAULT_MAX_SIZE;

	private $ignoreDuplicates = true;

	private $autoTrim = false;

	// NOTE: These are all ideas from looking at the Bash man page:

	// TODO: Add ignore space? (lines starting with a space are ignored)

	// TODO: Add ignore patterns?

	// TODO: Add history timestamp?

	// TODO: Add erase dups?

	private $offset = 0;

	private $index = 0;

	public function isIgnoreDuplicates() {
		return $this->ignoreDuplicates;
	}

	public function setIgnoreDuplicates($flag) {
		$this->ignoreDuplicates = $flag;
	}

	public function isAutoTrim() {
		return $this->autoTrim;
	}

	public function setAutoTrim($flag) {
		$this->autoTrim = $flag;
	}

	public function count() {
		return count($this->items);
	}

	public function isEmpty() {
		return empty($this->items);
	}

	public function key() {
		return $this->offset + $this->index;
	}

	public function clear() {
		$this->items = array();
		$this->offset = 0;
		$this->index = 0;
	}
	
	public function rewind() {
		$this->index = 0;
	}

	public function get($index) {
		return $this->items[$index - $this->offset];
	}

	public function set($index, $item) {
		$this->items[$index - $this->offset] = $item;
	}

	public function add($item) {

		if ($this->isAutoTrim()) {
			$item = trim($item);
		}

		if ($this->isIgnoreDuplicates()) {
			if (!empty($this->items) && $item == end($this->items)) {
				return;
			}
		}

		$this->items[] = $item;
		
		while ($this->count() > $this->maxSize) array_shift($this->items);
	}

	public function remove($i) {
		$result = $this->items[$i];
		unset($this->items[$i]);
		$this->items = array_values($this->items);
		return $result;
	}

	public function removeFirst() {
		return array_shift($this->items);
	}

	public function removeLast() {
		return array_pop($this->items);
	}
	
	public function replace($item) {
		$result = array_pop($this->items);
		$this->add($item);
		return $result;
	}

	//
	// Navigation
	//

	/**
	 * This moves the history to the last entry. This entry is one position
	 * before the moveToEnd() position.
	 *
	 * @return Returns false if there were no history entries or the history
	 *		 index was already at the last entry.
	 */
	public function moveToLast() {
		$lastEntry = $this->count() - 1;
		if ($lastEntry >= 0 && $lastEntry != $this->index) {
			$this->index = $this->count() - 1;
			return true;
		}

		return false;
	}

	/**
	 * Move to the specified index in the history
	 * @param index
	 * @return
	 */
	public function seek($index) {
		$index -= $this->offset;
		if ($index >= 0 && $index < $this->count() ) {
			$this->index = $index;
			return true;
		}
		return false;
	}

	/**
	 * Moves the history index to the first entry.
	 *
	 * @return Return false if there are no entries in the history or if the
	 *		 history is already at the beginning.
	 */
	public function moveToFirst() {
		if ($this->count() > 0 && $this->index != 0) {
			$this->index = 0;
			return true;
		}

		return false;
	}

	/**
	 * Move to the end of the history buffer. This will be a blank entry, after
	 * all of the other entries.
	 */
	public function moveToEnd() {
		$this->index = $this->count();
	}

	/**
	 * Return the content of the current buffer.
	 */
	public function current() {
		if ($this->index >= $this->count()) {
			return "";
		}

		return $this->items[$this->index];
	}

	/**
	 * Move the pointer to the previous element in the buffer.
	 *
	 * @return true if we successfully went to the previous element
	 */
	public function previous() {
		if ($this->index <= 0) {
			return false;
		}

		$this->index--;

		return true;
	}

	/**
	 * Move the pointer to the next element in the buffer.
	 *
	 * @return true if we successfully went to the next element
	 */
	public function next() {
		if ($this->index >= $this->count()) {
			return false;
		}

		$this->index++;
	
		return true;
	}
	public function valid() {
		return isset($this->items[$this->index]); 
	}
	
}
