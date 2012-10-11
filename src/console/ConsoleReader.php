<?php
/*
 * Copyright (c) 2002-2012, the original author or authors.
 *
 * This software is distributable under the BSD license. See the terms of the
 * BSD license in the documentation provided with this software.
 *
 * http://www.opensource.org/licenses/bsd-license.php
 */
namespace phpline\console;

use phpline\Terminal;
use phpline\TerminalFactory;
use phpline\console\completer\CandidateListCompletionHandler;
use phpline\console\completer\Completer;
use phpline\console\completer\CompletionHandler;
use phpline\console\history\History;
use phpline\console\history\MemoryHistory;
use phpline\internal\Configuration;
use phpline\internal\Log;
use phpline\internal\NonBlockingInputStream;
use phpline\internal\FinallyEmulator;
use phpline\javaApi\StringBuilder;

/**
 * Possible states in which the current readline operation may be in.
 */
class State {
	/**
	 * The user is just typing away
	 */
	const NORMAL = 0;
	/**
	 * In the middle of a emacs seach
	 */
	const SEARCH = 1;
	/**
	 * VI "yank-to" operation ("y" during move mode)
	 */
	const VI_YANK_TO = 2;
	/**
	 * VI "delete-to" operation ("d" during move mode)
	 */
	const VI_DELETE_TO = 3;
	/**
	 * VI "change-to" operation ("c" during move mode)
	 */
	const VI_CHANGE_TO = 4;
}

/**
 * A reader for console applications. It supports custom tab-completion,
 * saveable command history, and command line editing. On some platforms,
 * platform-specific commands will need to be issued before the reader will
 * function properly. See {@link jline.Terminal#init} for convenience
 * methods for issuing platform-specific setup commands.
 *
 * @author <a href="mailto:mwp1@cornell.edu">Marc Prud'hommeaux</a>
 * @author <a href="mailto:jason@planet57.com">Jason Dillon</a>
 * @author <a href="mailto:gnodet@gmail.com">Guillaume Nodet</a>
 * @author <a href="mailto:timwolla@bastelstu.be">Tim Düsterhus</a> 
 */
class ConsoleReader
{
	const JLINE_NOBELL = "jline.nobell";
	
	const JLINE_ESC_TIMEOUT = "jline.esc.timeout";

	const JLINE_INPUTRC = "jline.inputrc";

	const INPUT_RC = ".inputrc";

	const DEFAULT_INPUT_RC = "/etc/inputrc";

	const BACKSPACE = "\x08";

	const RESET_LINE = "\r";

	const KEYBOARD_BELL = "\x07";

	const NULL_MASK = "\x00";

	const TAB_WIDTH = 4;

	//private static final ResourceBundle
	//	resources = ResourceBundle.getBundle(CandidateListCompletionHandler.class.getName());

	private $terminal;

	private $out;

	private $buf;

	private $prompt;
	private $promptLen;

	private $expandEvents = true;

	private $bellEnabled = false;

	private $mask;

	private $echoCharacter;

	private $searchTerm = null;

	private $previousSearchTerm = "";

	private $searchIndex = -1;

	private $parenBlinkTimeout = 500;

	/*
	 * The reader and the nonBlockingInput go hand-in-hand.  The reader wraps
	 * the nonBlockingInput, but we have to retain a handle to it so that
	 * we can shut down its blocking read thread when we go away.
	 */
	private $in;
	private $escapeTimeout;
	private $reader;

	/*
	 * TODO: Please read the comments about this in setInput(), but this needs
	 * to be done away with.
	 */
	private $isUnitTestInput;

	/**
	 * Last character searched for with a vi character search
	 */
	private $charSearchChar = "\x00";		   // Character to search for
	private $charSearchLastInvokeChar = "\x00"; // Most recent invocation key
	private $charSearchFirstInvokeChar = "\x00";// First character that invoked

	/**
	 * The vi yank buffer
	 */
	private $yankBuffer = "";

	private $encoding;

	private $recording;

	private $macro = "";

	private $appName;

	private $inputrcUrl;

	private $consoleKeys;

	private $commentBegin = null;

	private $skipLF = false;

	/*
	 * Current internal state of the line reader
	 */
	private $state = State::NORMAL;

	public function __construct($appName = null, $in = null, $out = null, Terminal $term = null, $encoding = "") {
		$this->bellEnabled = !Configuration::getBoolean(self::JLINE_NOBELL, true);
		$this->autoprintTreshold = Configuration::getInteger(self::JLINE_COMPLETION_THRESHOLD, 100); // same default as bash;
		
		$this->buf = new CursorBuffer();
		$this->completionHandler = new CandidateListCompletionHandler();
		$this->history = new MemoryHistory();
		$this->appName = $appName ?: "PHPLine";
		$this->encoding = $encoding ?: "utf-8";
		$this->out = $out ?: STDOUT;
		$this->terminal = $term ?: TerminalFactory::get();
		
		$this->setInput($in ?: STDIN);
		
		$this->inputrcUrl = $this->getInputRc();

		$this->consoleKeys = new ConsoleKeys($this->appName, $this->inputrcUrl);
	}

	private function getInputRc() {
		$path = Configuration::getString(self::JLINE_INPUTRC);
		if ($path === null) {
			$f = Configuration::getUserHome().'/'.self::INPUT_RC;
			if (!file_exists($f)) {
				$f = self::DEFAULT_INPUT_RC;
			}
			return $f;
		} else {
			return $path;
		}
	}

	public function getKeys() {
		return $this->consoleKeys->getKeys();
	}

	public function setInput($in) {
		$this->escapeTimeout = Configuration::getInteger(self::JLINE_ESC_TIMEOUT, 100);
		/*
		 * This is gross and here is how to fix it. In getCurrentPosition()
		 * and getCurrentAnsiRow(), the logic is disabled when running unit
		 * tests and the fact that it is a unit test is determined by knowing
		 * if the original input stream was a ByteArrayInputStream. So, this
		 * is our test to do this.  What SHOULD happen is that the unit
		 * tests should pass in a terminal that is appropriately configured
		 * such that whatever behavior they expect to happen (or not happen)
		 * happens (or doesn't).
		 *
		 * So, TODO, get rid of this and fix the unit tests.
		 */
		$this->isUnitTestInput = false;//in instanceof ByteArrayInputStream;
		$nonBlockingEnabled =
			   $this->escapeTimeout > 0
			&& $this->terminal->isSupported()
			&& $in !== null;

		$wrapped = $this->terminal->wrapInIfNeeded( $in );

		$this->in = new NonBlockingInputStream($wrapped, $nonBlockingEnabled);
		$this->reader = $this->in;
	}

	public function getInput() {
		return $this->in;
	}

	public function getOutput() {
		return $this->out;
	}

	public function getTerminal() {
		return $this->terminal;
	}

	public function getCursorBuffer() {
		return $this->buf;
	}

	public function setExpandEvents($expand) {
		$this->expandEvents = $expand;
	}

	public function getExpandEvents() {
		return $this->expandEvents;
	}

	/**
	 * Set whether the console bell is enabled.
	 *
	 * @param enabled true if enabled; false otherwise
	 * @since 2.7
	 */
	public function setBellEnabled($enabled) {
		$this->bellEnabled = $enabled;
	}

	/**
	 * Get whether the console bell is enabled
	 *
	 * @return true if enabled; false otherwise
	 * @since 2.7
	 */
	public function getBellEnabled() {
		return $this->bellEnabled;
	}

	/**
	 * Sets the string that will be used to start a comment when the
	 * insert-comment key is struck.
	 * @param commentBegin The begin comment string.
	 * @since 2.7
	 */
	public function setCommentBegin($commentBegin) {
		$this->commentBegin = $commentBegin;
	}

	/**
	 * @return the string that will be used to start a comment when the
	 * insert-comment key is struck.
	 * @since 2.7
	 */
	public function getCommentBegin() {
		$str = $this->commentBegin;

		if ($str === null) {
			$str = $this->consoleKeys->getVariable("comment-begin");
			if ($str === null) {
				$str = "#";
			}
		}
		return $str;
	}

	public function setPrompt($prompt) {
		$this->prompt = $prompt;
		$this->promptLen = (($prompt === null) ? 0 : strlen($this->stripAnsi($this->lastLine($prompt))));
	}

	public function getPrompt() {
		return $this->prompt;
	}

	/**
	 * Set the echo character. For example, to have "*" entered when a password is typed:
	 * <p/>
	 * <pre>
	 * myConsoleReader.setEchoCharacter(new Character('*'));
	 * </pre>
	 * <p/>
	 * Setting the character to
	 * <p/>
	 * <pre>
	 * null
	 * </pre>
	 * <p/>
	 * will restore normal character echoing. Setting the character to
	 * <p/>
	 * <pre>
	 * new Character(0)
	 * </pre>
	 * <p/>
	 * will cause nothing to be echoed.
	 *
	 * @param c the character to echo to the console in place of the typed character.
	 */
	public function setEchoCharacter($c) {
		$this->echoCharacter = $c;
	}

	/**
	 * Returns the echo character.
	 */
	public function getEchoCharacter() {
		return $this->echoCharacter;
	}

	/**
	 * Erase the current line.
	 *
	 * @return false if we failed (e.g., the buffer was empty)
	 */
	protected final function resetLine() {
		if ($this->buf->cursor == 0) {
			return false;
		}

		$this->backspaceAll();

		return true;
	}

	public function getCursorPosition() {
		// FIXME: does not handle anything but a line with a prompt absolute position
		return $this->promptLen + $this->buf->cursor;
	}

	/**
	 * Returns the text after the last '\n'.
	 * prompt is returned if no '\n' characters are present.
	 * null is returned if prompt is null.
	 */
	private function lastLine($str) {
		if ($str === null) return "";
		$last = strrpos($str, "\n");

		if ($last !== false) {
			return substr($str, $last + 1);
		}

		return $str;
	}

	private function stripAnsi($str) {
		if ($str === null) return "";
		return \phpline\internal\AnsiUtil::stripAnsi($str);
		/*try {
			ByteArrayOutputStream baos = new ByteArrayOutputStream();
			AnsiOutputStream aos = new AnsiOutputStream(baos);
			aos.write(str.getBytes());
			aos.flush();
			return baos.toString();
		} catch (IOException e) {
			return str;
		}*/
	}

	/**
	 * Move the cursor position to the specified absolute index.
	 */
	public final function setCursorPosition($position) {
		if ($position == $this->buf->cursor) {
			return true;
		}

		return $this->moveCursor($position - $this->buf->cursor) != 0;
	}

	/**
	 * Set the current buffer's content to the specified {@link String}. The
	 * visual console will be modified to show the current buffer.
	 *
	 * @param buffer the new contents of the buffer.
	 */
	private function setBuffer($buffer) {
		if (is_array($buffer)) $buffer = implode('', $buffer);
		
		// don't bother modifying it if it is unchanged
		if ($buffer === $this->buf->buffer->__toString()) {
			return;
		}

		// obtain the difference between the current buffer and the new one
		$sameIndex = 0;

		for ($i = 0, $l1 = strlen($buffer), $l2 = $this->buf->buffer->length(); ($i < $l1)
			&& ($i < $l2); $i++) {
			if ($buffer{$i} == $this->buf->buffer->charAt($i)) {
				$sameIndex++;
			}
			else {
				break;
			}
		}

		$diff = $this->buf->cursor - $sameIndex;
		if ($diff < 0) { // we can't backspace here so try from the end of the buffer
			$this->moveToEnd();
			$diff = $this->buf->buffer->length() - $sameIndex;
		}

		$this->backspace($diff); // go back for the differences
		$this->killLine(); // clear to the end of the line
		$this->buf->buffer->setLength($sameIndex); // the new length
		$this->putString(substr($buffer, $sameIndex)); // append the differences
	}

