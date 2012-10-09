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
use phpline\internal\FinallyEmulator;
use phpline\internal\Log;
use phpline\console\KeyMap;

/**
 * @author Ståle W. Pedersen <stale.pedersen@jboss.org>
 * @author <a href="mailto:timwolla@bastelstu.be">Tim Düsterhus</a> 
 */
class ConsoleKeys {
	
	private $keys = null;
	
	private $keyMaps = array();
	private $variables = array();
	
	public function __construct($appName, $inputrcUrl) {
		$this->keyMaps = KeyMap::keyMaps();
		$this->loadKeys($appName, $inputrcUrl);
	}
	
	protected function isViEditMode() {
		return $this->keys->isViKeyMap();
	}
	
	protected function setKeyMap ($name) {
		if (!isset($this->keyMaps[$name])) {
			return false;
		}
		$this->keys = $this->keyMaps[$name];
		return true;
	}
	
	protected function getKeyMaps() {
		return $this->keyMaps;
	}
	
	public function getKeys() {
		return $this->keys;
	}
	
	protected function setKeys(KeyMap $keys) {
		$this->keys = $keys;
	}
	
	protected function getViEditMode() {
		return $this->keys->isViKeyMap();
	}
	
	protected function loadKeys($appName, $inputrcUrl) {
		$this->keys = $this->keyMaps[KeyMap::EMACS];
		
		try {
			$input = fopen($inputrcUrl, 'rb');
			$finally = new FinallyEmulator(function () use ($input) {
				fclose($input);
			});
			$this->_loadKeys($input, $appName);
			Log::debug("Loaded user configuration: ", $inputrcUrl);
			$finally();
		}
		catch (\Exception $e) {
			Log::warn("Unable to read user configuration: ", $inputrcUrl, $e);
		}
	}

