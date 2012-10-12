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
 * Null completer.
 *
 * @author <a href="mailto:mwp1@cornell.edu">Marc Prud'hommeaux</a>
 * @author <a href="mailto:jason@planet57.com">Jason Dillon</a>
 * @author <a href="mailto:timwolla@bastelstu.be">Tim Düsterhus</a>
 * @since 2.3
 */
final class NullCompleter implements Completer
{
	public static $INSTANCE = null;

	public function complete($buffer, $cursor, array &$candidates) {
		return -1;
	}
	
	public static function instance() {
		if (self::$INSTANCE !== null) return;
		self::$INSTANCE = new self();
	}
}
NullCompleter::instance();