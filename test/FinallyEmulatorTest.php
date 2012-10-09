<?php

require_once 'src/autoload.php';

require_once 'PHPUnit/Framework/TestCase.php';
use \phpline\internal\FinallyEmulator;

/**
 * FinallyEmulator test case.
 */
class FinallyEmulatorTest extends PHPUnit_Framework_TestCase {
	
	/**
	 *
	 * @var FinallyEmulator
	 */
	private $FinallyEmulator;
	
	/**
	 * Cleans up the environment after running a test.
	 */
	protected function tearDown() {
		// TODO Auto-generated FinallyEmulatorTest::tearDown()
		$this->FinallyEmulator = null;
		
		parent::tearDown ();
	}
	
	/**
	 * Constructs the test case.
	 */
	public function __construct() {
		// TODO Auto-generated constructor
	}
	
	/**
	 * Tests FinallyEmulator->__construct()
	 * 
	 * @expectedException InvalidArgumentException
	 */
	public function test__construct() {
		$this->FinallyEmulator = new FinallyEmulator(array($this, 'invalid'));
	}
	
	public function test__destruct() {
		$fail = true;
		$this->FinallyEmulator = new FinallyEmulator(function () use(&$fail) { $fail = false; });
		$this->FinallyEmulator = null;
		if ($fail) $this->fail("Callback was not called");
	}
	
	/**
	 * Tests FinallyEmulator->__invoke()
	 */
	public function test__invoke() {
		$fail = true;
		$this->FinallyEmulator = new FinallyEmulator(function () use(&$fail) { $fail = false; });
		$finally = $this->FinallyEmulator;
		$finally();
		if ($fail) $this->fail("Callback was not called");
	}
}