	/**
	 * Output put the prompt + the current buffer
	 */
	public final function drawLine() {
		$prompt = $this->getPrompt();
		if ($prompt !== null) {
			$this->_print($prompt);
		}

		$this->_print($this->buf->buffer->__toString());

		if ($this->buf->length() !== $this->buf->cursor) { // not at end of line
			$this->back($this->buf->length() - $this->buf->cursor - 1);
		}
		// force drawBuffer to check for weird wrap (after clear screen)
		$this->drawBuffer();
	}

	/**
	 * Clear the line and redraw it.
	 */
	public final function redrawLine() {
		$this->_print(self::RESET_LINE);
//		flush();
		$this->drawLine();
	}

	/**
	 * Clear the buffer and add its contents to the history.
	 *
	 * @return the former contents of the buffer.
	 */
	public final function finishBuffer() { // FIXME: Package protected because used by tests
		$str = $this->buf->buffer->__toString();
		$historyLine = $str;

		if ($this->expandEvents) {
			$str = $this->expandEvents($str);
			$historyLine = str_replace("\\!", "\\\\!", $str);
		}

		// we only add it to the history if the buffer is not empty
		// and if mask is null, since having a mask typically means
		// the string was a password. We clear the mask after this call
		if (strlen($str) > 0) {
			if ($this->mask === null && $this->isHistoryEnabled()) {
				$this->history->add($historyLine);
			}
			else {
				$this->mask = null;
			}
		}

		$this->history->moveToEnd();

		$this->buf->buffer->setLength(0);
		$this->buf->cursor = 0;

		return $str;
	}

	/**
	 * Expand event designator such as !!, !#, !3, etc...
	 * See http://www.gnu.org/software/bash/manual/html_node/Event-Designators.html
	 */
	protected function expandEvents($str) {
		$sb = new StringBuilder();
		$escaped = false;
		for ($i = 0; $i < strlen($str); $i++) {
			$c = $str{$i};
			if ($escaped) {
				$sb->append($c);
				$escaped = false;
				continue;
			} else if ($c == '\\') {
				$escaped = true;
				continue;
			} else {
				$escaped = false;
			}
			switch ($c) {
				case '!':
					if ($i + 1 < strlen($str)) {
						$c = $str{++$i};
						$neg = false;
						$rep = null;
						
						switch ($c) {
							case '!':
								if (count($this->history) == 0) {
									throw new \InvalidArgumentException("!!: event not found");
								}
								$rep = $this->history->get($this->history->key() - 1)->__toString();
								break;
							case '#':
								$sb->append($sb->__toString());
								break;
							case '?':
								$i1 = strpos($str, '?', $i + 1);
								if ($i1 < 0) {
									$i1 = strlen($str);
								}
								$sc = substr($str, $i + 1, $i1 - $i - 1);
								$i = $i1;
								$idx = $this->searchBackwards($sc);
								if ($idx < 0) {
									throw new \InvalidArgumentException("!?" . $sc . ": event not found");
								} else {
									$rep = $this->history->get($idx)->__toString();
								}
								break;
							case ' ':
							case '\t':
								$sb->append('!');
								$sb->append($c);
								break;
							case '-':
								$neg = true;
								$i++;
								// fall through
							case '0':
							case '1':
							case '2':
							case '3':
							case '4':
							case '5':
							case '6':
							case '7':
							case '8':
							case '9':
								$i1 = $i;
								for (; $i < strlen($str); $i++) {
									$c = $str{$i};
									if (ord($c) < ord('0') || ord($c) > ord('9')) {
										break;
									}
								}
								$idx = 0;
								try {
									if (!is_numeric(substr($str, $i1, $i - $i1))) throw new \Exception("Not numeric");
									$idx = substr($str, $i1, $i - $i1);
								} catch (\Exception $e) {
									throw new \InvalidArgumentException(($neg ? "!-" : "!") . substr($str, $i1, $i - $i1) . ": event not found");
								}
								if ($neg) {
									if ($idx < count($this->history)) {
										$rep = $this->history->get($this->history->key() - $idx)->__toString();
									} else {
										throw new \InvalidArgumentException(($neg ? "!-" : "!") . substr($str, $i1, $i - $i1) . ": event not found");
									}
								} else {
									if ($idx >= $this->history->key() - count($this->history) && $idx < $this->history->key()) {
										$rep = $this->history->get($idx)->__toString();
									} else {
										throw new \InvalidArgumentException(($neg ? "!-" : "!") . substr($str, $i1, $i - $i1) . ": event not found");
									}
								}
								break;
							default:
								$ss = substr($str, $i);
								$i = strlen($str);
								$idx = $this->searchBackwards($ss, $this->history->key(), true);
								if ($idx < 0) {
									throw new \InvalidArgumentException("!" . ss . ": event not found");
								} else {
									$rep = $this->history->get($idx)->__toString();
								}
								break;
						}
						if ($rep != null) {
							$sb->append($rep);
						}
					} else {
						$sb->append($c);
					}
					break;
				case '^':
					if ($i == 0) {
						$i1 = strpos($str, '^', i + 1);
						$i2 = strpos($str, '^', i1 + 1);
						if ($i2 < 0) {
							$i2 = strlen($str);
						}
						if ($i1 > 0 && $i2 > 0) {
							$s1 = substr($str, i + 1, i1 - $i - 1);
							$s2 = substr($str, $i1 + 1, $i2 - $i1 - 1);
							$s = str_replace($s1, $s2, $this->history->get($this->history->key() - 1)->__toString());
							$sb->append($s);
							$i = $i2 + 1;
							break;
						}
					}
					$sb->append($c);
					break;
				default:
					$sb->append($c);
					break;
			}
		}
		if ($escaped) {
			$sb->append('\\');
		}
		$result = $sb->__toString();
		if ($str !== $result) {
			$this->_print($result);
			$this->println();
			$this->flush();
		}
		return $result;

	}

	/**
	 * Write out the specified string to the buffer and the output stream.
	 */
	public final function putString($str) {
		$this->buf->write($str);
		if ($this->mask == null) {
			// no masking
			$this->_print($str);
		} else if ($this->mask === self::NULL_MASK) {
			// don't print anything
		} else {
			$this->_print($this->mask, strlen($str));
		}
		$this->drawBuffer();
	}

	/**
	 * Redraw the rest of the buffer from the cursor onwards. This is necessary
	 * for inserting text into the buffer.
	 *
	 * @param clear the number of characters to clear after the end of the buffer
	 */
	private function drawBuffer($clear = 0) {
		// debug ("drawBuffer: " + clear);
		if ($this->buf->cursor === $this->buf->length() && $clear === 0) {
		} else {
			if (strlen($this->buf->buffer->substring($this->buf->cursor)) === 0) $chars = array();
			else $chars = str_split($this->buf->buffer->substring($this->buf->cursor));
			
			if ($this->mask !== null) {
				$chars = array_fill(0, count($chars), $this->mask);
			}
			if ($this->terminal->hasWeirdWrap()) {
				// need to determine if wrapping will occur:
				$width = $this->terminal->getWidth();
				$pos = $this->getCursorPosition();
				for ($i = 0; $i < count($chars); $i++) {
					$this->_print($chars[$i]);
					if ((($pos + $i + 1) % $width) == 0) {
						$this->_print(chr(32)); // move cursor to next line by printing dummy space
						$this->_print(chr(13)); // CR / not newline.
					}
				}
			} else {
				$this->_print($chars);
			}
			
			$this->clearAhead($clear, count($chars));
			if ($this->terminal->isAnsiSupported()) {
				if (count($chars) > 0) {
					$this->back(count($chars));
				}
			} else {
				$this->back(count($chars));
			}
		}
		if ($this->terminal->hasWeirdWrap()) {
			$width = $this->terminal->getWidth();
			// best guess on whether the cursor is in that weird location...
			// Need to do this without calling ansi cursor location methods
			// otherwise it breaks paste of wrapped lines in xterm.
			if ($this->getCursorPosition() > 0 && (($this->getCursorPosition() % $width) === 0)
					&& $this->buf->cursor == $this->buf->length() && $clear === 0) {
				// the following workaround is reverse-engineered from looking
				// at what bash sent to the terminal in the same situation
				$this->_print(chr(32)); // move cursor to next line by printing dummy space
				$this->_print(chr(13)); // CR / not newline.
			}
		}
	}

	/**
	 * Clear ahead the specified number of characters without moving the cursor.
	 *
	 * @param num the number of characters to clear
	 * @param delta the difference between the internal cursor and the screen
	 * cursor - if > 0, assume some stuff was printed and weird wrap has to be
	 * checked
	 */
	private function clearAhead($num, $delta) {
		if ($num == 0) {
			return;
		}

		if ($this->terminal->isAnsiSupported()) {
			$width = $this->terminal->getWidth();
			$screenCursorCol = $this->getCursorPosition() + $delta;
			// clear current line
			$this->printAnsiSequence("K");
			// if cursor+num wraps, then we need to clear the line(s) below too
			$curCol = $screenCursorCol % $width;
			$endCol = ($screenCursorCol + $num - 1) % $width;
			$lines = intval($num / $width);
			if ($endCol < $curCol) $lines++;
			for ($i = 0; $i < $lines; $i++) {
				$this->printAnsiSequence("B");
				$this->printAnsiSequence("2K");
			}
			for ($i = 0; $i < $lines; $i++) {
				$this->printAnsiSequence("A");
			}
			return;
		}

		// print blank extra characters
		$this->_print(' ', $num);

		// we need to flush here so a "clever" console doesn't just ignore the redundancy
		// of a space followed by a backspace.
//		flush();

		// reset the visual cursor
		$this->back($num);

//		flush();
	}

	/**
	 * Move the visual cursor backwards without modifying the buffer cursor.
	 */
	protected function back($num) {
		if ($num == 0) return;
		if ($this->terminal->isAnsiSupported()) {
			$width = $this->getTerminal()->getWidth();
			$cursor = $this->getCursorPosition();
			$realCursor = $cursor + $num;
			$realCol  = $realCursor % $width;
			$newCol = $cursor % $width;
			$moveup = intval($num / $width);
			$delta = $realCol - $newCol;
			if ($delta < 0) $moveup++;
			if ($moveup > 0) {
				$this->printAnsiSequence($moveup . "A");
			}
			
			$this->printAnsiSequence((1 + $newCol) . "G");
			return;
		}
		$this->print(self::BACKSPACE, $num);
//		flush();
	}

	/**
	 * Flush the console output stream. This is important for printout out single characters (like a backspace or
	 * keyboard) that we want the console to handle immediately.
	 */
	public function flush() {
		fflush($this->out);
	}

	private function backspaceAll() {
		return $this->backspace(PHP_INT_MAX);
	}

