<?php
/*
 * Copyright (c) 2002-2012, the original author or authors.
 *
 * This software is distributable under the BSD license. See the terms of the
 * BSD license in the documentation provided with this software.
 *
 * http://www.opensource.org/licenses/bsd-license.php
 */
namespace phpline\javaApi;

/**
 * Equivalent to Java's CharSequence.
 * http://docs.oracle.com/javase/6/docs/api/java/lang/CharSequence.html
 * 
 * @author Tim Düsterhus
 */
interface CharSequence {
	public function charAt($index);
	public function length();
	public function __toString();
}

