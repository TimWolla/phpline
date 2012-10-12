<?php
/*
 * Copyright (c) 2002-2012, the original author or authors.
 *
 * This software is distributable under the BSD license. See the terms of the
 * BSD license in the documentation provided with this software.
 *
 * http://www.opensource.org/licenses/bsd-license.php
 */
namespace phpline\console\completer;

/**
 * Completer for a set of strings.
 *
 * @author <a href="mailto:jason@planet57.com">Jason Dillon</a>
 * @author <a href="mailto:timwolla@bastelstu.be">Tim Düsterhus</a>
 * @since 2.3
 */
class StringsCompleter
	implements Completer
{
	private $strings = array();

	public function __construct(array $strings) {
		$this->strings = $strings;
	}
	
	public function getStrings() {
		return $this->strings;
	}

	public function complete($buffer, $cursor, array &$candidates) {
		if ($buffer === null) {
			foreach ($this->strings as $string) $candidates[] = $string;
		}
		else {
			foreach ($this->strings as $string) {
				if (strpos($string, $buffer) === 0) {
					$candidates[] = $string;
				}
			}
		}
		
		if (count($candidates) == 1) {
			$candidates[0] = $candidates[0]." ";
		}
		sort($candidates);
		return empty($candidates) ? -1 : 0;
	}
}