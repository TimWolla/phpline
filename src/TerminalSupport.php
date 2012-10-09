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
 * Provides support for {@link Terminal} instances.
 *
 * @author <a href="mailto:jason@planet57.com">Jason Dillon</a>
 * @author <a href="mailto:timwolla@bastelstu.be">Tim Düsterhus</a>
 * @since 2.0
 */
abstract class TerminalSupport implements Terminal
{
	const DEFAULT_WIDTH = 80;
	
	const DEFAULT_HEIGHT = 24;
	
	private $shutdownTask;
	
	private $supported = false;
	
	private $echoEnabled = false;
	
	private $ansiSupported = false;
	
	private $isRestored = true;
	
	protected function __construct($supported) {
		$this->supported = $supported;
		
		// Register a task to restore the terminal on shutdown
		register_shutdown_function(array($this, 'restore'));
	}
	
	public function init() {
		$this->isRestored = false;
	}
	
	public function restore() {
		if ($this->isRestored) return;
		$this->isRestored = true;
		TerminalFactory::resetIf($this);
	}
	
	public function reset() {
		$this->restore();
		$this->init();
	}
	
	public function isSupported() {
		return $this->supported;
	}
	
	public function isAnsiSupported() {
		return $this->ansiSupported;
	}
	
	protected function setAnsiSupported($supported) {
		$this->ansiSupported = $supported;
		Log::debug("Ansi supported: ", $supported);
	}
	
	/**
	 * Subclass to change behavior if needed. 
	 * @return the passed out
	 */
	public function wrapOutIfNeeded($out) {
		return $out;
	}
	
	/**
	 * Defaults to true which was the behaviour before this method was added.
	 */
	public function hasWeirdWrap() {
		return true;
	}
	
	public function getWidth() {
		return self::DEFAULT_WIDTH;
	}
	
	public function getHeight() {
		return self::DEFAULT_HEIGHT;
	}
	
	public function isEchoEnabled() {
		return $this->echoEnabled;
	}
	
	public function setEchoEnabled($enabled) {
		$this->echoEnabled = $enabled;
		Log::debug("Echo enabled: ", $enabled);
	}
	
	public function wrapInIfNeeded($in) {
		return $in;
	}
}