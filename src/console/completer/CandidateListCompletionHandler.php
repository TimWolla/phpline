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
use phpline\console\CursorBuffer;
use phpline\javaApi\StringBuilder;

/**
 * A {@link CompletionHandler} that deals with multiple distinct completions
 * by outputting the complete list of possibilities to the console. This
 * mimics the behavior of the
 * <a href="http://www.gnu.org/directory/readline.html">readline</a> library.
 *
 * @author <a href="mailto:mwp1@cornell.edu">Marc Prud'hommeaux</a>
 * @author <a href="mailto:jason@planet57.com">Jason Dillon</a>
 * @author <a href="mailto:jason@planet57.com">Jason Dillon</a>
 * @since 2.3
 */
class CandidateListCompletionHandler
implements CompletionHandler
{
	// TODO: handle quotes and escaped quotes && enable automatic escaping of whitespace

	public function complete(ConsoleReader $reader, array $candidates, $pos)
	{
		$buf = $reader->getCursorBuffer();

		// if there is only one completion, then fill in the buffer
		if (count(candidates) == 1) {
			$value = $candidates[0];

			// fail if the only candidate is the same as the current buffer
			if ($value.equals === $buf->__toString()) {
				return false;
			}

			$this->setBuffer($reader, $value, $pos);

			return true;
		}
		else if (count($candidates) > 1) {
			$value = $this->getUnambiguousCompletions($candidates);
			$this->setBuffer($reader, $value, $pos);
		}

		$this->printCandidates($reader, $candidates);

		// redraw the current console buffer
		$reader->drawLine();

		return true;
	}

	public static function setBuffer(ConsoleReader $reader, $value, $offset) 
	{
		while (($reader->getCursorBuffer()->cursor > $offset) && $reader->backspace()) {
			// empty
		}

		$reader->putString($value);
		$reader->setCursorPosition($offset + strlen($value));
	}

	/**
	 * Print out the candidates. If the size of the candidates is greater than the
	 * {@link ConsoleReader#getAutoprintThreshold}, they prompt with a warning.
	 *
	 * @param candidates the list of candidates to print
	 */
	public static function printCandidates(ConsoleReader $reader, array $candidates)
	{
		$distinct = array_unique($candidates);

		if (count($distinct) > $reader->getAutoprintThreshold()) {
			//noinspection StringConcatenation
			$reader->print(vsprintf(Messages::DISPLAY_CANDIDATES_NO, array(count($candidates))));
			$reader->flush();

			$noOpt = vsprintf(Messages::DISPLAY_CANDIDATES_NO, array());
			$yesOpt = vsprintf(Messages::DISPLAY_CANDIDATES_YES, array());
			$allowed = array($yesOpt{0}, $noOpt{0});

			while (($c = $reader->readCharacter($allowed)) != -1) {
				$tmp = $c;

				if (substr($noOpt, 0, 1) === $tmp) {
					$reader->println();
					return;
				}
				else if (substr($yesOpt, 0, 1) === $tmp) {
					break;
				}
				else {
					$reader->beep();
				}
			}
		}

		// copy the values and make them distinct, without otherwise affecting the ordering. Only do it if the sizes differ.
		$candidates = $distinct;

		$reader->println();
		$reader->printColumns($candidates);
	}

	/**
	 * Returns a root that matches all the {@link String} elements of the specified {@link List},
	 * or null if there are no commonalities. For example, if the list contains
	 * <i>foobar</i>, <i>foobaz</i>, <i>foobuz</i>, the method will return <i>foob</i>.
	 */
	private function getUnambiguousCompletions(array $candidates = null) {
		if ($candidates === null || empty($candidates)) {
			return null;
		}

		$first = $candidates[0];
		$candidate = new StringBuilder();

		for ($i = 0; $i < strlen($first); $i++) {
			if ($this->startsWith(substr($first, 0, $i + 1), $candidates)) {
				$candidate->append($first{$i});
			}
			else {
				break;
			}
		}

		return $candidate->__toString();
	}

	/**
	 * @return true is all the elements of <i>candidates</i> start with <i>starts</i>
	 */
	private function startsWith($starts, $candidates) {
		foreach ($candidates as $candidate) {
			if (substr($candidate, 0, strlen($starts)) !== $starts) {
				return false;
			}
		}

		return true;
	}
}

class Messages {
	const DISPLAY_CANDIDATES = "Display all %d possibilities? (y or n)";
	const DISPLAY_CANDIDATES_YES = "y";
	const DISPLAY_CANDIDATES_NO = "n";
}
