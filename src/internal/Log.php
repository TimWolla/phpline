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
 * Internal logger.
 *
 * @author <a href="mailto:jason@planet57.com">Jason Dillon</a>
 * @author <a href="mailto:timwolla@bastelstu.be">Tim Düsterhus</a>
 * @since 2.0
 */
final class Log
{
	///CLOVER:OFF

	const TRACE = true;

	const DEBUG = true;

	private static $output = STDERR;

	public static function getOutput() {
		return self::$output;
	}

	public static function setOutput($out) {
		if ($out === null) throw new \InvalidArgumentException("Expected \$out to be non-null");
		self::$output = $out;
	}

	/**
	 * Helper to support rendering messages.
	 */
	public static function render($out, $message) {
		if (is_array($message)) {
			fwrite($out, "[");
			for ($i = 0; $i < count($message); $i++) {
				fwrite($out, $message[$i]);
				if ($i + 1 < count($message)) {
					fwrite($out, ",");
				}
			}
			fwrite($out, "]");
		}
		else {
			fwrite($out, $message);
		}
	}
	
	public static function log($level) {
		fwrite(self::$output, sprintf("[%s] ", $level));
		$messages = func_get_args();
		$messages = $messages[1];
		
		for ($i=0; $i<count($messages); $i++) {
			// Special handling for the last message if its a throwable, render its stack on the next line
			if ($i + 1 == count($messages) && $messages[$i] instanceof \Exception) {
				fwrite(self::$output, "\n");
				fwrite(self::$output, $messages[$i]);
			}
			else {
				self::render(self::$output, $messages[$i]);
			}
		}
		
		fwrite(self::$output, "\n");
		fflush(self::$output);
	}
	
	public static function trace() {
		if (self::TRACE) {
			self::log(Level::TRACE, func_get_args());
		}
	}
	
	public static function debug() {
		if (self::TRACE || self::DEBUG) {
			self::log(Level::DEBUG, func_get_args());
		}
	}
	
	/**
	 * @since 2.7
	 */
	public static function info() {
		self::log(Level::INFO, func_get_args());
	}
	
	public static function warn() {
		self::log(Level::WARN, func_get_args());
	}
	
	public static function error() {
		self::log(Level::ERROR, func_get_args());
	}
}

final class Level {
	const TRACE = "TRACE";
	const DEBUG = "DEBUG";
	const INFO = "INFO";
	const WARN = "WARN";
	const ERROR = "ERROR";
}