	/**
	 * Issue <em>num</em> backspaces.
	 *
	 * @return the number of characters backed up
	 */
	private function backspace($num = 1) {
		if ($this->buf->cursor == 0) {
			return 0;
		}

		$count = 0;

		$termwidth = $this->getTerminal()->getWidth();
		$lines = intval($this->getCursorPosition() / $termwidth);
		$count = $this->moveCursor(-1 * $num) * -1;
		$this->buf->buffer->delete($this->buf->cursor, $this->buf->cursor + $count);
		if (intval($this->getCursorPosition() / $termwidth) != $lines) {
			if ($this->terminal->isAnsiSupported()) {
				// debug("doing backspace redraw: " + getCursorPosition() + " on " + termwidth + ": " + lines);
				$this->printAnsiSequence("K");
				// if cursor+num wraps, then we need to clear the line(s) below too
				// last char printed is one pos less than cursor so we subtract
				// one
/*
				// TODO: fixme (does not work - test with reverse search with wrapping line and CTRL-E)
				int endCol = (getCursorPosition() + num - 1) % termwidth;
				int curCol = getCursorPosition() % termwidth;
				if (endCol < curCol) lines++;
				for (int i = 1; i < lines; i++) {
					printAnsiSequence("B");
					printAnsiSequence("2K");
				}
				for (int i = 1; i < lines; i++) {
					printAnsiSequence("A");
				}
				return count;
*/
			}
		}
		$this->drawBuffer($count);

		return $count;
	}

	protected function moveToEnd() {
		if ($this->buf->cursor == $this->buf->length()) {
			return true;
		}
		return $this->moveCursor($this->buf->length() - $this->buf->cursor) > 0;
	}

	/**
	 * Delete the character at the current position and redraw the remainder of the buffer.
	 */
	private function deleteCurrentCharacter() {
		if ($this->buf->length() == 0 || $this->buf->cursor == $this->buf->length()) {
			return false;
		}

		$this->buf->buffer->deleteCharAt($this->buf->cursor);
		$this->drawBuffer(1);
		return true;
	}

	/**
	 * This method is calling while doing a delete-to ("d"), change-to ("c"),
	 * or yank-to ("y") and it filters out only those movement operations
	 * that are allowable during those operations. Any operation that isn't
	 * allow drops you back into movement mode.
	 *
	 * @param op The incoming operation to remap
	 * @return The remaped operation
	 */
	private function viDeleteChangeYankToRemap ($op) {
		switch ($op) {
			case Operation::VI_EOF_MAYBE:
			case Operation::ABORT:
			case Operation::BACKWARD_CHAR:
			case Operation::FORWARD_CHAR:
			case Operation::END_OF_LINE:
			case Operation::VI_MATCH:
			case Operation::VI_BEGNNING_OF_LINE_OR_ARG_DIGIT:
			case Operation::VI_ARG_DIGIT:
			case Operation::VI_PREV_WORD:
			case Operation::VI_END_WORD:
			case Operation::VI_CHAR_SEARCH:
			case Operation::VI_NEXT_WORD:
			case Operation::VI_FIRST_PRINT:
			case Operation::VI_GOTO_MARK:
			case Operation::VI_COLUMN:
			case Operation::VI_DELETE_TO:
			case Operation::VI_YANK_TO:
			case Operation::VI_CHANGE_TO:
				return $op;

			default:
				return Operation::VI_MOVEMENT_MODE;
		}
	}

	/**
	 * Deletes the previous character from the cursor position
	 * @param count number of times to do it.
	 * @return true if it was done.
	 * @throws IOException
	 */
	private function viRubout($count) {
		$ok = true;
		for ($i = 0; $ok && $i < $count; $i++) {
			$ok = $this->backspace() == 1;
		}
		return $ok;
	}

	/**
	 * Deletes the character you are sitting on and sucks the rest of
	 * the line in from the right.
	 * @param count Number of times to perform the operation.
	 * @return true if its works, false if it didn't
	 * @throws IOException
	 */
	private function viDelete($count) {
		$ok = true;
		for ($i = 0; $ok && $i < $count; $i++) {
			$ok = $this->deleteCurrentCharacter();
		}
		return $ok;
	}

	/**
	 * Switches the case of the current character from upper to lower
	 * or lower to upper as necessary and advances the cursor one
	 * position to the right.
	 * @param count The number of times to repeat
	 * @return true if it completed successfully, false if not all
	 *   case changes could be completed.
	 * @throws IOException
	 */
	private function viChangeCase($count) {
		$ok = true;
		for ($i = 0; $ok && $i < $count; $i++) {

			$ok = $this->buf->cursor < $this->buf->buffer->length ();
			if (ok) {
				$ch = $this->buf->buffer->charAt($this->buf->cursor);
				if (strtoupper($ch) == $ch) {
					$ch = strtolower($ch);
				}
				else if (strtolower($ch) == $ch) {
					$ch = strtoupper($ch);
				}
				$this->buf->buffer->setCharAt($this->buf->cursor, $ch);
				$this->drawBuffer(1);
				$this->moveCursor(1);
			}
		}
		return $ok;
	}

	/**
	 * Implements the vi change character command (in move-mode "r"
	 * followed by the character to change to).
	 * @param count Number of times to perform the action
	 * @param c The character to change to
	 * @return Whether or not there were problems encountered
	 * @throws IOException
	 */
	private function viChangeChar($count, $c) {
		// EOF, ESC, or CTRL-C aborts.
		if (ord($c) < 0 || $c === "\033" || $c === "\003") {
			return true;
		}

		$ok = true;
		for ($i = 0; $ok && $i < $count; $i++) {
			$ok = $this->buf->cursor < $this->buf->buffer->length ();
			if ($ok) {
				$this->buf->buffer->setCharAt($this->buf->cursor, $c);
				$this->drawBuffer(1);
				if ($i < ($count-1)) {
					$this->moveCursor(1);
				}
			}
		}
		return $ok;
	}

	/**
	 * This is a close facsimile of the actual vi previous word logic. In
	 * actual vi words are determined by boundaries of identity characterse.
	 * This logic is a bit more simple and simply looks at white space or
	 * digits or characters.  It should be revised at some point.
	 *
	 * @param count number of iterations
	 * @return true if the move was successful, false otherwise
	 * @throws IOException
	 */
	private function viPreviousWord($count) {
		$ok = true;
		if ($this->buf->cursor === 0) {
			return false;
		}

		$pos = $this->buf->cursor - 1;
		for ($i = 0; $pos > 0 && $i < $count; $i++) {
			// If we are on white space, then move back.
			while ($pos > 0 && $this->isWhitespace($this->buf->buffer->charAt($pos))) {
				--$pos;
			}

			while ($pos > 0 && !$this->isDelimiter($this->buf->buffer->charAt($pos-1))) {
				--$pos;
			}

			if ($pos > 0 && $i < ($count-1)) {
				--$pos;
			}
		}
		$this->setCursorPosition($pos);
		return $ok;
	}

	/**
	 * Performs the vi "delete-to" action, deleting characters between a given
	 * span of the input line.
	 * @param startPos The start position
	 * @param endPos The end position.
	 * @return true if it succeeded, false otherwise
	 * @throws IOException
	 */
	private function viDeleteTo($startPos, $endPos) {
		if ($startPos === $endPos) {
			return true;
		}

		if ($endPos < $startPos) {
			$tmp = $endPos;
			$endPos = $startPos;
			$startPos = $tmp;
		}

		$this->setCursorPosition($startPos);
		$this->buf->cursor = $startPos;
		$this->buf->buffer->delete($startPos, $endPos);
		$this->drawBuffer($endPos - $startPos);
		return true;
	}

	/**
	 * Implement the "vi" yank-to operation.  This operation allows you
	 * to yank the contents of the current line based upon a move operation,
	 * for exaple "yw" yanks the current word, "3yw" yanks 3 words, etc.
	 *
	 * @param startPos The starting position from which to yank
	 * @param endPos The ending position to which to yank
	 * @return true if the yank succeeded
	 * @throws IOException
	 */
	private function viYankTo($startPos, $endPos) {
		$cursorPos = $startPos;

		if (endPos < $startPos) {
			$tmp = $endPos;
			$endPos = $startPos;
			$startPos = $tmp;
		}

		if ($startPos == $endPos) {
			$this->yankBuffer = "";
			return true;
		}

		$this->yankBuffer = $this->buf->buffer->substring($startPos, $endPos);

		/*
		 * It was a movement command that moved the cursor to find the
		 * end position, so put the cursor back where it started.
		 */
		$this->setCursorPosition($cursorPos);
		return true;
	}

	/**
	 * Pasts the yank buffer to the right of the current cursor position
	 * and moves the cursor to the end of the pasted region.
	 *
	 * @param count Number of times to perform the operation.
	 * @return true if it worked, false otherwise
	 * @throws IOException
	 */
	private function viPut($count) {
		if (strlen($this->yankBuffer) == 0) {
			return true;
		}
		if ($this->buf->cursor < $this->buf->buffer->length ()) {
			$this->moveCursor(1);
		}
		for ($i = 0; $i < $count; $i++) {
			$this->putString($this->yankBuffer);
		}
		$this->moveCursor(-1);
		return true;
	}

	/**
	 * Searches forward of the current position for a character and moves
	 * the cursor onto it.
	 * @param count Number of times to repeat the process.
	 * @param ch The character to search for
	 * @return true if the char was found, false otherwise
	 * @throws IOException
	 */
	private function viCharSearch($count, $invokeChar, $ch) {
		if (ord($ch) < 0 || ord($invokeChar) < 0) {
			return false;
		}

		$searchChar = $ch;

		/*
		 * The character stuff turns out to be hairy. Here is how it works:
		 *   f - search forward for ch
		 *   F - search backward for ch
		 *   t - search forward for ch, but stop just before the match
		 *   T - search backward for ch, but stop just after the match
		 *   ; - After [fFtT;], repeat the last search, after ',' reverse it
		 *   , - After [fFtT;], reverse the last search, after ',' repeat it
		 */
		if ($invokeChar === ';' || $invokeChar === ',') {
			// No recent search done? Then bail
			if ($this->charSearchChar === 0) {
				return false;
			}

			// Reverse direction if switching between ',' and ';'
			if ($this->charSearchLastInvokeChar === ';' || $this->charSearchLastInvokeChar === ',') {
				if ($this->charSearchLastInvokeChar !== $this->invokeChar) {
					$this->charSearchFirstInvokeChar = $this->switchCase($this->charSearchFirstInvokeChar);
				}
			}
			else {
				if ($invokeChar == ',') {
					$this->charSearchFirstInvokeChar = $this->switchCase($this->charSearchFirstInvokeChar);
				}
			}

			$searchChar = $this->charSearchChar;
		}
		else {
			$this->charSearchChar			= $searchChar;
			$this->charSearchFirstInvokeChar = $invokeChar;
		}

		$this->charSearchLastInvokeChar = $invokeChar;

		$isForward = strtolower($this->charSearchFirstInvokeChar) === $this->charSearchFirstInvokeChar;
		$stopBefore = (strtolower($this->charSearchFirstInvokeChar) === 't');

		$ok = false;

		if ($isForward) {
			while ($count-- > 0) {
				$pos = $this->buf->cursor + 1;
				while ($pos < $this->buf->buffer->length()) {
					if ($this->buf->buffer->charAt($pos) == $searchChar) {
						$this->setCursorPosition($pos);
						$ok = true;
						break;
					}
					++$pos;
				}
			}

			if ($ok) {
				if ($stopBefore)
					$this->moveCursor(-1);

				/*
				 * When in yank-to, move-to, del-to state we actually want to
				 * go to the character after the one we landed on to make sure
				 * that the character we ended up on is included in the
				 * operation
				 */
				if ($this->isInViMoveOperationState()) {
					$this->moveCursor(1);
				}
			}
		}
		else {
			while ($count-- > 0) {
				$pos = $this->buf->cursor - 1;
				while ($pos >= 0) {
					if ($this->buf->buffer->charAt($pos) === $searchChar) {
						$this->setCursorPosition($pos);
						$ok = true;
						break;
					}
					--$pos;
				}
			}

			if ($ok && $stopBefore)
				$this->moveCursor(1);
		}

		return $ok;
	}

