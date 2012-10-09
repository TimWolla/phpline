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

// Based on Apache Karaf impl

/**
 * Non-interruptible (via CTRL-C) {@link UnixTerminal}.
 *
 * @since 2.0
 */
class NoInterruptUnixTerminal
	extends UnixTerminal
{

	public function init() {
		parent::init();
		$this->getSettings()->set("intr undef");
	}

	public function restore() {
		$this->getSettings()->set("intr ^C");
		parent::restore();
	}
}
