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
use phpline\javaApi\CharSequence;

/**
 * The KeyMap class contains all bindings from keys to operations.
 *
 * @author <a href="mailto:gnodet@gmail.com">Guillaume Nodet</a>
 * @author <a href="mailto:timwolla@bastelstu.be">Tim Düsterhus</a> 
 * @since 2.6
 */
class KeyMap {
		
	const VI_MOVE		= "vi-move";
	const VI_INSERT	  = "vi-insert";
	const EMACS		  = "emacs";
	const EMACS_STANDARD = "emacs-standard";
	const EMACS_CTLX	 = "emacs-ctlx";
	const EMACS_META	 = "emacs-meta";

	const KEYMAP_LENGTH = 256;

	private $mapping = array();
	private $anotherKey = null;
	private $name = "";
	private $isViKeyMap = false;
	
	public function __construct($name, $mapping, $isViKeyMap = null) {
		if ($isViKeyMap === null) {
			$isViKeyMap = $mapping;
			$mapping = array();
			for ($i = 0; $i < self::KEYMAP_LENGTH; $i++) {
				$mapping[$i] = new \stdClass();
			}
		}
		$this->mapping = $mapping;
		$this->name = $name;
		$this->isViKeyMap = $isViKeyMap;
	}
	
	public function isViKeyMap() {
		return $this->isViKeyMap;
	}
	
	public function getName() {
		return $this->name;
	}

	public function getAnotherKey() {
		return $this->anotherKey;
	}

	public function from(KeyMap $other) {
		$this->mapping = $other->mapping;
		$this->anotherKey = $other->anotherKey;
	}

	public function getBound($keySeq) {
		if ($keySeq !== null && strlen($keySeq) > 0) {
			$map = $this;
			for ($i = 0; $i < strlen($keySeq); $i++) {
				$c = ord($keySeq{$i});
				
				if ($c > 255) {
					return Operation::SELF_INSERT;
				}
				
				if ($map->mapping[$c] instanceof KeyMap) {
					if ($i == strlen($keySeq) - 1) {
						return $map->mapping[$c];
					} else {
						$map = $map->mapping[$c];
					}
				} else {
					return $map->mapping[$c];
				}
			}
		}
		return null;
	}

	public function bindIfNotBound($keySeq, $function) {
		
		self::_bind($this, $keySeq, $function, true);
	}
	
	public function bind($keySeq, $function) {
		self::_bind($this, $keySeq, $function);
	}
	
	private static function _bind(KeyMap $map, $keySeq, $function, 
			$onlyIfNotBound = false) {
		
		if ($keySeq !== null && strlen($keySeq) > 0) {
			for ($i = 0; $i < strlen($keySeq); $i++) {
				$c = ord($keySeq{$i});
				if ($c >= count($map->mapping)) {
					return;
				}
				if ($i < strlen($keySeq) - 1) {
					if (!($map->mapping[$c] instanceof KeyMap)) {
						$m = new KeyMap("anonymous", false);
						if ($map->mapping[$c] !== Operation::DO_LOWERCASE_VERSION) {
							$m->anotherKey = $map->mapping[$c];
						}
						$map->mapping[$c] = $m;
					}
					$map = $map->mapping[$c];
				} else {
					if ($function === null) {
						$function = new \stdClass();
					}
					if ($map->mapping[$c] instanceof KeyMap) {
						$map->anotherKey = $function;
					} else {
						$op = $map->mapping[$c];
						if ($onlyIfNotBound === false 
							|| $op === null 
							|| $op === Operation::DO_LOWERCASE_VERSION 
							|| $op === Operation::VI_MOVEMENT_MODE ) {
							
						}
						
						$map->mapping[$c] = $function;
					}
				}
			}
		}
	}

	public function setBlinkMatchingParen($on) {
		if ($on) {
			self::_bind($this, "}", Operation::INSERT_CLOSE_CURLY );
			self::_bind($this, ")", Operation::INSERT_CLOSE_PAREN );
			self::_bind($this, "]", Operation::INSERT_CLOSE_SQUARE );
		}
	}