	private function _loadKeys($input, $appName) {
		$line = "";
		$parsing = true;
		$ifsStack = array();
		while ( ($line = fgets($input)) != null ) {
			try {
				$line = trim($line);
				if (strlen($line) === 0) {
					continue;
				}
				if ($line{0} === '#') {
					continue;
				}
				$i = 0;
				if ($line{$i} === '$') {
					$cmd = "";
					$args = "";
					for (++$i; $i < strlen($line) && ($line{$i} === ' ' || $line{$i} === "\t"); $i++);
					$s = $i;
					for (; $i < strlen($line) && ($line{$i} !== ' ' && $line{$i} !== "\t"); $i++);
					$cmd = substr($line, $s, $i-$s);
					for (; $i < strlen($line) && ($line{$i} === ' ' || $line{$i} === "\t"); $i++);
					$s = $i;
					for (; $i < strlen($line) && ($line{$i} !== ' ' && $line{$i} !== "\t"); $i++);
					$args = substr($line, $s, $i-$s);
					
					if ("if" === strtolower($cmd)) {
						array_push($ifsStack, $parsing);
						if (!$parsing) {
							continue;
						}
						if (substr($args, 0, 5) === "term=") {
							// TODO
						} else if (substr($args, 0, 5) === "mode=") {
							if (strtolower($args) ==="mode=vi") {
								$parsing = $this->isViEditMode();
							} else if ($args === "mode=emacs") {
								$parsing = !$this->isViEditMode();
							} else {
								$parsing = false;
							}
						} else {
							$parsing = strtolower($args) === strtolower($appName);
						}
					} else if ("else" === strtolower($cmd)) {
						if (empty($ifsStack)) {
							throw new \InvalidArgumentException("\$else found without matching \$if");
						}
						$invert = true;
						foreach ($ifsStack as $b) {
							if (!$b) {
								$invert = false;
								break;
							}
						}
						if ($invert) {
							$parsing = !$parsing;
						}
					} else if ("endif" === strtolower($cmd)) {
						if (empty($ifsStack)) {
							throw new \InvalidArgumentException("endif found without matching \$if");
						}
						$parsing = array_pop($ifsStack);
					} else if ("include" === strtolower($cmd)) {
						// TODO
					}
					continue;
				}
				if (!$parsing) {
					continue;
				}
				$equivalency = false;
				$keySeq = "";
				if ($line{$i++} === '"') {
					$esc = false;
					for (;; $i++) {
						if ($i >= strlen($line)) {
							throw new \InvalidArgumentException("Missing closing quote on line '".$line."'");
						}
						if ($esc) {
							$esc = false;
						} else if ($line{$i} === '\\') {
							$esc = true;
						} else if ($line{$i} === '"') {
							break;
						}
					}
				}
				for (; $i < strlen($line) && $line{$i} !== ':'
						&& $line{$i} !== ' ' && $line{$i} !== "\t"
						; $i++);
				$keySeq = substr($line, 0, $i);
				$equivalency = ($i + 1 < strlen($line) && $line{$i} === ':' && $line{$i + 1} === '=');
				$i++;
				if ($equivalency) {
					$i++;
				}
				if (strtolower($keySeq) === "set") {
					$key = "";
					$val = "";
					for (; $i < strlen($line) && ($line{$i} === ' ' || $line{$i} === "\t"); $i++);
					$s = $i;
					for (; $i < strlen($line) && ($line{$i} !== ' ' && $line{$i} !== "\t"); $i++);
					$key = substr($line, $s, $i-$s);
					for (; $i < strlen($line) && ($line{$i} === ' ' || $line{$i} === "\t"); $i++);
					$s = $i;
					for (; $i < strlen($line) && ($line{$i} !== ' ' && $line{$i} !== "\t"); $i++);
					$val = substr($line, $s, $i-$s);
					$this->setVar($key, $val);
				} else {
					for (; $i < strlen($line) && ($line{$i} === ' ' || $line{$i} === "\t"); $i++);
					$start = $i;
					if ($i < strlen($line) && ($line{$i} === '\'' || $line{$i} === '\"')) {
						$delim = $line{$i++};
						$esc = false;
						for (;; $i++) {
							if ($i >= strlen($line)) {
								break;
							}
							if (esc) {
								$esc = false;
							} else if ($line{$i} === '\\') {
								$esc = true;
							} else if ($line{$i} === $delim) {
								break;
							}
						}
					}
					for (; $i < strlen($line) && $line{$i} !== ' ' && $line{$i} !== "\t"; $i++);
					$val = substr($line, min($start, strlen($line)), min($i, strlen($line)) - min($start, strlen($line)));
					if ($keySeq{0} === '"') {
						$keySeq = $this->translateQuoted($keySeq);
					} else {
						// Bind key name
						$keyName = strrpos($keySeq, '-') > 0 ? substr($keySeq, strrpos($keySeq, '-') + 1 ) : $keySeq;
						$key = $this->getKeyFromName($keyName);
						$keyName = strtolower($keySeq);
						$keySeq = "";
						if (strpos($keyName, "meta-") !== false || strpos($keyName, "m-") !== false) {
							$keySeq .= "\x1B";
						}
						if (strpos($keyName, "control-") !== false || strpos($keyName, "c-") !== false || strpos($keyName, "ctrl-") !== false) {
							$key = chr(ord(strtoupper($key)) & 0x1f);
						}
						$keySeq .= $key;
					}
					if (strlen($val) > 0 && ($val{0} === '\'' || $val{0} === '\"')) {
						$this->keys->bind($keySeq, $this->translateQuoted($val));
					} else {
						$operationName = strtoupper(str_replace('-', '_', $val));
						try {
							if (!defined('\phpline\console\Operation::'.$operationName)) throw new \InvalidArgumentException("Constant not found");
							$this->keys->bind($keySeq, constant('\phpline\console\Operation::'.$operationName));
						} catch(\InvalidArgumentException $e) {
							Log::info("Unable to bind key for unsupported operation: ", $val);
						}
					}
				}
			} catch (\InvalidArgumentException $e) {
				Log::warn("Unable to parse user configuration: ", $e);
			}
		}
	}

