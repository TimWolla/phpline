<?php
/*
 * Copyright (c) 2002-2012, the original author or authors.
*
* This software is distributable under the BSD license. See the terms of the
* BSD license in the documentation provided with this software.
*
* http://www.opensource.org/licenses/bsd-license.php
*/
namespace phpline\console\completer;

use phpline\console\ConsoleReader;

/**
 * Handler for dealing with candidates for tab-completion.
 *
 * @author <a href="mailto:mwp1@cornell.edu">Marc Prud'hommeaux</a>
 * @author <a href="mailto:jason@planet57.com">Jason Dillon</a>
 * @author <a href="mailto:jason@planet57.com">Jason Dillon</a>
 * @since 2.3
 */
interface CompletionHandler
{
	public function complete(ConsoleReader $reader, array $candidates, $position);
}
