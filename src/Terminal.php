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

/**
 * Representation of the input terminal for a platform.
 *
 * @author <a href="mailto:mwp1@cornell.edu">Marc Prud'hommeaux</a>
 * @author <a href="mailto:jason@planet57.com">Jason Dillon</a>
 * @author <a href="mailto:timwolla@bastelstu.be">Tim Düsterhus</a>
 * @since 2.0
 */
interface Terminal
{
	public function init();
	
	public function restore();
	
	public function reset();
	
	public function isSupported();
	
	public function getWidth();
	
	public function getHeight();
	
	public function isAnsiSupported();
	
	/**
	 * When ANSI is not natively handled, the output will have to be wrapped.
	 */
	public function wrapOutIfNeeded($out);
	
	/**
	 * When using native support, return the InputStream to use for reading characters
	 * else return the input stream passed as a parameter.
	 *
	 * @since 2.6
	 */
	public function wrapInIfNeeded($in);
	
	/**
	 * For terminals that don't wrap when character is written in last column,
	 * only when the next character is written.
	 * These are the ones that have 'am' and 'xn' termcap attributes (xterm and
	 * rxvt flavors falls under that category)
	 */
	public function hasWeirdWrap();
	
	public function isEchoEnabled();
	
	public function setEchoEnabled($enabled);
	
}
