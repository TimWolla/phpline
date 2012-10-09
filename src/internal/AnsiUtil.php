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
 * AnsiUtil proves functions to handle strings with Ansi-Escape-sequences
 * 
 * @author Tim Düsterhus
 */
final class AnsiUtil {
	
	/**
	 * Strips Ansi-Escape-Sequences from the given string
	 */
	public static function stripAnsi($str) {
		$str = preg_replace("/\x1b\\[\\d+(;\\d+)?m/", '', $str);
		$str = preg_replace("/\x1b\\[\\d+;\\d+[fH]/", '', $str);
		$str = preg_replace("/\x1b\\[\\d+[ABCDEFGJKST]/", '', $str);
		$str = preg_replace("/\x1b\\[[su]/", '', $str);
		
		return $str;
	}
}
