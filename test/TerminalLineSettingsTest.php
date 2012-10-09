<?php

require_once 'src/autoload.php';

require_once 'PHPUnit/Framework/TestCase.php';
use \phpline\internal\TerminalLineSettings;

/**
 * TerminalLineSettings test case.
 */
class TerminalLineSettingsTest extends PHPUnit_Framework_TestCase {
	
	/**
	 *
	 * @var TerminalLineSettings
	 */
	private $TerminalLineSettings;
	
	const linuxSttySample = "speed 38400 baud; rows 85; columns 244; line = 0;\n
intr = ^C; quit = ^\\; erase = ^?; kill = ^U; eof = ^D; eol = M-^?; eol2 = M-^?; swtch = M-^?; start = ^Q; stop = ^S; susp = ^Z; rprnt = ^R; werase = ^W; lnext = ^V; flush = ^O; min = 1; time = 0;\n
-parenb -parodd cs8 hupcl -cstopb cread -clocal -crtscts\n
-ignbrk brkint -ignpar -parmrk -inpck -istrip -inlcr -igncr icrnl ixon -ixoff -iuclc ixany imaxbel iutf8\n
opost -olcuc -ocrnl onlcr -onocr -onlret -ofill -ofdel nl0 cr0 tab0 bs0 vt0 ff0\n
isig icanon iexten echo echoe echok -echonl -noflsh -xcase -tostop -echoprt echoctl echoke";
	
	const solarisSttySample = "speed 38400 baud; \n
rows = 85; columns = 244; ypixels = 0; xpixels = 0;\n
csdata ?\n
eucw 1:0:0:0, scrw 1:0:0:0\n
intr = ^c; quit = ^\\; erase = ^?; kill = ^u;\n
eof = ^d; eol = -^?; eol2 = -^?; swtch = <undef>;\n
start = ^q; stop = ^s; susp = ^z; dsusp = ^y;\n
rprnt = ^r; flush = ^o; werase = ^w; lnext = ^v;\n
-parenb -parodd cs8 -cstopb -hupcl cread -clocal -loblk -crtscts -crtsxoff -parext \n
-ignbrk brkint -ignpar -parmrk -inpck -istrip -inlcr -igncr icrnl -iuclc \n
ixon ixany -ixoff imaxbel \n
isig icanon -xcase echo echoe echok -echonl -noflsh \n
-tostop echoctl -echoprt echoke -defecho -flusho -pendin iexten \n
opost -olcuc onlcr -ocrnl -onocr -onlret -ofill -ofdel tab3";
	
	const aixSttySample = "speed 38400 baud; 85 rows; 244 columns;\n
eucw 1:1:0:0, scrw 1:1:0:0:\n
intr = ^C; quit = ^\\; erase = ^?; kill = ^U; eof = ^D; eol = <undef>\n
eol2 = <undef>; start = ^Q; stop = ^S; susp = ^Z; dsusp = ^Y; reprint = ^R\n
discard = ^O; werase = ^W; lnext = ^V\n
-parenb -parodd cs8 -cstopb -hupcl cread -clocal -parext \n
-ignbrk brkint -ignpar -parmrk -inpck -istrip -inlcr -igncr icrnl -iuclc \n
ixon ixany -ixoff imaxbel \n
isig icanon -xcase echo echoe echok -echonl -noflsh \n
-tostop echoctl -echoprt echoke -flusho -pending iexten \n
opost -olcuc onlcr -ocrnl -onocr -onlret -ofill -ofdel tab3";
	
	const macOsSttySample = "speed 9600 baud; 47 rows; 155 columns;\n
lflags: icanon isig iexten echo echoe -echok echoke -echonl echoctl\n
-echoprt -altwerase -noflsh -tostop -flusho pendin -nokerninfo\n
-extproc\n
iflags: -istrip icrnl -inlcr -igncr ixon -ixoff ixany imaxbel iutf8\n
-ignbrk brkint -inpck -ignpar -parmrk\n
oflags: opost onlcr -oxtabs -onocr -onlret\n
cflags: cread cs8 -parenb -parodd hupcl -clocal -cstopb -crtscts -dsrflow\n
-dtrflow -mdmbuf\n
cchars: discard = ^O; dsusp = ^Y; eof = ^D; eol = <undef>;\n
eol2 = <undef>; erase = ^?; intr = ^C; kill = ^U; lnext = ^V;\n
min = 1; quit = ^\\; reprint = ^R; start = ^Q; status = ^T;\n
stop = ^S; susp = ^Z; time = 0; werase = ^W;";
	