	private static function bindArrowKeys(KeyMap $map) {
		
		// MS-DOS
		self::_bind( $map, "\033[0A", Operation::PREVIOUS_HISTORY );
		self::_bind( $map, "\033[0B", Operation::BACKWARD_CHAR );
		self::_bind( $map, "\033[0C", Operation::FORWARD_CHAR );
		self::_bind( $map, "\033[0D", Operation::NEXT_HISTORY );

		// Windows
		self::_bind( $map, "\340\000", Operation::KILL_WHOLE_LINE );
		self::_bind( $map, "\340\107", Operation::BEGINNING_OF_LINE );
		self::_bind( $map, "\340\110", Operation::PREVIOUS_HISTORY );
		self::_bind( $map, "\340\111", Operation::BEGINNING_OF_HISTORY );
		self::_bind( $map, "\340\113", Operation::BACKWARD_CHAR );
		self::_bind( $map, "\340\115", Operation::FORWARD_CHAR );
		self::_bind( $map, "\340\117", Operation::END_OF_LINE );
		self::_bind( $map, "\340\120", Operation::NEXT_HISTORY );
		self::_bind( $map, "\340\121", Operation::END_OF_HISTORY );
		self::_bind( $map, "\340\122", Operation::OVERWRITE_MODE );
		self::_bind( $map, "\340\123", Operation::DELETE_CHAR );
		self::_bind( $map, "\000\110", Operation::PREVIOUS_HISTORY );
		self::_bind( $map, "\000\113", Operation::BACKWARD_CHAR );
		self::_bind( $map, "\000\115", Operation::FORWARD_CHAR );
		self::_bind( $map, "\000\120", Operation::NEXT_HISTORY );
		self::_bind( $map, "\000\123", Operation::DELETE_CHAR );

		self::_bind( $map, "\033[A", Operation::PREVIOUS_HISTORY );
		self::_bind( $map, "\033[B", Operation::NEXT_HISTORY );
		self::_bind( $map, "\033[C", Operation::FORWARD_CHAR );
		self::_bind( $map, "\033[D", Operation::BACKWARD_CHAR );
		self::_bind( $map, "\033[H", Operation::BEGINNING_OF_LINE );
		self::_bind( $map, "\033[F", Operation::END_OF_LINE );

		self::_bind( $map, "\033OA", Operation::PREVIOUS_HISTORY );
		self::_bind( $map, "\033OB", Operation::NEXT_HISTORY );
		self::_bind( $map, "\033OC", Operation::FORWARD_CHAR );
		self::_bind( $map, "\033OD", Operation::BACKWARD_CHAR );
		self::_bind( $map, "\033OH", Operation::BEGINNING_OF_LINE );
		self::_bind( $map, "\033OF", Operation::END_OF_LINE );

		self::_bind( $map, "\033[3~", Operation::DELETE_CHAR);

		// MINGW32
		self::_bind( $map, "\0340H", Operation::PREVIOUS_HISTORY );
		self::_bind( $map, "\0340P", Operation::NEXT_HISTORY );
		self::_bind( $map, "\0340M", Operation::FORWARD_CHAR );
		self::_bind( $map, "\0340K", Operation::BACKWARD_CHAR );
	}

//	public boolean isConvertMetaCharsToAscii() {
//		return convertMetaCharsToAscii;
//	}

//	public void setConvertMetaCharsToAscii(boolean convertMetaCharsToAscii) {
//		this.convertMetaCharsToAscii = convertMetaCharsToAscii;
//	}

	public static function isMeta( $c ) {
		return ord($c) > 0x7f && ord($c) <= 0xff;
	}

	public static function unMeta( $c ) {
		return (ord($c) & 0x7F);
	}

	public static function meta( $c ) {
		return (ord($c) | 0x80);
	}
	
	public static function keyMaps() {
		$keyMaps = array();
		
		$emacs = self::emacs();
		self::bindArrowKeys($emacs);
		$keyMaps[self::EMACS] = $emacs;
		$keyMaps[self::EMACS_STANDARD] = $emacs;
		$keyMaps[self::EMACS_CTLX] = $emacs->getBound("\x18");
		$keyMaps[self::EMACS_META] = $emacs->getBound("\x1B");
		
		$viMov = self::viMovement();
		self::bindArrowKeys($viMov);
		$keyMaps[self::VI_MOVE] = $viMov;
		$keyMaps["vi-command"] = $viMov;
		
		$viIns = self::viInsertion();
		self::bindArrowKeys($viIns);
		$keyMaps[self::VI_INSERT] = $viIns;
		$keyMaps["vi"] = $viIns;
		
		return $keyMaps;
	}

