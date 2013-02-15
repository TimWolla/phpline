<?php
/*
 * Copyright (c) 2002-2012 = ; the original author or authors.
*
* This software is distributable under the BSD license. See the terms of the
* BSD license in the documentation provided with this software.
*
* http://www.opensource.org/licenses/bsd-license.php
*/
namespace phpline\console;

/**
 * List of all operations.
 *
 * @author <a href="mailto:gnodet@gmail.com">Guillaume Nodet</a>
 * @author <a href="mailto:timwolla@bastelstu.be">Tim Düsterhus</a> 
 * @since 2.6
 */
class Operation {

	const ABORT = 0;
	const ACCEPT_LINE = 1;
	const ARROW_KEY_PREFIX = 2;
	const BACKWARD_BYTE = 3;
	const BACKWARD_CHAR = 4;
	const BACKWARD_DELETE_CHAR = 5;
	const BACKWARD_KILL_LINE = 6;
	const BACKWARD_KILL_WORD = 7;
	const BACKWARD_WORD = 8;
	const BEGINNING_OF_HISTORY = 9;
	const BEGINNING_OF_LINE = 10;
	const CALL_LAST_KBD_MACRO = 11;
	const CAPITALIZE_WORD = 12;
	const CHARACTER_SEARCH = 13;
	const CHARACTER_SEARCH_BACKWARD = 14;
	const CLEAR_SCREEN = 15;
	const COMPLETE = 16;
	const COPY_BACKWARD_WORD = 17;
	const COPY_FORWARD_WORD = 18;
	const COPY_REGION_AS_KILL = 19;
	const DELETE_CHAR = 20;
	const DELETE_CHAR_OR_LIST = 21;
	const DELETE_HORIZONTAL_SPACE = 22;
	const DIGIT_ARGUMENT = 23;
	const DO_LOWERCASE_VERSION = 24;
	const DOWNCASE_WORD = 25;
	const DUMP_FUNCTIONS = 26;
	const DUMP_MACROS = 27;
	const DUMP_VARIABLES = 28;
	const EMACS_EDITING_MODE = 29;
	const END_KBD_MACRO = 30;
	const END_OF_HISTORY = 31;
	const END_OF_LINE = 32;
	const EXCHANGE_POINT_AND_MARK = 33;
	const EXIT_OR_DELETE_CHAR = 34;
	const FORWARD_BACKWARD_DELETE_CHAR = 35;
	const FORWARD_BYTE = 36;
	const FORWARD_CHAR = 37;
	const FORWARD_SEARCH_HISTORY = 38;
	const FORWARD_WORD = 39;
	const HISTORY_SEARCH_BACKWARD = 40;
	const HISTORY_SEARCH_FORWARD = 41;
	const INSERT_CLOSE_CURLY = 42;
	const INSERT_CLOSE_PAREN = 43;
	const INSERT_CLOSE_SQUARE = 44;
	const INSERT_COMMENT = 45;
	const INSERT_COMPLETIONS = 46;
	const INTERRUPT = 137;
	const KILL_WHOLE_LINE = 47;
	const KILL_LINE = 48;
	const KILL_REGION = 49;
	const KILL_WORD = 50;
	const MENU_COMPLETE = 51;
	const MENU_COMPLETE_BACKWARD = 52;
	const NEXT_HISTORY = 53;
	const NON_INCREMENTAL_FORWARD_SEARCH_HISTORY = 54;
	const NON_INCREMENTAL_REVERSE_SEARCH_HISTORY = 55;
	const NON_INCREMENTAL_FORWARD_SEARCH_HISTORY_AGAIN = 56;
	const NON_INCREMENTAL_REVERSE_SEARCH_HISTORY_AGAIN = 57;
	const OLD_MENU_COMPLETE = 58;
	const OVERWRITE_MODE = 59;
	const PASTE_FROM_CLIPBOARD = 60;
	const POSSIBLE_COMPLETIONS = 61;
	const PREVIOUS_HISTORY = 62;
	const QUOTED_INSERT = 63;
	const RE_READ_INIT_FILE = 64;
	const REDRAW_CURRENT_LINE = 65;
	const REVERSE_SEARCH_HISTORY = 66;
	const REVERT_LINE = 67;
	const SELF_INSERT = 68;
	const SET_MARK = 69;
	const SKIP_CSI_SEQUENCE = 70;
	const START_KBD_MACRO = 71;
	const TAB_INSERT = 72;
	const TILDE_EXPAND = 73;
	const TRANSPOSE_CHARS = 74;
	const TRANSPOSE_WORDS = 75;
	const TTY_STATUS = 76;
	const UNDO = 77;
	const UNIVERSAL_ARGUMENT = 78;
	const UNIX_FILENAME_RUBOUT = 79;
	const UNIX_LINE_DISCARD = 80;
	const UNIX_WORD_RUBOUT = 81;
	const UPCASE_WORD = 82;
	const YANK = 83;
	const YANK_LAST_ARG = 84;
	const YANK_NTH_ARG = 85;
	const YANK_POP = 86;
	const VI_APPEND_EOL = 87;
	const VI_APPEND_MODE = 88;
	const VI_ARG_DIGIT = 89;
	const VI_BACK_TO_INDENT = 90;
	const VI_BACKWARD_BIGWORD = 91;
	const VI_BACKWARD_WORD = 92;
	const VI_BWORD = 93;
	const VI_CHANGE_CASE = 94;
	const VI_CHANGE_CHAR = 95;
	const VI_CHANGE_TO = 96;
	const VI_CHAR_SEARCH = 97;
	const VI_COLUMN = 98;
	const VI_COMPLETE = 99;
	const VI_DELETE = 100;
	const VI_DELETE_TO = 101;
	const VI_EDITING_MODE = 102;
	const VI_END_BIGWORD = 103;
	const VI_END_WORD = 104;
	const VI_EOF_MAYBE = 105;
	const VI_EWORD = 106;
	const VI_FWORD = 107;
	const VI_FETCH_HISTORY = 108;
	const VI_FIRST_PRINT = 109;
	const VI_FORWARD_BIGWORD = 110;
	const VI_FORWARD_WORD = 111;
	const VI_GOTO_MARK = 112;
	const VI_INSERT_BEG = 113;
	const VI_INSERTION_MODE = 114;
	const VI_MATCH = 115;
	const VI_MOVEMENT_MODE = 116;
	const VI_NEXT_WORD = 117;
	const VI_OVERSTRIKE = 118;
	const VI_OVERSTRIKE_DELETE = 119;
	const VI_PREV_WORD = 120;
	const VI_PUT = 121;
	const VI_REDO = 122;
	const VI_REPLACE = 123;
	const VI_RUBOUT = 124;
	const VI_SEARCH = 125;
	const VI_SEARCH_AGAIN = 126;
	const VI_SET_MARK = 127;
	const VI_SUBST = 128;
	const VI_TILDE_EXPAND = 129;
	const VI_YANK_ARG = 130;
	const VI_YANK_TO = 131;
	const VI_MOVE_ACCEPT_LINE = 132;
	const VI_NEXT_HISTORY = 133;
	const VI_PREVIOUS_HISTORY = 134;
	const VI_INSERT_COMMENT = 135;
	const VI_BEGNNING_OF_LINE_OR_ARG_DIGIT = 136;
}