	private function switchCase($ch) {
		if (strtoupper($ch) == $ch) {
			return strtolower(ch);
		}
		return strtoupper(ch);
	}

	/**
	 * @return true if line reader is in the middle of doing a change-to
	 *   delete-to or yank-to.
	 */
	private final function isInViMoveOperationState() {
		return $this->state === State::VI_CHANGE_TO
			|| $this->state === State::VI_DELETE_TO
			|| $this->state === State::VI_YANK_TO;
	}

	/**
	 * This is a close facsimile of the actual vi next word logic.
	 * As with viPreviousWord() this probably needs to be improved
	 * at some point.
	 *
	 * @param count number of iterations
	 * @return true if the move was successful, false otherwise
	 * @throws IOException
	 */
	private function viNextWord($count) {
		$pos = $this->buf->cursor;
		$end = $this->buf->buffer->length();

		for ($i = 0; $pos < $end && $i < $count; $i++) {
			// Skip over letter/digits
			while ($pos < $end && !$this->isDelimiter($this->buf->buffer->charAt($pos))) {
				++$pos;
			}

			/*
			 * Don't you love special cases? During delete-to and yank-to
			 * operations the word movement is normal. However, during a
			 * change-to, the trailing spaces behind the last word are
			 * left in tact.
			 */
			if ($i < ($count-1) || !($this->state === State::VI_CHANGE_TO)) {
				while ($pos < $end && $this->isDelimiter($this->buf->buffer->charAt($pos))) {
					++$pos;
				}
			}
		}

		$this->setCursorPosition($pos);
		return true;
	}

	/**
	 * Implements a close facsimile of the vi end-of-word movement.
	 * If the character is on white space, it takes you to the end
	 * of the next word.  If it is on the last character of a word
	 * it takes you to the next of the next word.  Any other character
	 * of a word, takes you to the end of the current word.
	 *
	 * @param count Number of times to repeat the action
	 * @return true if it worked.
	 * @throws IOException
	 */
	private function viEndWord($count) {
		$pos = $this->buf->cursor;
		$end = $this->buf->buffer->length();

		for ($i = 0; $pos < $end && $i < $count; $i++) {
			if ($pos < ($end-1)
					&& !$this->isDelimiter($this->buf->buffer->charAt($pos))
					&& $this->isDelimiter($this->buf->buffer->charAt ($pos+1))) {
				++$pos;
			}

			// If we are on white space, then move back.
			while ($pos < $end && $this->isDelimiter($this->buf->buffer->charAt($pos))) {
				++$pos;
			}

			while ($pos < ($end-1) && !$this->isDelimiter($this->buf->buffer->charAt($pos+1))) {
				++$pos;
			}
		}
		$this->setCursorPosition($pos);
		return true;
	}

	private function previousWord() {
		while ($this->isDelimiter($this->buf->current()) && ($this->moveCursor(-1) !== 0)) {
			// nothing
		}

		while (!$this->isDelimiter($this->buf->current()) && ($this->moveCursor(-1) !== 0)) {
			// nothing
		}

		return true;
	}

	private function nextWord() {
		while ($this->isDelimiter($this->buf->nextChar()) && ($this->moveCursor(1) !== 0)) {
			// nothing
		}

		while (!$this->isDelimiter($this->buf->nextChar()) && ($this->moveCursor(1) !== 0)) {
			// nothing
		}

		return true;
	}

	/**
	 * Deletes to the beginning of the word that the cursor is sitting on.
	 * If the cursor is on white-space, it deletes that and to the beginning
	 * of the word before it.  If the user is not on a word or whitespace
	 * it deletes up to the end of the previous word.
	 *
	 * @param count Number of times to perform the operation
	 * @return true if it worked, false if you tried to delete too many words
	 * @throws IOException
	 */
	private function unixWordRubout($count) {
		for (; $count > 0; --$count) {
			if ($this->buf->cursor == 0)
				return false;

			while ($this->isWhitespace($this->buf->current()) && $this->backspace()) {
				// nothing
			}
			while (!$this->isWhitespace($this->buf->current()) && $this->backspace()) {
				// nothing
			}
		}

		return true;
	}

	private function insertComment($isViMode) {
		$comment = $this->getCommentBegin ();
		$this->setCursorPosition(0);
		$this->putString($comment);
		if ($this->isViMode) {
			$this->consoleKeys->setKeyMap(KeyMap::VI_INSERT);
		}
		return $this->accept();
	}

	/**
	 * Similar to putString() but allows the string to be repeated a specific
	 * number of times, allowing easy support of vi digit arguments to a given
	 * command. The string is placed as the current cursor position.
	 *
	 * @param count The count of times to insert the string.
	 * @param str The string to insert
	 * @return true if the operation is a success, false otherwise
	 * @throws IOException
	 */
	private function insert($count, $str) {
		for ($i = 0; $i < $count; $i++) {
			$this->buf->write($str);
			if ($this->mask === null) {
				// no masking
				$this->_print($str);
			} else if ($this->mask == self::NULL_MASK) {
				// don't print anything
			} else {
				$this->print($this->mask, strlen($str));
			}
		}
		$this->drawBuffer();
		return true;
	}

	/**
	 * Implements vi search ("/" or "?").
	 * @throws IOException
	 */
	private function viSearch($searchChar) {
		$isForward = ($searchChar == '/');

		/*
		 * This is a little gross, I'm sure there is a more appropriate way
		 * of saving and restoring state.
		 */
		$origBuffer = $this->buf->copy();

		// Clear the contents of the current line and
		$this->setCursorPosition (0);
		$this->killLine();

		// Our new "prompt" is the character that got us into search mode.
		$this->putString($searchChar);
		$this->flush();

		$isAborted = false;
		$isComplete = false;

		/*
		 * Readline doesn't seem to do any special character map handling
		 * here, so I think we are safe.
		 */
		$ch = -1;
		while (!$isAborted && !$isComplete && ($ch = $this->readCharacter()) != -1) {
			switch ($ch) {
				case "\033":  // ESC
					/*
					 * The ESC behavior doesn't appear to be readline behavior,
					 * but it is a little tweak of my own. I like it.
					 */
					$isAborted = true;
					break;
				case "\010":  // Backspace
				case "\177":  // Delete
					$this->backspace();
					/*
					 * Backspacing through the "prompt" aborts the search.
					 */
					if ($this->buf->cursor == 0) {
						$this->isAborted = true;
					}
					break;
				case '\012': // Enter
					$this->isComplete = true;
					break;
				default:
					$this->putString($ch);
			}

			$this->flush();
		}

		// If we aborted, then put ourself at the end of the original buffer.
		if ($ch == -1 || $this->isAborted) {
			$this->setCursorPosition(0);
			$this->killLine();
			$this->putString($origBuffer->buffer);
			$this->setCursorPosition($origBuffer->cursor);
			return -1;
		}

		/*
		 * The first character of the buffer was the search character itself
		 * so we discard it.
		 */
		$searchTerm = $this->buf->buffer->substring(1);
		$idx = -1;

		/*
		 * The semantics of the history thing is gross when you want to
		 * explicitly iterate over entries (without an iterator) as size()
		 * returns the actual number of entries in the list but get()
		 * doesn't work the way you think.
		 */
		$end   = $this->history->key();
		$start = ($end <= count($this->history)) ? 0 : $end - count($this->history);

		if ($isForward) {
			for ($i = $start; $i < $end; $i++) {
				if (strpos($this->history->get($i)->__toString(), $searchTerm) !== false) {
					$idx = $i;
					break;
				}
			}
		}
		else {
			for ($i = $end-1; $i >= $start; $i--) {
				if (strpos($this->history->get($i)->__toString(), $searchTerm) !== false) {
					$idx = $i;
					break;
				}
			}
		}

		/*
		 * No match? Then restore what we were working on, but make sure
		 * the cursor is at the beginning of the line.
		 */
		if ($idx == -1) {
			$this->setCursorPosition(0);
			$this->killLine();
			$this->putString($origBuffer->buffer);
			$this->setCursorPosition(0);
			return -1;
		}

		/*
		 * Show the match.
		 */
		$this->setCursorPosition(0);
		$this->killLine();
		$this->putString($this->history->get($idx));
		$this->setCursorPosition(0);
		$this->flush();

		/*
		 * While searching really only the "n" and "N" keys are interpreted
		 * as movement, any other key is treated as if you are editing the
		 * line with it, so we return it back up to the caller for interpretation.
		 */
		$isComplete = false;
		while (!$isComplete && ($ch = $this->readCharacter()) !== -1) {
			$forward = $isForward;
			switch ($ch) {
				case 'p': case 'P':
					$forward = !$isForward;
					// Fallthru
				case 'n': case 'N':
					$isMatch = false;
					if ($forward) {
						for ($i = $idx+1; !$isMatch && $i < $end; $i++) {
							if (strpos($this->history->get($i)->__toString(), $searchTerm) !== false) {
								$idx = $i;
								$isMatch = true;
							}
						}
					}
					else {
						for ($i = idx - 1; !$isMatch && $i >= $start; $i--) {
							if (strpos($this->history->get($i)->__toString(), $searchTerm) !== false) {
								$idx = $i;
								$isMatch = true;
							}
						}
					}
					if ($isMatch) {
						$this->setCursorPosition(0);
						$this->killLine();
						$this->putString($this->history->get($idx));
						$this->setCursorPosition(0);
					}
					break;
				default:
					$isComplete = true;
			}
			$this->flush();
		}

		/*
		 * Complete?
		 */
		return $ch;
	}

	public function setParenBlinkTimeout($timeout) {
		$this->parenBlinkTimeout = $timeout;
	}

	private function insertClose($s) {
		$this->putString($s);
		$closePosition = $this->buf->cursor;

		$this->moveCursor(-1);
		$this->viMatch();

		if ($this->in->isNonBlockingEnabled()) {
			$this->in->peek($this->parenBlinkTimeout);
		}

		$this->setCursorPosition($closePosition);
	}

	/**
	 * Implements vi style bracket matching ("%" command). The matching
	 * bracket for the current bracket type that you are sitting on is matched.
	 * The logic works like so:
	 * @return true if it worked, false if the cursor was not on a bracket
	 *   character or if there was no matching bracket.
	 * @throws IOException
	 */
	private function viMatch() {
		$pos		= $this->buf->cursor;

		if (pos == $this->buf->length()) {
			return false;
		}

		$type	   = $this->getBracketType($this->buf->buffer->charAt ($pos));
		$move	   = ($type < 0) ? -1 : 1;
		$count	  = 1;

		if ($type == 0)
			return false;

		while ($count > 0) {
			$pos += $move;

			// Fell off the start or end.
			if ($pos < 0 || $pos >= $this->buf->buffer->length ()) {
				return false;
			}

			$curType = $this->getBracketType($this->buf->buffer->charAt ($pos));
			if ($curType == $type) {
				++$count;
			}
			else if ($curType == -$type) {
				--$count;
			}
		}

		/*
		 * Slight adjustment for delete-to, yank-to, change-to to ensure
		 * that the matching paren is consumed
		 */
		if ($move > 0 && $this->isInViMoveOperationState())
			++$pos;

		$this->setCursorPosition($pos);
		return true;
	}

	/**
	 * Given a character determines what type of bracket it is (paren,
	 * square, curly, or none).
	 * @param ch The character to check
	 * @return 1 is square, 2 curly, 3 parent, or zero for none.  The value
	 *   will be negated if it is the closing form of the bracket.
	 */
	private function getBracketType ($ch) {
		switch ($ch) {
			case '[': return  1;
			case ']': return -1;
			case '{': return  2;
			case '}': return -2;
			case '(': return  3;
			case ')': return -3;
			default:
				return 0;
		}
	}