	public static function emacs() {
		$map = array();
		for ($i = 0; $i < self::KEYMAP_LENGTH; $i++) {
			$map[$i] = new \stdClass();
		}
		$ctrl = array(
						// Control keys.
						Operation::SET_MARK,				 /* Control-@ */
						Operation::BEGINNING_OF_LINE,		/* Control-A */
						Operation::BACKWARD_CHAR,			/* Control-B */
						Operation::INTERRUPT,							   /* Control-C */
						Operation::EXIT_OR_DELETE_CHAR,	  /* Control-D */
						Operation::END_OF_LINE,			  /* Control-E */
						Operation::FORWARD_CHAR,			 /* Control-F */
						Operation::ABORT,					/* Control-G */
						Operation::BACKWARD_DELETE_CHAR,	 /* Control-H */
						Operation::COMPLETE,				 /* Control-I */
						Operation::ACCEPT_LINE,			  /* Control-J */
						Operation::KILL_LINE,				/* Control-K */
						Operation::CLEAR_SCREEN,			 /* Control-L */
						Operation::ACCEPT_LINE,			  /* Control-M */
						Operation::NEXT_HISTORY,			 /* Control-N */
						null,							   /* Control-O */
						Operation::PREVIOUS_HISTORY,		 /* Control-P */
						Operation::QUOTED_INSERT,			/* Control-Q */
						Operation::REVERSE_SEARCH_HISTORY,   /* Control-R */
						Operation::FORWARD_SEARCH_HISTORY,   /* Control-S */
						Operation::TRANSPOSE_CHARS,		  /* Control-T */
						Operation::UNIX_LINE_DISCARD,		/* Control-U */
						Operation::QUOTED_INSERT,			/* Control-V */
						Operation::UNIX_WORD_RUBOUT,		 /* Control-W */
						self::emacsCtrlX(),					   /* Control-X */
						Operation::YANK,					 /* Control-Y */
						null,							   /* Control-Z */
						self::emacsMeta(),						/* Control-[ */
						null,							   /* Control-\ */
						Operation::CHARACTER_SEARCH,		 /* Control-] */
						null,							   /* Control-^ */
						Operation::UNDO,					 /* Control-_ */
				);
		foreach ($ctrl as $key => $val) $map[$key] = $val;
		for ($i = 32; $i < 256; $i++) {
			$map[$i] = Operation::SELF_INSERT;
		}
		$map[self::DELETE] = Operation::BACKWARD_DELETE_CHAR;
		return new KeyMap(self::EMACS, $map, false);
	}

	const CTRL_D = 0x04;
	const CTRL_G = 0x07;
	const CTRL_H = 0x08;
	const CTRL_I = 0x09;
	const CTRL_J = 0x0A;
	const CTRL_M = 0x0D;
	const CTRL_R = 0x12;
	const CTRL_U = 0x15;
	const CTRL_X = 0x18;
	const CTRL_Y = 0x19;
	const ESCAPE = 0x1B; /* Ctrl-[ */
	const CTRL_OB = 0x1B; /* Ctrl-[ */
	const CTRL_CB = 0x1D; /* Ctrl-] */

	const DELETE = 0x7F;

	public static function emacsCtrlX() {
		$map = array();
		for ($i = 0; $i < self::KEYMAP_LENGTH; $i++) {
			$map[$i] = new \stdClass();
		}
		$map[self::CTRL_G] = Operation::ABORT;
		$map[self::CTRL_R] = Operation::RE_READ_INIT_FILE;
		$map[self::CTRL_U] = Operation::UNDO;
		$map[self::CTRL_X] = Operation::EXCHANGE_POINT_AND_MARK;
		$map[ord('(')] = Operation::START_KBD_MACRO;
		$map[ord(')')] = Operation::END_KBD_MACRO;
		for ($i = ord('A'); $i <= ord('Z'); $i++) {
			$map[$i] = Operation::DO_LOWERCASE_VERSION;
		}
		$map[ord('e')] = Operation::CALL_LAST_KBD_MACRO;
		$map[self::DELETE] = Operation::KILL_LINE;
		return new KeyMap(self::EMACS_CTLX, $map, false);
	}

