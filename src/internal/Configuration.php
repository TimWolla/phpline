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
	const PHPLINE_CONFIGURATION = "phpline.configuration";

	/**
	 * Default configuration file name loaded from user's home directory.
	 */
	const PHPLINE_RC = ".phpline.rc";

	private static $properties = null;

	private static function initProperties() {
		$url = self::determineUrl();
		
		$props = array();
		try {
			$props = self::loadProperties($url);
		}
		catch (\RuntimeException $e) {
			// debug here instead of warn, as this can happen normally if default phpline.rc file is missing
			Log::debug("Unable to read configuration from: ", $url, $e);
		}
		
		return $props;
	}
	
	private static function loadProperties($url) {
		if (!file_exists($url)) throw new \RuntimeException('Cannot find configuration file');
		$properties = parse_ini_file($url);
		
		Log::debug('Loaded properties:');
		
		foreach ($properties as $key => $value) {
			Log::debug("  ", $key, "=", $value);
		}
		
		return $properties;
	}
	
	private static function determineUrl() {
		// See if user has customized the configuration location via sysprop
		// $tmp = System.getProperty(JLINE_CONFIGURATION);
		// if (tmp != null) {
		//	return Urls.create(tmp);
		// }
		// else {
			// Otherwise try the default
			$file = rtrim(self::getUserHome(), '/').'/'.self::PHPLINE_RC;
			return $file;
		// }
	}
	
	public static function reset() {
		Log::debug("Resetting");
		self::$properties = null;
		
		// force new properties to load
		self::getProperties();
	}
	
	public static function getProperties() {
		if (self::$properties === null) {
			self::$properties = self::initProperties();
		}
		
		return self::$properties;
	}
	
	public static function getString($name, $defaultValue = null) {
		if ($name === null) throw new \InvalidArgumentException("Expected \$name to be non-null");
		
		$value = null;
		
		// Check sysprops first, it always wins
		// value = System.getProperty(name);
		
		if ($value === null) {
			// Next try userprops
			$props = self::getProperties();
			
			$value = isset($props[$name]) && $props[$name] !== null ? $props[$name] : $defaultValue;
		}
		
		return $value;
	}
	
	public static function getBoolean($name, $defaultValue) {
		$value = self::getString($name);
		
		if ($value === null) return $defaultValue;
		$value = strtolower($value);
		
		return strlen($value) === 0 || $value === "1" || $value === "on" || $value === "true";
	}
	
	public static function getInteger($name, $defaultValue) {
		$str = self::getString($name);
		
		if ($str === null) {
			return $defaultValue;
		}
		
		return intval($str);
	}

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