	private function deletePreviousWord() {
		while ($this->isDelimiter($this->buf->current()) && $this->backspace()) {
			// nothing
		}

		while (!$this->isDelimiter($this->buf->current()) && $this->backspace()) {
			// nothing
		}

		return true;
	}

	private function deleteNextWord() {
		while ($this->isDelimiter($this->buf->nextChar()) && $this->delete()) {

		}

		while (!$this->isDelimiter($this->buf->nextChar()) && $this->delete()) {
			// nothing
		}

		return true;
	}

	private function capitalizeWord() {
		$first = true;
		$i = 1;
		
		while ($this->buf->cursor + $i - 1 < $this->buf->length() && !$this->isDelimiter(($c = $this->buf->buffer->charAt($this->buf->cursor + $i - 1)))) {
			$this->buf->buffer->setCharAt($this->buf->cursor + $i - 1, $first ? strtoupper($c) : strtolower($c));
			$first = false;
			$i++;
		}
		$this->drawBuffer();
		$this->moveCursor($i - 1);
		return true;
	}

	private function upCaseWord() {
		$i = 1;
		while ($this->buf->cursor + $i - 1 < $this->buf->length() && !$this->isDelimiter(($c = $this->buf->buffer->charAt($this->buf->cursor + $i - 1)))) {
			$this->buf->buffer->setCharAt($this->buf->cursor + $i - 1, strtoupper($c));
			$i++;
		}
		$this->drawBuffer();
		$this->moveCursor($i - 1);
		return true;
	}

	private function downCaseWord() {
		$i = 1;
		while ($this->buf->cursor + $i - 1 < $this->buf->length() && !$this->isDelimiter(($c = $this->buf->buffer->charAt($this->buf->cursor + $i - 1)))) {
			$this->buf->buffer->setCharAt($this->buf->cursor + $i - 1, strtolower($c));
			$i++;
		}
		$this->drawBuffer();
		$this->moveCursor($i - 1);
		return true;
	}

	/**
	 * Performs character transpose. The character prior to the cursor and the
	 * character under the cursor are swapped and the cursor is advanced one
	 * character unless you are already at the end of the line.
	 *
	 * @param count The number of times to perform the transpose
	 * @return true if the operation succeeded, false otherwise (e.g. transpose
	 *   cannot happen at the beginning of the line).
	 * @throws IOException
	 */
	private function transposeChars($count) {
		for (; $count > 0; --$count) {
			if ($this->buf->cursor == 0 || $this->buf->cursor == $this->buf->buffer->length()) {
				return false;
			}

			$first  = $this->buf->cursor-1;
			$second = $this->buf->cursor;

			$tmp = $this->buf->buffer->charAt ($first);
			$this->buf->buffer->setCharAt($first, $this->buf->buffer->charAt($second));
			$this->buf->buffer->setCharAt($second, $tmp);

			// This could be done more efficiently by only re-drawing at the end.
			$this->moveInternal(-1);
			$this->drawBuffer();
			$this->moveInternal(2);
		}

		return true;
	}

	public function isKeyMap($name) {
		// Current keymap.
		$map = $this->consoleKeys->getKeys();
		$mapByName = $this->consoleKeys->getKeyMaps();

		if (!isset($mapByName[$name]))
			return false;

		/*
		 * This may not be safe to do, but there doesn't appear to be a
		 * clean way to find this information out.
		 */
		return $map == $mapByName[$name];
	}


	/**
	 * The equivalent of hitting &lt;RET&gt;.  The line is considered
	 * complete and is returned.
	 *
	 * @return The completed line of text.
	 * @throws IOException
	 */
	public function accept() {
		$this->moveToEnd();
		$this->println(); // output newline
		$this->flush();
		return $this->finishBuffer();
	}

	/**
	 * Move the cursor <i>where</i> characters.
	 *
	 * @param num   If less than 0, move abs(<i>where</i>) to the left, otherwise move <i>where</i> to the right.
	 * @return	  The number of spaces we moved
	 */
	public function moveCursor($num) {
		$where = $num;

		if (($this->buf->cursor == 0) && ($where <= 0)) {
			return 0;
		}

		if (($this->buf->cursor == $this->buf->buffer->length()) && ($where >= 0)) {
			return 0;
		}

		if (($this->buf->cursor + $where) < 0) {
			$where = -$this->buf->cursor;
		}
		else if (($this->buf->cursor + $where) > $this->buf->buffer->length()) {
			$where = $this->buf->buffer->length() - $this->buf->cursor;
		}

		$this->moveInternal($where);

		return $where;
	}

	/**
	 * Move the cursor <i>where</i> characters, without checking the current buffer.
	 *
	 * @param where the number of characters to move to the right or left.
	 */
	private function moveInternal($where) {
		// debug ("move cursor " + where + " ("
		// + $this->buf->cursor + " => " + ($this->buf->cursor + where) + ")");
		$this->buf->cursor += $where;

		if ($this->terminal->isAnsiSupported()) {
			if ($where < 0) {
				$this->back(abs($where));
			} else {
				$width = $this->getTerminal()->getWidth();
				$cursor = $this->getCursorPosition();
				$oldLine = intval(($cursor - $where) / $width);
				$newLine = intval($cursor / $width);
				if ($newLine > $oldLine) {
					$this->printAnsiSequence(($newLine - $oldLine) . "B");
				}
				$this->printAnsiSequence(1 +($cursor % $width) . "G");
			}
//			flush();
			return;
		}

		if ($where < 0) {
			$len = 0;
			for ($i = $this->buf->cursor; $i < $this->buf->cursor - $where; $i++) {
				if ($this->buf->buffer->charAt($i) === "\t") {
					$len += self::TAB_WIDTH;
				}
				else {
					$len++;
				}
			}

			$chars = array();
			$chars = array_fill(0, $len, self::BACKSPACE);
			fwrite($this->out, implode('', $chars));

			return;
		}
		else if ($this->buf->cursor === 0) {
			return;
		}
		else if ($this->mask != null) {
			$c = $this->mask;
		}
		else {
			$this->_print(str_split($this->buf->buffer->substring($this->buf->cursor - $where, $this->buf->cursor)));
			return;
		}

		// null character mask: don't output anything
		if ($this->mask === self::NULL_MASK) {
			return;
		}

		$this->_print($c, abs($where));
	}

	// FIXME: replace() is not used

	/*public final boolean replace(final $num, final String replacement) {
		$this->buf->buffer.replace($this->buf->cursor - num, $this->buf->cursor, replacement);
		try {
			moveCursor(-num);
			drawBuffer(Math.max(0, num - replacement.length()));
			moveCursor(replacement.length());
		}
		catch (IOException e) {
			e.printStackTrace();
			return false;
		}
		return true;
	}*/

	/**
	 * Read a character from the console.
	 *
	 * @return the character, or -1 if an EOF is received.
	 */
	public final function readCharacter(array $allowed = array()) {
		if (!empty($allowed)) {
			while (!in_array($c = $this->reader->read(), $allowed));
		}
		else {
			$c = $this->reader->read();
		}
		
		if ($c !== -1) {
			Log::trace("Keystroke: ", $c);
			// clear any echo characters
			if ($this->terminal->isSupported()) {
				$this->clearEcho($c);
			}
		}
		return $c;
	}

	/**
	 * Clear the echoed characters for the specified character code.
	 */
	private function clearEcho($c) {
		// if the terminal is not echoing, then ignore
		if (!$this->terminal->isEchoEnabled()) {
			return 0;
		}

		// otherwise, clear
		$num = $this->countEchoCharacters($c);
		$this->back($num);
		$this->drawBuffer($num);

		return $num;
	}

	private function countEchoCharacters($c) {
		// tabs as special: we need to determine the number of spaces
		// to cancel based on what out current cursor position is
		if ($c == 9) {
			$tabStop = 8; // will this ever be different?
			$position = $this->getCursorPosition();

			return $tabStop - ($position % $tabStop);
		}

		return $this->getPrintableCharacters($c)->length();
	}

	/**
	 * Return the number of characters that will be printed when the specified
	 * character is echoed to the screen
	 *
	 * Adapted from cat by Torbjorn Granlund, as repeated in stty by David MacKenzie.
	 */
	private function getPrintableCharacters($ch) {
		$sbuff = new StringBuilder();
		$ch = ord($ch);
		if ($ch >= 32) {
			if ($ch < 127) {
				$sbuff->append($ch);
			}
			else if ($ch == 127) {
				$sbuff->append('^');
				$sbuff->append('?');
			}
			else {
				$sbuff->append('M');
				$sbuff->append('-');

				if ($ch >= (128 + 32)) {
					if ($ch < (128 + 127)) {
						$sbuff->append(chr(ch - 128));
					}
					else {
						$sbuff->append('^');
						$sbuff->append('?');
					}
				}
				else {
					$sbuff->append('^');
					$sbuff->append(chr(ch - 128 + 64));
				}
			}
		}
		else {
			$sbuff->append('^');
			$sbuff->append(chr(ch + 64));
		}

		return $sbuff;
	}

	//
	// Key Bindings
	//

	const JLINE_COMPLETION_THRESHOLD = "jline.completion.threshold";

	//
	// Line Reading
	//

	/**
	 * Sets the current keymap by name. Supported keymaps are "emacs",
	 * "vi-insert", "vi-move".
	 * @param name The name of the keymap to switch to
	 * @return true if the keymap was set, or false if the keymap is
	 *	not recognized.
	 */
	public function setKeyMap($name) {
		return $this->consoleKeys->setKeyMap($name);
	}

	/**
	 * Returns the name of the current key mapping.
	 * @return the name of the key mapping. This will be the canonical name
	 *   of the current mode of the key map and may not reflect the name that
	 *   was used with {@link #setKeyMap(String)}.
	 */
	public function getKeyMap() {
		return $this->consoleKeys->getKeys()->getName();
	}

