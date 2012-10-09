<?php

require_once 'PHPUnit/Framework/TestSuite.php';

require_once 'test/StringBuilderTest.php';

require_once 'test/TerminalLineSettingsTest.php';

/**
 * Static test suite.
 */
class PHPLineTestSuite extends PHPUnit_Framework_TestSuite {
	
	/**
	 * Constructs the test suite handler.
	 */
	public function __construct() {
		$this->setName ( 'PHPLineTestSuite' );
		
		$this->addTestSuite ( 'StringBuilderTest' );
		
		$this->addTestSuite ( 'TerminalLineSettingsTest' );
	}
	
	/**
	 * Creates the suite.
	 */
	public static function suite() {
		return new self ();
	}
}

