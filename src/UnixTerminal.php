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
use \phpline\internal\Log;

/**
 * Terminal that is used for unix platforms. Terminal initialization
 * is handled by issuing the <em>stty</em> command against the
 * <em>/dev/tty</em> file to disable character echoing and enable
 * character input. All known unix systems (including
 * Linux and Macintosh OS X) support the <em>stty</em>), so this
 * implementation should work for an reasonable POSIX system.
 *
 * @author <a href="mailto:mwp1@cornell.edu">Marc Prud'hommeaux</a>
 * @author <a href="mailto:dwkemp@gmail.com">Dale Kemp</a>
 * @author <a href="mailto:jason@planet57.com">Jason Dillon</a>
 * @author <a href="mailto:jbonofre@apache.org">Jean-Baptiste Onofré</a>
 * @author <a href="mailto:timwolla@bastelstu.be">Tim Düsterhus</a>
 * @since 2.0
 */
class UnixTerminal extends TerminalSupport
{
	private $settings = null;

	public function __construct() {
		$this->settings = new internal\TerminalLineSettings();
		
		parent::__construct(true);
	}

	protected function getSettings() {
		return $this->settings;
	}

	/**
	 * Remove line-buffered input by invoking "stty -icanon min 1"
	 * against the current terminal.
	 */
	public function init() {
		parent::init();
		
		$this->setAnsiSupported(true);
		
		// set the console to be character-buffered instead of line-buffered
		// also make sure we're distinguishing carriage return from newline
		$this->settings->set("-icanon min 1 -icrnl -inlcr");
		
		$this->setEchoEnabled(false);
	}
	
	/**
	 * Restore the original terminal configuration, which can be used when
	 * shutting down the console reader. The ConsoleReader cannot be
	 * used after calling this method.
	 */
	public function restore() {
		$this->settings->restore();
		parent::restore();
		// print a newline after the terminal exits.
		// this should probably be a configurable.
		echo PHP_EOL;
	}
	
	/**
	 * Returns the value of <tt>stty columns</tt> param.
	 */
	public function getWidth() {
		$w = $this->settings->getProperty("columns");
		return $w < 1 ? self::DEFAULT_WIDTH : $w;
	}
	
	/**
	 * Returns the value of <tt>stty rows>/tt> param.
	 */
	public function getHeight() {
		$h = $this->settings->getProperty("rows");
		return $h < 1 ? self::DEFAULT_HEIGHT : $h;
	}
	
	public function setEchoEnabled($enabled) {
		try {
			if ($enabled) {
				$this->settings->set("echo");
			}
			else {
				$this->settings->set("-echo");
			}
			parent::setEchoEnabled($enabled);
		}
		catch (\Exception $e) {
			Log::error("Failed to ", ($enabled ? "enable" : "disable"), " echo");
		}
	}
	
	public function disableInterruptCharacter() {
		try {
			$this->settings->set("intr undef");
		}
		catch (\Exception $e) {
			Log::error("Failed to disable interrupt character", $e);
		}
	}
	
	public function enableInterruptCharacter() {
		try {
			$this->settings->set("intr ^C");
		}
		catch (\Exception $e) {
			Log::error("Failed to enable interrupt character", $e);
		}
	}
}
