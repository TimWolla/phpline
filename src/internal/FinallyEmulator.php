<?php
/*
 * Copyright (c) 2002-2012, the original author or authors.
 *
 *       DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE
 *                    Version 2, December 2004
 *
 * Copyright (C) 2004 Sam Hocevar
 *  14 rue de Plaisance, 75014 Paris, France
 * Everyone is permitted to copy and distribute verbatim or modified
 * copies of this license document, and changing it is allowed as long
 * as the name is changed.
 * 
 *            DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE
 *   TERMS AND CONDITIONS FOR COPYING, DISTRIBUTION AND MODIFICATION
 * 
 *  0. You just DO WHAT THE FUCK YOU WANT TO.
 */
namespace phpline\internal;

/**
 * Emulates finally in PHP.
 *
 * @author <a href="https://github.com/attilammagyar">Attila Magyar</a>
 */
class FinallyEmulator
{
	private $callable = null;
	
	public function __construct($callable) {
		if (!is_callable($callable)) throw new \InvalidArgumentException("\$callable is not callable");
		$this->callable = $callable;
	}
	
	public function __destruct() {
		$this->invoke();
	}
	
	public function __invoke() {
		$this->invoke();
	}
	
	private function invoke() {
		if ($this->callable !== null) {
			$callable = $this->callable;
			$this->callable = null;
			
			$callable();
		}
	}
}
