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
 * Provides access to configuration values.
 *
 * @author <a href="mailto:jason@planet57.com">Jason Dillon</a>
 * @author <a href="mailto:gnodet@gmail.com">Guillaume Nodet</a>
 * @author <a href="mailto:timwolla@bastelstu.be">Tim Düsterhus</a>
 * @since 2.4
 */
class Configuration
{
	/**
	 * System property which can point to a file or URL containing configuration properties to load.
	 *
	 * @since 2.7
	 */
	const JLINE_CONFIGURATION = "jline.configuration";

	/**
	 * Default configuration file name loaded from user's home directory.
	 */
	const JLINE_RC = ".jline.rc";

	//
	// System property helpers
	//

	/**
	* @since 2.7
	*/
	public static function getLineSeparator() {
		return PHP_EOL;
	}

	public static function getUserHome() {
		if (isset($_SERVER['HOME'])) return $_SERVER['HOME'];
		if (isset($_SERVER['HOMEDRIVE']) && $_SERVER['HOMEPATH']) return $_SERVER['HOMEDRIVE'].$_SERVER['HOMEPATH'];
		throw new \RuntimeException("Cannot find userhome");
	}

	public static function getOsName() {
		return PHP_OS;
	}

	/**
	* @since 2.7
	*/
	public static function isWindows() {
		return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
	}
}