	public static function emacsMeta() {
		$map = array();
		for ($i = 0; $i < self::KEYMAP_LENGTH; $i++) {
			$map[$i] = new \stdClass();
		}
		$map[self::CTRL_G] = Operation::ABORT;
		$map[self::CTRL_H] = Operation::BACKWARD_KILL_WORD;
		$map[self::CTRL_I] = Operation::TAB_INSERT;
		$map[self::CTRL_J] = Operation::VI_EDITING_MODE;
		$map[self::CTRL_M] = Operation::VI_EDITING_MODE;
		$map[self::CTRL_R] = Operation::REVERT_LINE;
		$map[self::CTRL_Y] = Operation::YANK_NTH_ARG;
		$map[self::CTRL_OB] = Operation::COMPLETE;
		$map[self::CTRL_CB] = Operation::CHARACTER_SEARCH_BACKWARD;
		$map[ord(' ')] = Operation::SET_MARK;
		$map[ord('#')] = Operation::INSERT_COMMENT;
		$map[ord('&')] = Operation::TILDE_EXPAND;
		$map[ord('*')] = Operation::INSERT_COMPLETIONS;
		$map[ord('-')] = Operation::DIGIT_ARGUMENT;
		$map[ord('.')] = Operation::YANK_LAST_ARG;
		$map[ord('<')] = Operation::BEGINNING_OF_HISTORY;
		$map[ord('=')] = Operation::POSSIBLE_COMPLETIONS;
		$map[ord('>')] = Operation::END_OF_HISTORY;
		$map[ord('?')] = Operation::POSSIBLE_COMPLETIONS;
		for ($i = ord('A'); $i <= ord('Z'); $i++) {
			$map[$i] = Operation::DO_LOWERCASE_VERSION;
		}
		$map[ord('\\')] = Operation::DELETE_HORIZONTAL_SPACE;
		$map[ord('_')] = Operation::YANK_LAST_ARG;
		$map[ord('b')] = Operation::BACKWARD_WORD;
		$map[ord('c')] = Operation::CAPITALIZE_WORD;
		$map[ord('d')] = Operation::KILL_WORD;
		$map[ord('f')] = Operation::FORWARD_WORD;
		$map[ord('l')] = Operation::DOWNCASE_WORD;
		$map[ord('p')] = Operation::NON_INCREMENTAL_REVERSE_SEARCH_HISTORY;
		$map[ord('r')] = Operation::REVERT_LINE;
		$map[ord('t')] = Operation::TRANSPOSE_WORDS;
		$map[ord('u')] = Operation::UPCASE_WORD;
		$map[ord('y')] = Operation::YANK_POP;
		$map[ord('~')] = Operation::TILDE_EXPAND;
		$map[self::DELETE] = Operation::BACKWARD_KILL_WORD;
		
		return new KeyMap(self::EMACS_META, $map, false);
	}

	public static function viInsertion() {
		$map = array();
		for ($i = 0; $i < self::KEYMAP_LENGTH; $i++) {
			$map[$i] = new \stdClass();
		}
		
		$ctrl = array(
						// Control keys.
						null,							   /* Control-@ */
						Operation::SELF_INSERT,			  /* Control-A */
						Operation::SELF_INSERT,			  /* Control-B */
						Operation::SELF_INSERT,			  /* Control-C */
						Operation::VI_EOF_MAYBE,			 /* Control-D */
						Operation::SELF_INSERT,			  /* Control-E */
						Operation::SELF_INSERT,			  /* Control-F */
						Operation::SELF_INSERT,			  /* Control-G */
						Operation::BACKWARD_DELETE_CHAR,	 /* Control-H */
						Operation::COMPLETE,				 /* Control-I */
						Operation::ACCEPT_LINE,			  /* Control-J */
						Operation::SELF_INSERT,			  /* Control-K */
						Operation::SELF_INSERT,			  /* Control-L */
						Operation::ACCEPT_LINE,			  /* Control-M */
						Operation::MENU_COMPLETE,			/* Control-N */
						Operation::SELF_INSERT,			  /* Control-O */
						Operation::MENU_COMPLETE_BACKWARD,   /* Control-P */
						Operation::SELF_INSERT,			  /* Control-Q */
						Operation::REVERSE_SEARCH_HISTORY,   /* Control-R */
						Operation::FORWARD_SEARCH_HISTORY,   /* Control-S */
						Operation::TRANSPOSE_CHARS,		  /* Control-T */
						Operation::UNIX_LINE_DISCARD,		/* Control-U */
						Operation::QUOTED_INSERT,			/* Control-V */
						Operation::UNIX_WORD_RUBOUT,		 /* Control-W */
						Operation::SELF_INSERT,			  /* Control-X */
						Operation::YANK,					 /* Control-Y */
						Operation::SELF_INSERT,			  /* Control-Z */
						Operation::VI_MOVEMENT_MODE,		 /* Control-[ */
						Operation::SELF_INSERT,			  /* Control-\ */
						Operation::SELF_INSERT,			  /* Control-] */
						Operation::SELF_INSERT,			  /* Control-^ */
						Operation::UNDO,					 /* Control-_ */
				);
		foreach ($ctrl as $key => $val) $map[$key] = $val;
		for ($i = 32; $i < 256; $i++) {
			$map[$i] = Operation::SELF_INSERT;
		}
		$map[self::DELETE] = Operation::BACKWARD_DELETE_CHAR;
		return new KeyMap(self::VI_INSERT, $map, false);
	}