	const netBsdSttySample = "speed 38400 baud; 85 rows; 244 columns;\n
lflags: icanon isig iexten echo echoe echok echoke -echonl echoctl\n
        -echoprt -altwerase -noflsh -tostop -flusho pendin -nokerninfo\n
        -extproc\n
iflags: -istrip icrnl -inlcr -igncr ixon -ixoff ixany imaxbel -ignbrk\n
        brkint -inpck -ignpar -parmrk\n
oflags: opost onlcr -ocrnl oxtabs onocr onlret\n
cflags: cread cs8 -parenb -parodd hupcl -clocal -cstopb -crtscts -mdmbuf\n
        -cdtrcts\n
cchars: discard = ^O; dsusp = ^Y; eof = ^D; eol = <undef>;\n
        eol2 = <undef>; erase = ^?; intr = ^C; kill = ^U; lnext = ^V;\n
        min = 1; quit = ^\\; reprint = ^R; start = ^Q; status = ^T;\n
        stop = ^S; susp = ^Z; time = 0; werase = ^W;";
	
	const freeBsdSttySample = "speed 9600 baud; 32 rows; 199 columns;\n
lflags: icanon isig iexten echo echoe echok echoke -echonl echoctl\n
        -echoprt -altwerase -noflsh -tostop -flusho -pendin -nokerninfo\n
        -extproc\n
iflags: -istrip icrnl -inlcr -igncr ixon -ixoff ixany imaxbel -ignbrk\n
        brkint -inpck -ignpar -parmrk\n
oflags: opost onlcr -ocrnl tab0 -onocr -onlret\n
cflags: cread cs8 -parenb -parodd hupcl -clocal -cstopb -crtscts -dsrflow\n
        -dtrflow -mdmbuf\n
cchars: discard = ^O; dsusp = ^Y; eof = ^D; eol = <undef>;\n
        eol2 = <undef>; erase = ^?; erase2 = ^H; intr = ^C; kill = ^U;\n
        lnext = ^V; min = 1; quit = ^\\; reprint = ^R; start = ^Q;\n
        status = ^T; stop = ^S; susp = ^Z; time = 0; werase = ^W;";

	
	public function testLinuxSttyParsing() {
		$this->assertEquals(0x7f, TerminalLineSettings::_getProperty("erase", self::linuxSttySample));
		$this->assertEquals(244, TerminalLineSettings::_getProperty("columns", self::linuxSttySample));
		$this->assertEquals(85, TerminalLineSettings::_getProperty("rows", self::linuxSttySample));
	}
	
	
	public function testSolarisSttyParsing() {
		$this->assertEquals(0x7f, TerminalLineSettings::_getProperty("erase", self::solarisSttySample));
		$this->assertEquals(244, TerminalLineSettings::_getProperty("columns", self::solarisSttySample));
		$this->assertEquals(85, TerminalLineSettings::_getProperty("rows", self::solarisSttySample));
	}
	
	
	public function testAixSttyParsing() {
		$this->assertEquals(0x7f, TerminalLineSettings::_getProperty("erase", self::aixSttySample));
		$this->assertEquals(244, TerminalLineSettings::_getProperty("columns", self::aixSttySample));
		$this->assertEquals(85, TerminalLineSettings::_getProperty("rows", self::aixSttySample));
	}
	
	
	public function testMacOsSttyParsing() {
		$this->assertEquals(0x7f, TerminalLineSettings::_getProperty("erase", self::macOsSttySample));
		$this->assertEquals(155, TerminalLineSettings::_getProperty("columns", self::macOsSttySample));
		$this->assertEquals(47, TerminalLineSettings::_getProperty("rows", self::macOsSttySample));
	}
	
	
	public function testNetBsdSttyParsing() {
		$this->assertEquals(0x7f, TerminalLineSettings::_getProperty("erase", self::netBsdSttySample));
		$this->assertEquals(244, TerminalLineSettings::_getProperty("columns", self::netBsdSttySample));
		$this->assertEquals(85, TerminalLineSettings::_getProperty("rows", self::netBsdSttySample));
	}
	
	
	public function testFreeBsdSttyParsing() {
		$this->assertEquals(0x7f, TerminalLineSettings::_getProperty("erase", self::freeBsdSttySample));
		$this->assertEquals(199, TerminalLineSettings::_getProperty("columns", self::freeBsdSttySample));
		$this->assertEquals(32, TerminalLineSettings::_getProperty("rows", self::freeBsdSttySample));
	}
}

