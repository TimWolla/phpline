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
 * Console history.
 *
 * @author <a href="mailto:mwp1@cornell.edu">Marc Prud'hommeaux</a>
 * @author <a href="mailto:jason@planet57.com">Jason Dillon</a>
 * @author <a href="mailto:timwolla@bastelstu.be">Tim Düsterhus</a>
 * @since 2.3
 */
interface History extends \SeekableIterator, \Countable
{
	public function isEmpty();

	public function clear();

	public function get($index);

	public function add($line);

	/**
	 * Set the history item at the given index to the given CharSequence.
	 *
	 * @param index the index of the history offset
	 * @param item the new item
	 * @since 2.7
	*/
	public function set($index, $item);

	/**
	 * Remove the history element at the given index.
	 *
	 * @param i the index of the element to remove
	 * @return the removed element
	 * @since 2.7
	*/
	public function remove($i);

	/**
	 * Remove the first element from history.
	 *
	 * @return the removed element
	 * @since 2.7
	*/
	public function removeFirst();

	/**
	 * Remove the last element from history
	 *
	 * @return the removed element
	 * @since 2.7
	*/
	public function removeLast();

	public function replace($item);

	//
	// Navigation
	//

	public function previous();

	public function moveToLast();

	public function moveToEnd();
}