	public static function viMovement() {
		$map = array();
		for ($i = 0; $i < self::KEYMAP_LENGTH; $i++) {
			$map[$i] = new \stdClass();
		}
		
		$low = array(
						// Control keys.
						null,							   /* Control-@ */
						null,							   /* Control-A */
						null,							   /* Control-B */
						Operation::INTERRUPT,							   /* Control-C */
						/* 
						 * ^D is supposed to move down half a screen. In bash
						 * appears to be ignored.
						 */
						Operation::VI_EOF_MAYBE,			 /* Control-D */
						Operation::EMACS_EDITING_MODE,	   /* Control-E */
						null,							   /* Control-F */
						Operation::ABORT,					/* Control-G */
						Operation::BACKWARD_CHAR,			/* Control-H */
						null,							   /* Control-I */
						Operation::VI_MOVE_ACCEPT_LINE,	  /* Control-J */
						Operation::KILL_LINE,				/* Control-K */
						Operation::CLEAR_SCREEN,			 /* Control-L */
						Operation::VI_MOVE_ACCEPT_LINE,	  /* Control-M */
						Operation::VI_NEXT_HISTORY,		  /* Control-N */
						null,							   /* Control-O */
						Operation::VI_PREVIOUS_HISTORY,	  /* Control-P */
						/*
						 * My testing with readline is the ^Q is ignored. 
						 * Maybe this should be null?
						 */
						Operation::QUOTED_INSERT,			/* Control-Q */
						
						/*
						 * TODO - Very broken.  While in forward/reverse 
						 * history search the VI keyset should go out the
						 * window and we need to enter a very simple keymap.
						 */
						Operation::REVERSE_SEARCH_HISTORY,   /* Control-R */
						/* TODO */
						Operation::FORWARD_SEARCH_HISTORY,   /* Control-S */
						Operation::TRANSPOSE_CHARS,		  /* Control-T */
						Operation::UNIX_LINE_DISCARD,		/* Control-U */
						/* TODO */
						Operation::QUOTED_INSERT,			/* Control-V */
						Operation::UNIX_WORD_RUBOUT,		 /* Control-W */
						null,							   /* Control-X */
						/* TODO */
						Operation::YANK,					 /* Control-Y */
						null,							   /* Control-Z */
						self::emacsMeta(),						/* Control-[ */
						null,							   /* Control-\ */
						/* TODO */
						Operation::CHARACTER_SEARCH,		 /* Control-] */
						null,							   /* Control-^ */
						/* TODO */
						Operation::UNDO,					 /* Control-_ */
						Operation::FORWARD_CHAR,			 /* SPACE */
						null,							   /* ! */
						null,							   /* " */
						Operation::VI_INSERT_COMMENT,		/* # */
						Operation::END_OF_LINE,			  /* $ */
						Operation::VI_MATCH,				 /* % */
						Operation::VI_TILDE_EXPAND,		  /* & */
						null,							   /* ' */
						null,							   /* ( */
						null,							   /* ) */
						/* TODO */
						Operation::VI_COMPLETE,			  /* * */
						Operation::VI_NEXT_HISTORY,		  /* + */
						Operation::VI_CHAR_SEARCH,		   /* , */
						Operation::VI_PREVIOUS_HISTORY,	  /* - */
						/* TODO */
						Operation::VI_REDO,				  /* . */
						Operation::VI_SEARCH,				/* / */
						Operation::VI_BEGNNING_OF_LINE_OR_ARG_DIGIT, /* 0 */
						Operation::VI_ARG_DIGIT,			 /* 1 */
						Operation::VI_ARG_DIGIT,			 /* 2 */
						Operation::VI_ARG_DIGIT,			 /* 3 */
						Operation::VI_ARG_DIGIT,			 /* 4 */
						Operation::VI_ARG_DIGIT,			 /* 5 */
						Operation::VI_ARG_DIGIT,			 /* 6 */
						Operation::VI_ARG_DIGIT,			 /* 7 */
						Operation::VI_ARG_DIGIT,			 /* 8 */
						Operation::VI_ARG_DIGIT,			 /* 9 */
						null,							   /* : */
						Operation::VI_CHAR_SEARCH,		   /* ; */
						null,							   /* < */
						Operation::VI_COMPLETE,			  /* = */
						null,							   /* > */
						Operation::VI_SEARCH,				/* ? */
						null,							   /* @ */
						Operation::VI_APPEND_EOL,			/* A */
						Operation::VI_PREV_WORD,			 /* B */
						Operation::VI_CHANGE_TO,			 /* C */
						Operation::VI_DELETE_TO,			 /* D */
						Operation::VI_END_WORD,			  /* E */
						Operation::VI_CHAR_SEARCH,		   /* F */
						/* I need to read up on what this does */
						Operation::VI_FETCH_HISTORY,		 /* G */
						null,							   /* H */
						Operation::VI_INSERT_BEG,			/* I */
						null,							   /* J */
						null,							   /* K */
						null,							   /* L */
						null,							   /* M */
						Operation::VI_SEARCH_AGAIN,		  /* N */
						null,							   /* O */
						Operation::VI_PUT,				   /* P */
						null,							   /* Q */
						/* TODO */
						Operation::VI_REPLACE,			   /* R */
						Operation::VI_SUBST,				 /* S */
						Operation::VI_CHAR_SEARCH,		   /* T */
						/* TODO */
						Operation::REVERT_LINE,			  /* U */
						null,							   /* V */
						Operation::VI_NEXT_WORD,			 /* W */
						Operation::VI_RUBOUT,				/* X */
						Operation::VI_YANK_TO,			   /* Y */
						null,							   /* Z */
						null,							   /* [ */
						Operation::VI_COMPLETE,			  /* \ */
						null,							   /* ] */
						Operation::VI_FIRST_PRINT,		   /* ^ */
						Operation::VI_YANK_ARG,			  /* _ */
						Operation::VI_GOTO_MARK,			 /* ` */
						Operation::VI_APPEND_MODE,		   /* a */
						Operation::VI_PREV_WORD,			 /* b */
						Operation::VI_CHANGE_TO,			 /* c */
						Operation::VI_DELETE_TO,			 /* d */
						Operation::VI_END_WORD,			  /* e */
						Operation::VI_CHAR_SEARCH,		   /* f */
						null,							   /* g */
						Operation::BACKWARD_CHAR,			/* h */
						Operation::VI_INSERTION_MODE,		/* i */
						Operation::NEXT_HISTORY,			 /* j */
						Operation::PREVIOUS_HISTORY,		 /* k */
						Operation::FORWARD_CHAR,			 /* l */
						Operation::VI_SET_MARK,			  /* m */
						Operation::VI_SEARCH_AGAIN,		  /* n */
						null,							   /* o */
						Operation::VI_PUT,				   /* p */
						null,							   /* q */
						Operation::VI_CHANGE_CHAR,		   /* r */
						Operation::VI_SUBST,				 /* s */
						Operation::VI_CHAR_SEARCH,		   /* t */
						Operation::UNDO,					 /* u */
						null,							   /* v */
						Operation::VI_NEXT_WORD,			 /* w */
						Operation::VI_DELETE,				/* x */
						Operation::VI_YANK_TO,			   /* y */
						null,							   /* z */
						null,							   /* { */
						Operation::VI_COLUMN,				/* | */
						null,							   /* } */
						Operation::VI_CHANGE_CASE,		   /* ~ */
						Operation::VI_DELETE				 /* DEL */
				);

		foreach ($low as $key => $val) $map[$key] = $val;
		for ($i = 128; $i < 256; $i++) {
			$map[$i] = null;
		}
		return new KeyMap(self::VI_MOVE, $map, false);
	}
}
