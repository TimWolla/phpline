<?php
/*
 * Copyright (c) 2002-2012, the original author or authors.
 *
 * This software is distributable under the BSD license. See the terms of the
 * BSD license in the documentation provided with this software.
 *
 * http://www.opensource.org/licenses/bsd-license.php
 */
namespace phpline;
use \phpline\internal\Configuration;
use \phpline\internal\Log;

/**
 * Creates terminal instances.
 *
 * @author <a href="mailto:jason@planet57.com">Jason Dillon</a>
 * @author <a href="mailto:timwolla@bastelstu.be">Tim Düsterhus</a>
 * @since 2.0
 */
class TerminalFactory
{
	private static $holder = null;
	
	public static function create() {
		$type = Configuration::getString("phpline.terminal", "auto");
		
		if (isset($_SERVER['TERM']) && $_SERVER['TERM'] === 'dumb') {
			$type = "none";
			Log::debug("\$TERM=dumb; setting type=", $type);
		}
		
		Log::debug("Creating terminal; type=", $type);
		
		try {
			$tmp = strtolower($type);
			
			if ($tmp === "auto") {
				$os = PHP_OS;
				$tmp = "unix";
				if (stripos($os, "win") !== false) {
					$tmp = "win";
				}
			}
			
			if ($tmp === "unix") {
				$t = new UnixTerminal();
			}
			else if ($tmp === "win" || $tmp === "windows") {
				$t = new UnsupportedTerminal(); //AnsiWindowsTerminal();
			}
			else if ($tmp === "none" || $tmp === "off" || $tmp == "false") {
				$t = new UnsupportedTerminal();
			}
			else {
				try {
					if (class_exists($type)) $t = new $type();
					else throw new \Exception('Class not found');
					
					if (!($t instanceof Terminal)) throw new \Exception("$type is not of type phpline\Terminal");
				}
				catch (\Exception $e) {
					throw new \InvalidArgumentException("Invalid terminal type: $type", 0, $e);
				}
			}
		}
		catch (\Exception $e) {
			Log::error("Failed to construct terminal; falling back to unsupported", $e);
			$t = new UnsupportedTerminal();
		}
		
		Log::debug("Created Terminal: ", get_class($t));
		
		try {
			$t->init();
		}
		catch (\Exception $e) {
			Log::error("Terminal initialization failed; falling back to unsupported", $e);
			return new UnsupportedTerminal();
		}
		
		return $t;
	}
	
	public static function reset() {
		self::$holder = null;
	}
	
	public static function resetIf(Terminal $t) {
		if (self::$holder === $t) {
			self::reset();
		}
	}
	
	public static function get() {
		$t = self::$holder;
		if ($t === null) {
			$t = self::create();
			self::$holder = $t;
		}
		return $t;
	}
}
