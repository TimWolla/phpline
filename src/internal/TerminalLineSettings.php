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
 * Provides access to terminal line settings via <tt>stty</tt>.
 *
 * @author <a href="mailto:mwp1@cornell.edu">Marc Prud'hommeaux</a>
 * @author <a href="mailto:dwkemp@gmail.com">Dale Kemp</a>
 * @author <a href="mailto:jason@planet57.com">Jason Dillon</a>
 * @author <a href="mailto:jbonofre@apache.org">Jean-Baptiste Onofré</a>
 * @author <a href="mailto:timwolla@bastelstu.be">Tim Düsterhus</a>
 * @since 2.0
 */
final class TerminalLineSettings
{
	private $sttyCommand = "";
	
	private $config = "";
	
	private $configLastFetched = 0;
	
	public function __construct() {
		$this->sttyCommand = Configuration::getString("phpline.stty", "stty");
		$this->config = $this->get("-a");
		$this->configLastFetched = microtime(true);
		
		Log::debug("Config: ", $this->config);
		
		// sanity check
		if (strlen($this->config) == 0) {
			throw new \UnexpectedValueException("Unrecognized stty code: ".$this->config);
		}
	}
	
	public function getConfig() {
		return $this->config;
	}
	
	public function restore() {
		$this->set("sane");
	}
	
	public function get($args) {
		return $this->stty($args);
	}
	
	public function set($args) {
		$this->stty($args);
	}
	
	/**
	 * <p>
	 * Get the value of a stty property, including the management of a cache.
	 * </p>
	 *
	 * @param name the stty property.
	 * @return the stty property value.
	 */
	public function getProperty($name) {
		if ($name === null) throw new \InvalidArgumentException("Expected \$name to be non-null");
		try {
			// tty properties are cached so we don't have to worry too much about getting term widht/height
			if ($this->config === null || (microtime(true) - $this->configLastFetched) > 1) {
				$this->config = $this->get("-a");
				$this->configLastFetched = microtime(true);
			}
			return self::_getProperty($name, $this->config);
		} catch (\Exception $e) {
			return -1;
		}
	}
	
	/**
	 * <p>
	 * Parses a stty output (provided by stty -a) and return the value of a given property.
	 * </p>
	 *
	 * @param name property name.
	 * @param stty string resulting of stty -a execution.
	 * @return value of the given property.
	 */
	public static function _getProperty($name, $stty) {
		// try the first kind of regex
		$pattern = $name."\\s+=\\s+([^;]*)[;\\n\\r]";
		
		if (!preg_match('~'.$pattern.'~', $stty, $matches)) {
			// try a second kind of regex
			$pattern = $name."\\s+([^;]*)[;\\n\\r]";
			
			if (!preg_match('~'.$pattern.'~', $stty, $matches)) {
				// try a second try of regex
				$pattern = "(\\S*)\\s+".$name;
				if (!preg_match('~'.$pattern.'~', $stty, $matches)) {
					return -1;
				}
			}
		}
		return self::parseControlChar($matches[1]);
	}
	
	private static function parseControlChar($str) {
		// under
		if ("<undef>" === $str) {
			return -1;
		}
		// octal
		if (substr($str, 0, 1) === "0") {
			return intval($str, 8);
		}
		// decimal
		if (intval(substr($str, 0, 1)) >= 1 && intval(substr($str, 0, 1)) <= 9) {
			return intval($str, 10);
		}
		// control char
		if (substr($str, 0, 1) == '^') {
			if (substr($str, 1, 1) == '?') {
				return 127;
			} else {
				return substr($str, 1, 1) - 64;
			}
		} else if (substr($str, 0, 1) == 'M' && substr($str, 1, 1) == '-') {
			if (substr($str, 2, 1) == '^') {
				if (substr($str, 3, 1) == '?') {
					return 127 + 128;
				} else {
					return substr($str, 3, 1) - 64 + 128;
				}
			} else {
				return substr($str, 2, 1) + 128;
			}
		} else {
			return substr($str, 0, 1);
		}
	}
	
	private function stty($args) {
		if ($args === null) throw new \InvalidArgumentException("Expected \$args to be non-null");
		if (!function_exists('shell_exec')) throw new \UnexpectedValueException('shell_exec has to be enabled');
		
		// TODO: secure args
		$cmd = sprintf("%s %s < /dev/tty", $this->sttyCommand, $args);
		Log::trace("Running ", $cmd);
		return shell_exec($cmd);
	}
}