	/**
	 * Read a line from the <i>in</i> {@link InputStream}, and return the line
	 * (without any trailing newlines).
	 *
	 * @param prompt	The prompt to issue to the console, may be null.
	 * @return		  A line that is read from the terminal, or null if there was null input (e.g., <i>CTRL-D</i>
	 *				  was pressed).
	 */
	public function readLine($prompt = null, $mask = null) {
		// prompt may be null
		// mask may be null

		/*
		 * This is the accumulator for VI-mode repeat count. That is, while in
		 * move mode, if you type 30x it will delete 30 characters. This is
		 * where the "30" is accumulated until the command is struck.
		 */
		$repeatCount = 0;

		// FIXME: This blows, each call to readLine will reset the console's state which doesn't seem very nice.
		$this->mask = $mask;
		if ($prompt !== null) {
			$this->setPrompt($prompt);
		}
		else {
			$prompt = $this->getPrompt();
		}
		$_this = $this;
		$finally = new FinallyEmulator(function () use ($_this) {
			if (!$_this->getTerminal()->isSupported()) {
				$_this->afterReadLine();
			}
		});
		if (!$this->terminal->isSupported()) {
			$this->beforeReadLine($prompt, $mask);
		}

		if ($prompt !== null && strlen($prompt) > 0) {
			fwrite($this->out, $prompt);
			fflush($this->out);
		}

		// if the terminal is unsupported, just use plain-java reading
		if (!$this->terminal->isSupported()) {
			return $this->readLineSimple();
		}

		$originalPrompt = $this->prompt;

		$this->state = State::NORMAL;

		$success = true;

		$sb = new StringBuilder();
		$pushBackChar = array();
		while (true) {
			$c = empty($pushBackChar) ? $this->readCharacter() : array_pop($pushBackChar);
			if ($c == -1) {
				return null;
			}
			$sb->append($c);
			
			if ($this->recording) {
				$this->macro .= $c;
			}

			$o = $this->getKeys()->getBound( $sb->__toString());
			if ($o === Operation::DO_LOWERCASE_VERSION) {
				$sb->setLength( $sb->length() - 1);
				$sb->append( strtolower( $c ));
				$o = $this->getKeys()->getBound( $sb->__toString() );
			}

			/*
			 * A KeyMap indicates that the key that was struck has a
			 * number of keys that can follow it as indicated in the
			 * map. This is used primarily for Emacs style ESC-META-x
			 * lookups. Since more keys must follow, go back to waiting
			 * for the next key.
			 */
			if ( $o instanceof KeyMap ) {
				/*
				 * The ESC key (#27) is special in that it is ambiguous until
				 * you know what is coming next.  The ESC could be a literal
				 * escape, like the user entering vi-move mode, or it could
				 * be part of a terminal control sequence.  The following
				 * logic attempts to disambiguate things in the same
				 * fashion as regular vi or readline.
				 *
				 * When ESC is encountered and there is no other pending
				 * character in the pushback queue, then attempt to peek
				 * into the input stream (if the feature is enabled) for
				 * 150ms. If nothing else is coming, then assume it is
				 * not a terminal control sequence, but a raw escape.
				 */
				if (ord($c) === 27
						&& empty($pushBackChar)
						&& $this->in->isNonBlockingEnabled()
						&& $this->in->peek($this->escapeTimeout) == -2) {
					$o = $o->getAnotherKey();
					if ($o === null || $o instanceof KeyMap) {
						continue;
					}
					$sb->setLength(0);
				}
				else {
					continue;
				}
			}

			/*
			 * If we didn't find a binding for the key and there is
			 * more than one character accumulated then start checking
			 * the largest span of characters from the beginning to
			 * see if there is a binding for them.
			 *
			 * For example if our buffer has ESC,CTRL-M,C the getBound()
			 * called previously indicated that there is no binding for
			 * this sequence, so this then checks ESC,CTRL-M, and failing
			 * that, just ESC. Each keystroke that is pealed off the end
			 * during these tests is stuffed onto the pushback buffer so
			 * they won't be lost.
			 *
			 * If there is no binding found, then we go back to waiting for
			 * input.
			 */
			while ( $o === null && $sb->length() > 0 ) {
				$c = $sb->charAt( $sb->length() - 1 );
				$sb->setLength( $sb->length() - 1 );
				$o2 = $this->getKeys()->getBound( $sb->__toString() );
				if ( $o2 instanceof KeyMap ) {
					$o = $o2->getAnotherKey();
					if ( $o === null ) {
						continue;
					} else {
						$pushBackChar[] = $c;
					}
				}
			}

			if ( $o === null ) {
				continue;
			}
			Log::trace("Binding: ", $o);


			// Handle macros
			if (is_string($o)) {
				$macro = $o;
				for ($i = 0; $i < strlen($macro); $i++) {
					$pushBackChar[] = $macro{strlen($macro) - 1 - $i};
				}
				$sb->setLength( 0 );
				continue;
			}

			// Handle custom callbacks
			if (is_callable($o)) {
				$o();
				$sb->setLength( 0 );
				continue;
			}

			// Search mode.
			//
			// Note that we have to do this first, because if there is a command
			// not linked to a search command, we leave the search mode and fall
			// through to the normal state.
			if ($this->state === State::SEARCH) {
				$cursorDest = -1;
				switch ( ($o )) {
					case Operation::ABORT:
						$this->state = State::NORMAL;
						break;

					case Operation::REVERSE_SEARCH_HISTORY:
					case Operation::HISTORY_SEARCH_BACKWARD:
						if ($this->searchTerm->length() == 0) {
							$this->searchTerm->append($this->previousSearchTerm);
						}

						if ($this->searchIndex == -1) {
							$this->searchIndex = $this->searchBackwards($this->searchTerm->__toString());
						} else {
							$this->searchIndex = $this->searchBackwards($this->searchTerm->__toString(), $this->searchIndex);
						}
						break;

					case Operation::BACKWARD_DELETE_CHAR:
						if ($this->searchTerm->length() > 0) {
							$this->searchTerm->deleteCharAt($this->searchTerm->length() - 1);
							$this->searchIndex = $this->searchBackwards($this->searchTerm->__toString());
						}
						break;

					case Operation::SELF_INSERT:
						$this->searchTerm->append($c);
						$this->searchIndex = $this->searchBackwards($this->searchTerm->__toString());
						break;

					default:
						// Set buffer and cursor position to the found string.
						if ($this->searchIndex !== -1) {
							$this->history->seek($this->searchIndex);
							// set cursor position to the found string
							$cursorDest = strpos($this->history->current()->__toString(), $this->searchTerm->__toString());
						}
						$this->state = State::NORMAL;
						break;
				}

				// if we're still in search mode, print the search status
				if ($this->state === State::SEARCH) {
					if ($this->searchTerm->length() == 0) {
						$this->printSearchStatus("", "");
						$this->searchIndex = -1;
					} else {
						if ($this->searchIndex == -1) {
							$this->beep();
						} else {
							$this->printSearchStatus($this->searchTerm->__toString(), $this->history->get($this->searchIndex)->__toString());
						}
					}
				}
				// otherwise, restore the line
				else {
					$this->restoreLine($originalPrompt, $cursorDest);
				}
			}
			if ($this->state !== State::SEARCH) {
				/*
				 * If this is still false at the end of the switch, then
				 * we reset our repeatCount to 0.
				 */
				$isArgDigit = false;

				/*
				 * Every command that can be repeated a specified number
				 * of times, needs to know how many times to repeat, so
				 * we figure that out here.
				 */
				$count = ($repeatCount == 0) ? 1 : $repeatCount;

				/*
				 * Default success to true. You only need to explicitly
				 * set it if something goes wrong.
				 */
				$success = true;

				if ($o) {
					$op = $o;

					/*
					 * Current location of the cursor (prior to the operation).
					 * These are used by vi *-to operation (e.g. delete-to)
					 * so we know where we came from.
					 */
					$cursorStart = $this->buf->cursor;
					$origState   = $this->state;

					/*
					 * If we are on a "vi" movement based operation, then we
					 * need to restrict the sets of inputs pretty heavily.
					 */
					if ($this->state == State::VI_CHANGE_TO
						|| $this->state == State::VI_YANK_TO
						|| $this->state == State::VI_DELETE_TO) {

						$op = $this->viDeleteChangeYankToRemap($op);
					}

					switch ( $op ) {
						case Operation::COMPLETE: // tab
							$success = $this->complete();
							break;

						case Operation::POSSIBLE_COMPLETIONS:
							$this->printCompletionCandidates();
							break;

						case Operation::BEGINNING_OF_LINE:
							$success = $this->setCursorPosition(0);
							break;

						case Operation::KILL_LINE: // CTRL-K
							$success = $this->killLine();
							break;

						case Operation::KILL_WHOLE_LINE:
							$success = $this->setCursorPosition(0) && $this->killLine();
							break;

						case Operation::CLEAR_SCREEN: // CTRL-L
							$success = $this->clearScreen();
							break;

						case Operation::OVERWRITE_MODE:
							$this->buf->setOverTyping(!$this->buf->isOverTyping());
							break;

						case Operation::SELF_INSERT:
							$this->putString($sb->__toString());
							break;

						case Operation::ACCEPT_LINE:
							return $this->accept();

						/*
						 * VI_MOVE_ACCEPT_LINE is the result of an ENTER
						 * while in move mode. This is the same as a normal
						 * ACCEPT_LINE, except that we need to enter
						 * insert mode as well.
						 */
						case Operation::VI_MOVE_ACCEPT_LINE:
							$this->consoleKeys->setKeyMap(KeyMap::VI_INSERT);
							return $this->accept();

						case Operation::BACKWARD_WORD:
							$success = $this->previousWord();
							break;

						case Operation::FORWARD_WORD:
							$success = $this->nextWord();
							break;

						case Operation::PREVIOUS_HISTORY:
							$success = $this->_moveHistory(false);
							break;

						/*
						 * According to bash/readline move through history
						 * in "vi" mode will move the cursor to the
						 * start of the line. If there is no previous
						 * history, then the cursor doesn't move.
						 */
						case Operation::VI_PREVIOUS_HISTORY:
							$success = $this->moveHistory(false, $count)
								&& $this->setCursorPosition(0);
							break;

						case Operation::NEXT_HISTORY:
							$success = $this->_moveHistory(true);
							break;

						/*
						 * According to bash/readline move through history
						 * in "vi" mode will move the cursor to the
						 * start of the line. If there is no next history,
						 * then the cursor doesn't move.
						 */
						case Operation::VI_NEXT_HISTORY:
							$success = $this->moveHistory(true, $count)
								&& $this->setCursorPosition(0);
							break;

						case Operation::BACKWARD_DELETE_CHAR: // backspace
							$success = $this->backspace();
							break;

						case Operation::EXIT_OR_DELETE_CHAR:
							if ($this->buf->buffer->length() == 0) {
								return null;
							}
							$success = $this->deleteCurrentCharacter();
							break;

						case Operation::DELETE_CHAR: // delete
							$success = $this->deleteCurrentCharacter();
							break;

						case Operation::BACKWARD_CHAR:
							$success = $this->moveCursor(-($count)) != 0;
							break;

						case Operation::FORWARD_CHAR:
							$success = $this->moveCursor($count) != 0;
							break;

						case Operation::UNIX_LINE_DISCARD:
							$success = $this->resetLine();
							break;

						case Operation::UNIX_WORD_RUBOUT:
							$success = $this->unixWordRubout($count);
							break;

						case Operation::BACKWARD_KILL_WORD:
							$success = $this->deletePreviousWord();
							break;
						case Operation::KILL_WORD:
							$success = $this->deleteNextWord();
							break;
						case Operation::BEGINNING_OF_HISTORY:
							$success = $this->history->rewind();
							if ($success) {
								$this->setBuffer($this->history->current());
							}
							break;

						case Operation::END_OF_HISTORY:
							$success = $this->history->moveToLast();
							if ($success) {
								$this->setBuffer($this->history->current());
							}
							break;

						case Operation::REVERSE_SEARCH_HISTORY:
						case Operation::HISTORY_SEARCH_BACKWARD:
							if ($this->searchTerm != null) {
								$this->previousSearchTerm = $this->searchTerm->__toString();
							}
							$this->searchTerm = new StringBuilder($this->buf->buffer);
							$this->state = State::SEARCH;
							if ($this->searchTerm->length() > 0) {
								$this->searchIndex = $this->searchBackwards($this->searchTerm->__toString());
								if ($this->searchIndex == -1) {
									$this->beep();
								}
								$this->printSearchStatus($this->searchTerm->__toString(),
										$this->searchIndex > -1 ? $this->history->get($this->searchIndex)->__toString() : "");
							} else {
								$this->searchIndex = -1;
								$this->printSearchStatus("", "");
							}
							break;

						case Operation::CAPITALIZE_WORD:
							$success = $this->capitalizeWord();
							break;

						case Operation::UPCASE_WORD:
							$success = $this->upCaseWord();
							break;

						case Operation::DOWNCASE_WORD:
							$success = $this->downCaseWord();
							break;

						case Operation::END_OF_LINE:
							$success = $this->moveToEnd();
							break;

						case Operation::TAB_INSERT:
							$this->putString( "\t" );
							break;

						case Operation::RE_READ_INIT_FILE:
							$this->consoleKeys->loadKeys($this->appName, $this->inputrcUrl);
							break;

						case Operation::START_KBD_MACRO:
							$this->recording = true;
							break;

						case Operation::END_KBD_MACRO:
							$this->recording = false;
							$this->macro = substr($this->macro, strlen($macro) - $sb->length());
							break;

						case Operation::CALL_LAST_KBD_MACRO:
							for ($i = 0; $i < strlen($this->macro); $i++) {
								$pushBackChar[] = $this->macro{strlen($this->macro) - 1 - $i};
							}
							$this->sb->setLength( 0 );
							break;

						case Operation::VI_EDITING_MODE:
							$this->consoleKeys->setKeyMap(KeyMap::VI_INSERT);
							break;

						case Operation::VI_MOVEMENT_MODE:
							/*
							 * If we are re-entering move mode from an
							 * aborted yank-to, delete-to, change-to then
							 * don't move the cursor back. The cursor is
							 * only move on an expclit entry to movement
							 * mode.
							 */
							if ($this->state == State::NORMAL) {
								$this->moveCursor(-1);
							}
							$this->consoleKeys->setKeyMap(KeyMap::VI_MOVE);
							break;

						case Operation::VI_INSERTION_MODE:
							$this->consoleKeys->setKeyMap(KeyMap::VI_INSERT);
							break;

						case Operation::VI_APPEND_MODE:
							$this->moveCursor(1);
							$this->consoleKeys->setKeyMap(KeyMap::VI_INSERT);
							break;

						case Operation::VI_APPEND_EOL:
							$success = $this->moveToEnd();
							$this->consoleKeys->setKeyMap(KeyMap::VI_INSERT);
							break;

						/*
						 * Handler for CTRL-D. Attempts to follow readline
						 * behavior. If the line is empty, then it is an EOF
						 * otherwise it is as if the user hit enter.
						 */
						case Operation::VI_EOF_MAYBE:
							if ($this->buf->buffer->length() == 0) {
								return null;
							}
							return $this->accept();

						case Operation::TRANSPOSE_CHARS:
							$success = $this->transposeChars($count);
							break;

						case Operation::INSERT_COMMENT:
							return $this->insertComment (false);

						case Operation::INSERT_CLOSE_CURLY:
							$this->insertClose("}");
							break;

						case Operation::INSERT_CLOSE_PAREN:
							$this->insertClose(")");
							break;

						case Operation::INSERT_CLOSE_SQUARE:
							$this->insertClose("]");
							break;

						case Operation::VI_INSERT_COMMENT:
							return $this->insertComment (true);

						case Operation::VI_MATCH:
							$success = $this->viMatch ();
							break;

						case Operation::VI_SEARCH:
							$lastChar = $this->viSearch($sb->charAt (0));
							if ($lastChar !== -1) {
								$pushBackChar[] = $lastChar;
							}
							break;

						case Operation::VI_ARG_DIGIT:
							$repeatCount = ($repeatCount * 10) + ord($sb->charAt(0)) - ord('0');
							$isArgDigit = true;
							break;

						case Operation::VI_BEGNNING_OF_LINE_OR_ARG_DIGIT:
							if ($repeatCount > 0) {
								$repeatCount = ($repeatCount * 10) + ord($sb->charAt(0)) - ord('0');
								$isArgDigit = true;
							}
							else {
								$success = $this->setCursorPosition(0);
							}
							break;

						case Operation::VI_PREV_WORD:
							$success = $this->viPreviousWord($count);
							break;

						case Operation::VI_NEXT_WORD:
							$success = $this->viNextWord($count);
							break;

						case Operation::VI_END_WORD:
							$success = $this->viEndWord($count);
							break;

						case Operation::VI_INSERT_BEG:
							$success = $this->setCursorPosition(0);
							$this->consoleKeys->setKeyMap(KeyMap::VI_INSERT);
							break;

						case Operation::VI_RUBOUT:
							$success = $this->viRubout($count);
							break;

						case Operation::VI_DELETE:
							$success = $this->viDelete($count);
							break;

						case Operation::VI_DELETE_TO:
							/*
							 * This is a weird special case. In vi
							 * "dd" deletes the current line. So if we
							 * get a delete-to, followed by a delete-to,
							 * we delete the line.
							 */
							if ($this->state == State::VI_DELETE_TO) {
								$success = $this->setCursorPosition(0) && $this->killLine();
								$this->state = $origState = State::NORMAL;
							}
							else {
								$this->state = State::VI_DELETE_TO;
							}
							break;

						case Operation::VI_YANK_TO:
							// Similar to delete-to, a "yy" yanks the whole line.
							if ($this->state == State::VI_YANK_TO) {
								$this->yankBuffer = $this->buf->buffer->__toString();
								$his->state = $origState = State::NORMAL;
							}
							else {
								$this->state = State::VI_YANK_TO;
							}
							break;

						case Operation::VI_CHANGE_TO:
							if ($this->state == State::VI_CHANGE_TO) {
								$success = $this->setCursorPosition(0) && $this->killLine();
								$this->state = $origState = State::NORMAL;
								$this->consoleKeys->setKeyMap(KeyMap::VI_INSERT);
							}
							else {
								$this->state = State::VI_CHANGE_TO;
							}
							break;

						case Operation::VI_PUT:
							$success = $this->viPut($count);
							break;

						case Operation::VI_CHAR_SEARCH: {
							 // ';' and ',' don't need another character. They indicate repeat next or repeat prev.
							$searchChar = ($c !== ';' && $c !== ',')
								? (empty($pushBackChar)
									? $this->readCharacter()
									: array_pop($pushBackChar))
								: 0;

								$success = $this->viCharSearch($count, $c, $searchChar);
							}
							break;

						case Operation::VI_CHANGE_CASE:
							$success = $this->viChangeCase($count);
							break;

						case Operation::VI_CHANGE_CHAR:
							$success = $this->viChangeChar($count,
								empty($pushBackChar)
									? $this->readCharacter()
									: array_pop($pushBackChar));
							break;

						case Operation::EMACS_EDITING_MODE:
							$this->consoleKeys->setKeyMap(KeyMap::EMACS);
							break;

						default:
							break;
					}

					/*
					 * If we were in a yank-to, delete-to, move-to
					 * when this operation started, then fall back to
					 */
					if ($origState !== State::NORMAL) {
						if ($origState === State::VI_DELETE_TO) {
							$success = $this->viDeleteTo($cursorStart, $this->buf->cursor);
						}
						else if ($origState === State::VI_CHANGE_TO) {
							$success = $this->viDeleteTo($cursorStart, $this->buf->cursor);
							$this->consoleKeys->setKeyMap(KeyMap::VI_INSERT);
						}
						else if ($origState === State::VI_YANK_TO) {
							$success = $this->viYankTo($cursorStart, $this->buf->cursor);
						}
						$this->state = State::NORMAL;
					}

					/*
					 * Another subtly. The check for the NORMAL state is
					 * to ensure that we do not clear out the repeat
					 * count when in delete-to, yank-to, or move-to modes.
					 */
					if ($this->state === State::NORMAL && !$isArgDigit) {
						/*
						 * If the operation performed wasn't a vi argument
						 * digit, then clear out the current repeatCount;
						 */
						$repeatCount = 0;
					}
				}
			}
			if (!$success) {
				$this->beep();
			}
			$sb->setLength( 0 );
			$this->flush();
		}
		$finally();
	}