	private function translateQuoted($keySeq) {
		$str = substr($keySeq, 1, strlen($keySeq) - 2 );
		$keySeq = "";
		for ($i = 0; $i < strlen($str); $i++) {
			$c = $str{$i};
			if ($c === '\\') {
				$ctrl = substr($str, $i, 3) === "\\C-" || substr($str, $i, 6) === "\\M-\\C-";
				$meta = substr($str, $i, 3) === "\\M-" || substr($str, $i, 6) === "\\C-\\M-";
				$i += ($meta ? 3 : 0) + ($ctrl ? 3 : 0) + (!$meta && !$ctrl ? 1 : 0);
				if ($i >= strlen($str)) {
					break;
				}
				$c = $str{$i};
				if ($meta) {
					$keySeq .= "\x1B";
				}
				if ($ctrl) {
					$c = $c == '?' ? chr(0x7f) : chr(ord(strtoupper($c)) & 0x1f);
				}
				if (!$meta && !$ctrl) {
					switch ($c) {
						case 'a': $c = chr(0x07); break;
						case 'b': $c = chr(8); break;
						case 'd': $c = chr(0x7f); break;
						case 'e': $c = chr(0x1b); break;
						case 'f': $c = "\f"; break;
						case 'n': $c = "\n"; break;
						case 'r': $c = "\r"; break;
						case 't': $c = "\t"; break;
						case 'v': $c = chr(0x0b); break;
						case '\\': $c = '\\'; break;
						case '0': case '1': case '2': case '3':
						case '4': case '5': case '6': case '7':
							$c = 0;
							for ($j = 0; $j < 3; $j++, $i++) {
								if ($i >= strlen($str)) {
									break;
								}
								$k = intval($str{$i}, 8);
								if ($k < 0) {
									break;
								}
								$c = chr($c * 8 + $k);
							}
							$c = chr(ord($c) & 0xFF);
							break;
						case 'x':
							$i++;
							$c = 0;
							for ($j = 0; $j < 2; $j++, $i++) {
								if ($i >= strlen($str)) {
									break;
								}
								$k = intval($str{$i}, 16);
								if ($k < 0) {
									break;
								}
								$c = chr($c * 16 + $k);
							}
							$c = chr(ord($c) & 0xFF);
							break;
						case 'u':
							$i++;
							$c = 0;
							for ($j = 0; $j < 4; $j++, $i++) {
								if ($i >= strlen($str)) {
									break;
								}
								$k = intval($str{$i}, 16);
								if ($k < 0) {
									break;
								}
								$c = chr($c * 16 + $k);
							}
							break;
					}
				}
				$keySeq .= $c;
			} else {
				$keySeq .= $c;
			}
		}
		return $keySeq;
	}

	private function getKeyFromName($name) {
		if ("del" === strtolower($name) || "rubout" === strtolower($name)) {
			return "\x7f";
		} else if ("esc" === strtolower($name) || "escape" === strtolower($name)) {
			return "\033";
		} else if ("lfd" === strtolower($name) || "newLine" === strtolower($name)) {
			return "\n";
		} else if ("ret" === strtolower($name) || "return" === strtolower($name)) {
			return "\r";
		} else if ("spc" === strtolower($name) || "space" === strtolower($name)) {
			return ' ';
		} else if ("tab" === strtolower($name)) {
			return "\t";
		} else {
			return $name{0};
		}
	}
	
	private function setVar($key, $val) {
		if ("keymap" === strtolower($key)) {
			if (isset($this->keyMaps[$val])) {
				$this->keys = $this->keyMaps[$val];
			}
		} else if ("editing-mode" === $key) {
			if ("vi" === strtolower($val)) {
				$this->keys = $this->keyMaps[KeyMap::VI_INSERT];
			} else if ("emacs" === strtolower($val)) {
				$this->keys = $this->keyMaps[KeyMap::EMACS];
			}
		} else if ("blink-matching-paren" === $key) {
			if ("on" === strtolower($val)) {
				$this->keys->setBlinkMatchingParen(true);
			} else if ("off" === strtolower($val)) {
				$this->keys->setBlinkMatchingParen(false);
			}
		}

		/*
		 * Technically variables should be defined as a functor class
		 * so that validation on the variable value can be done at parse
		 * time. This is a stop-gap.
		 */
		$this->variables[$key] = $val;
	}

	/**
	 * Retrieves the value of a variable that was set in the .inputrc file
	 * during processing
	 * @param var The variable name
	 * @return The variable value.
	 */
	public function getVariable($var) {
		if (!isset($this->variables[$var])) return null;
		return $this->variables[$var];
	}
}