	/**
	 * Read a line for unsupported terminals.
	 */
	private function readLineSimple() {
		$buff = new StringBuilder();

		if ($this->skipLF) {
			$this->skipLF = false;

			$i = $this->readCharacter();

			if ($i === -1 || $i === "\r") {
				return $buff->__toString();
			} else if ($i === "\n") {
				// ignore
			} else {
				$buff->append($i);
			}
		}

		while (true) {
			$i = $this->readCharacter();

			if ($i === -1 && $buff->length() == 0) {
				return null;
			}

			if ($i === -1 || $i === "\n") {
				return $buff->__toString();
			} else if ($i === "\r") {
				$this->skipLF = true;
				return $buff->__toString();
			} else {
				$buff->append($i);
			}
		}
	}

	//
	// Completion
	//

	private $completers = array();

	private $completionHandler = null;

	/**
	 * Add the specified {@link jline.console.completer.Completer} to the list of handlers for tab-completion.
	 *
	 * @param completer the {@link jline.console.completer.Completer} to add
	 * @return true if it was successfully added
	 */
	public function addCompleter(Completer $completer) {
		return $this->completers[spl_object_hash($completer)] = $completer;
	}

	/**
	 * Remove the specified {@link jline.console.completer.Completer} from the list of handlers for tab-completion.
	 *
	 * @param completer	 The {@link Completer} to remove
	 * @return			  True if it was successfully removed
	 */
	public function removeCompleter(Completer $completer) {
		$hash = spl_object_hash($completer);
		unset($this->completers[$hash]);
		return true;
	}

	/**
	 * Returns an unmodifiable list of all the completers.
	 */
	public function getCompleters() {
		return $this->completers;
	}

	public function setCompletionHandler(CompletionHandler $handler) {
		$this->completionHandler = $handler;
	}

	public function getCompletionHandler() {
		return $this->completionHandler;
	}

	/**
	 * Use the completers to modify the buffer with the appropriate completions.
	 *
	 * @return true if successful
	 */
	protected function complete() {
		// debug ("tab for (" + buf + ")");
		if (empty($this->completers)) {
			return false;
		}

		$candidates = array();
		$bufstr = $this->buf->buffer->__toString();
		$cursor = $this->buf->cursor;

		$position = -1;

		foreach($this->completers as $comp) {
			if (($position = $comp->complete($bufstr, $cursor, $candidates)) != -1) {
				break;
			}
		}

		return count($candidates) != 0 && $this->getCompletionHandler()->complete($this, $candidates, $position);
	}

	protected function printCompletionCandidates() {
		// debug ("tab for (" + buf + ")");
		if (empty($this->completers)) {
			return;
		}

		$candidates = array();
		$bufstr = $this->buf->buffer->__toString();
		$cursor = $this->buf->cursor;

		foreach($this->completers as $comp) {
			if ($comp->complete($bufstr, $cursor, $candidates) != -1) {
				break;
			}
		}
		CandidateListCompletionHandler::printCandidates($this, $candidates);
		$this->drawLine();
	}

	/**
	 * The number of tab-completion candidates above which a warning will be
	 * prompted before showing all the candidates.
	 */
	private $autoprintThreshold = 100;

	/**
	 * @param threshold the number of candidates to print without issuing a warning.
	 */
	public function setAutoprintThreshold($threshold) {
		$this->autoprintThreshold = $threshold;
	}

	/**
	 * @return the number of candidates to print without issuing a warning.
	 */
	public function getAutoprintThreshold() {
		return $this->autoprintThreshold;
	}

	private $paginationEnabled = false;

	/**
	 * Whether to use pagination when the number of rows of candidates exceeds the height of the terminal.
	 */
	public function setPaginationEnabled($enabled) {
		$this->paginationEnabled = $enabled;
	}

	/**
	 * Whether to use pagination when the number of rows of candidates exceeds the height of the terminal.
	 */
	public function isPaginationEnabled() {
		return $this->paginationEnabled;
	}

	//
	// History
	//

	private $history;

	public function setHistory(History $history) {
		$this->history = $history;
	}

	public function getHistory() {
		return $this->history;
	}

	private $historyEnabled = true;

	/**
	 * Whether or not to add new commands to the history buffer.
	 */
	public function setHistoryEnabled($enabled) {
		$this->historyEnabled = $enabled;
	}

	/**
	 * Whether or not to add new commands to the history buffer.
	 */
	public function isHistoryEnabled() {
		return $this->historyEnabled;
	}

	/**
	 * Used in "vi" mode for argumented history move, to move a specific
	 * number of history entries forward or back.
	 *
	 * @param next If true, move forward
	 * @param count The number of entries to move
	 * @return true if the move was successful
	 * @throws IOException
	 */
	private function moveHistory($next, $count) {
		$ok = true;
		for ($i = 0; $i < $count && ($ok = $this->_moveHistory($next)); $i++) {
			/* empty */
		}
		return $ok;
	}

	/**
	 * Move up or down the history tree.
	 */
	private function _moveHistory($next) {
		if ($next && !$this->history->next()) {
			return false;
		}
		else if (!$next && !$this->history->previous()) {
			return false;
		}
		
		$this->setBuffer($this->history->current());

		return true;
	}

	//
	// Printing
	//

	const CR = PHP_EOL; 
	
	/**
	 * Output the specified character to the output stream without manipulating the current buffer.
	 */
	private function _print($c, $num = null) {
		if ($num !== null) {
			if ($c === "\t") $num *= self::TAB_WIDTH;
			for ($i = 0; $i < $num; $i++) {
				fwrite($this->out, $c);
			}
			return;
		}
		
		if (!is_array($c)) $c = str_split($c);
		foreach ($c as $x) {
			if ($c === "\t") {
				for ($i = 0; $i < self::TAB_WIDTH; $i++) {
					fwrite($this->out, " ");
				}
			}

			fwrite($this->out, $x);
		}
	}

	/**
	 * Output a platform-dependant newline.
	 */
	public function println($s = null) {
		if ($s !== null) $this->_print($s);
		$this->_print("\n");
//		flush();
	}

	//
	// Actions
	//

	// FIXME: delete(int) the return is always 1 and num is ignored

	/**
	 * Issue <em>num</em> deletes.
	 *
	 * @return the number of characters backed up
	 */
	private function delete($num = 1) {
		// TODO: Try to use jansi for this

		/* Commented out because of DWA-2949:
		if ($this->buf->cursor == 0) {
			return 0;
		}
		*/

		$this->buf->buffer->delete($this->buf->cursor, $this->buf->cursor + 1);
		$this->drawBuffer(1);

		return 1;
	}

	/**
	 * Kill the buffer ahead of the current cursor position.
	 *
	 * @return true if successful
	 */
	public function killLine() {
		$cp = $this->buf->cursor;
		$len = $this->buf->buffer->length();

		if ($cp >= $len) {
			return false;
		}

		$num = $this->buf->buffer->length() - $cp;
		$this->clearAhead($num, 0);

		for ($i = 0; $i < $num; $i++) {
			$this->buf->buffer->deleteCharAt($len - $i - 1);
		}

		return true;
	}

	/**
	 * Clear the screen by issuing the ANSI "clear screen" code.
	 */
	public function clearScreen() {
		if (!$this->terminal->isAnsiSupported()) {
			return false;
		}

		// send the ANSI code to clear the screen
		$this->printAnsiSequence("2J");

		// then send the ANSI code to go to position 1,1
		$this->printAnsiSequence("1;1H");

		$this->redrawLine();

		return true;
	}

	/**
	 * Issue an audible keyboard bell.
	 */
	public function beep() {
		if ($this->bellEnabled) {
			$this->_print(self::KEYBOARD_BELL);
			// need to flush so the console actually beeps
			$this->flush();
		}
	}

	/**
	 * Paste the contents of the clipboard into the console buffer
	 *
	 * @return true if clipboard contents pasted
	 */
	public function paste() {
		/*Clipboard clipboard;
		try { // May throw ugly exception on system without X
			clipboard = Toolkit.getDefaultToolkit().getSystemClipboard();
		}
		catch (Exception e) {
			return false;
		}

		if (clipboard == null) {
			return false;
		}

		Transferable transferable = clipboard.getContents(null);

		if (transferable == null) {
			return false;
		}

		try {
			Object content = transferable.getTransferData(DataFlavor.plainTextFlavor);

			// This fix was suggested in bug #1060649 at
			// http://sourceforge.net/tracker/index.php?func=detail&aid=1060649&group_id=64033&atid=506056
			// to get around the deprecated DataFlavor.plainTextFlavor, but it
			// raises a UnsupportedFlavorException on Mac OS X

			if (content == null) {
				try {
					content = new DataFlavor().getReaderForText(transferable);
				}
				catch (Exception e) {
					// ignore
				}
			}

			if (content == null) {
				return false;
			}

			String value;

			if (content instanceof Reader) {
				// TODO: we might want instead connect to the input stream
				// so we can interpret individual lines
				value = "";
				String line;

				BufferedReader read = new BufferedReader((Reader) content);
				while ((line = read.readLine()) != null) {
					if (value.length() > 0) {
						value += "\n";
					}

					value += line;
				}
			}
			else {
				value = content.toString();
			}

			if (value == null) {
				return true;
			}

			putString(value);

			return true;
		}
		catch (UnsupportedFlavorException e) {
			Log.error("Paste failed: ", e);

			return false;
		}*/
	}

	//
	// Triggered Actions
	//

	private $triggeredActions = array();

	/**
	 * Adding a triggered Action allows to give another curse of action if a character passed the pre-processing.
	 * <p/>
	 * Say you want to close the application if the user enter q.
	 * addTriggerAction('q', new ActionListener(){ System.exit(0); }); would do the trick.
	 */
	public function addTriggeredAction($c, $listener) {
		$this->triggeredActions[$c] = $listener;
	}

	//
	// Formatted Output
	//

	/**
	 * Output the specified {@link Collection} in proper columns.
	 */
	public function printColumns(array $items) {
		if ($items === null || empty($items)) {
			return;
		}

		$width = $this->getTerminal()->getWidth();
		$height = $this->getTerminal()->getHeight();

		$maxWidth = 0;
		foreach ($items as $item) {
			$maxWidth = max($maxWidth, strlen($item));
		}
		$maxWidth = $maxWidth + 3;
		Log::debug("Max width: ", $maxWidth);

		if ($this->isPaginationEnabled()) {
			$showLines = $height - 1; // page limit
		}
		else {
			$showLines = PHP_INT_MAX;
		}

		$buff = new StringBuilder();
		foreach ($items as $item) {
			if (($buff->length() + $maxWidth) > $width) {
				$this->println($buff);
				$buff->setLength(0);

				if (--$showLines == 0) {
					// Overflow
					$this->print("--More--"); // TODO: language-item
					$this->flush();
					$c = $this->readCharacter();
					if (c === "\r" || $c === "\n") {
						// one step forward
						$showLines = 1;
					}
					else if ($c !== 'q') {
						// page forward
						$showLines = $height - 1;
					}

					$this->back(strlen("--More--")); // TODO: language-item
					if ($c === 'q') {
						// cancel
						break;
					}
				}
			}

			// NOTE: toString() is important here due to AnsiString being retarded
			$buff->append($item);
			for ($i = 0; $i < ($maxWidth - strlen($item)); $i++) {
				$buff->append(' ');
			}
		}

		if ($buff->length() > 0) {
			$this->println($buff);
		}
	}

	//
	// Non-supported Terminal Support
	//

	//private Thread maskThread;

	private function beforeReadLine($prompt, $mask) {
		/*if (mask != null && maskThread == null) {
			final String fullPrompt = "\r" + prompt
				+ "				 "
				+ "				 "
				+ "				 "
				+ "\r" + prompt;

			maskThread = new Thread()
			{
				public void run() {
					while (!interrupted()) {
						try {
							Writer out = getOutput();
							out.write(fullPrompt);
							out.flush();
							sleep(3);
						}
						catch (IOException e) {
							return;
						}
						catch (InterruptedException e) {
							return;
						}
					}
				}
			};

			maskThread.setPriority(Thread.MAX_PRIORITY);
			maskThread.setDaemon(true);
			maskThread.start();
		}*/
	}

	public function afterReadLine() {
		/*if (maskThread != null && maskThread.isAlive()) {
			maskThread.interrupt();
		}

		maskThread = null;*/
	}

	/**
	 * Erases the current line with the existing prompt, then redraws the line
	 * with the provided prompt and buffer
	 * @param prompt
	 *			the new prompt
	 * @param buffer
	 *			the buffer to be drawn
	 * @param cursorDest
	 *			where you want the cursor set when the line has been drawn.
	 *			-1 for end of line.
	 * */
	public function resetPromptLine($prompt, $buffer, $cursorDest) {
		// move cursor to end of line
		$this->moveToEnd();

		// backspace all text, including prompt
		$this->buf->buffer->append($this->prompt);
		$this->buf->cursor += strlen($this->prompt);
		$this->setPrompt("");
		$this->backspaceAll();

		$this->setPrompt($prompt);
		$this->redrawLine();
		$this->setBuffer($buffer);

		// move cursor to destination (-1 will move to end of line)
		if ($cursorDest < 0) $cursorDest = strlen($buffer);
		$this->setCursorPosition($cursorDest);

		$this->flush();
	}

	public function printSearchStatus($searchTerm, $match) {
		$prompt = "(reverse-i-search)`" . $searchTerm . "': ";
		$buffer = $match;
		$cursorDest = strpos($match, $searchTerm);
		$this->resetPromptLine($prompt, $buffer, $cursorDest);
	}

	public function restoreLine($originalPrompt, $cursorDest) {
		// TODO move cursor to matched string
		$prompt = $this->lastLine($originalPrompt);
		$buffer = $this->buf->buffer->__toString();
		$this->resetPromptLine($prompt, $buffer, $cursorDest);
	}

	//
	// History search
	//
	/**
	 * Search backward in history from a given position.
	 *
	 * @param searchTerm substring to search for.
	 * @param startIndex the index from which on to search
	 * @return index where this substring has been found, or -1 else.
	 */
	public function searchBackwards($searchTerm, $startIndex = null, $startsWith = false) {
		if ($startIndex === null) $startIndex = $this->history->key();
		// TODO:
		/*ListIterator<History.Entry> it = history.entries(startIndex);
		while (it.hasPrevious()) {
			History.Entry e = it.previous();
			if (startsWith) {
				if (e.value().toString().startsWith(searchTerm)) {
					return e.index();
				}
			} else {
				if (e.value().toString().contains(searchTerm)) {
					return e.index();
				}
			}
		}*/
		return -1;
	}

	//
	// Helpers
	//

	/**
	 * Checks to see if the specified character is a delimiter. We consider a
	 * character a delimiter if it is anything but a letter or digit.
	 *
	 * @param c	 The character to test
	 * @return	  True if it is a delimiter
	 */
	private function isDelimiter($c) {
		return preg_match('/[^a-zA-Z0-9]/', $c);
	}

	/**
	 * Checks to see if a character is a whitespace character. Currently
	 * this delegates to {@link Character#isWhitespace(char)}, however
	 * eventually it should be hooked up so that the definition of whitespace
	 * can be configured, as readline does.
	 *
	 * @param c The character to check
	 * @return true if the character is a whitespace
	 */
	private function isWhitespace($c) {
		return preg_match('/\s/', $c);
	}

	private function printAnsiSequence($sequence) {
		$this->_print(chr(27));
		$this->_print('[');
		$this->_print($sequence);
		$this->flush(); // helps with step debugging
	}

}