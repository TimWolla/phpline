<?php

require_once 'src/autoload.php';

require_once 'PHPUnit/Framework/TestCase.php';
use \phpline\javaApi\StringBuilder;

/**
 * StringBuilder test case.
 */
class StringBuilderTest extends PHPUnit_Framework_TestCase {
	
	/**
	 *
	 * @var StringBuilder
	 */
	private $StringBuilder;
	
	/**
	 * Prepares the environment before running a test.
	 */
	protected function setUp() {
		parent::setUp ();
		
		// TODO Auto-generated StringBuilderTest::setUp()
		
		$this->StringBuilder = new StringBuilder(/* parameters */);
	}
	
	/**
	 * Cleans up the environment after running a test.
	 */
	protected function tearDown() {
		// TODO Auto-generated StringBuilderTest::tearDown()
		$this->StringBuilder = null;
		
		parent::tearDown ();
	}
	
	/**
	 * Constructs the test case.
	 */
	public function __construct() {
		// TODO Auto-generated constructor
	}
	
	/**
	 * Tests StringBuilder->__construct()
	 */
	public function test__construct() {
		$this->StringBuilder->__construct("");
		$this->assertSame("", $this->StringBuilder->__toString());
		$this->StringBuilder->__construct("a");
		$this->assertSame("a", $this->StringBuilder->__toString());
		$this->StringBuilder->__construct("123");
		$this->assertSame("123", $this->StringBuilder->__toString());
	}
	
	/**
	 * Tests StringBuilder->append()
	 */
	public function testAppend() {
		$this->StringBuilder->append("a");
		$this->assertSame("a", $this->StringBuilder->__toString());
		$this->StringBuilder->append("123");
		$this->assertSame("a123", $this->StringBuilder->__toString());
		$this->StringBuilder->append(new StringBuilder("a"));
		$this->assertSame("a123a", $this->StringBuilder->__toString());
	}
	
	/**
	 * Tests StringBuilder->charAt()
	 */
	public function testCharAt() {
		try {
			$this->StringBuilder->charAt(1);
			$this->fail("Should throw an OutOfBoundsException");
		}
		catch (\OutOfBoundsException $e) { }
		$this->StringBuilder->append("a");
		$this->assertSame("a", $this->StringBuilder->charAt(0));
		$this->StringBuilder->append("123");
		$this->assertSame("a", $this->StringBuilder->charAt(0));
		$this->assertSame("2", $this->StringBuilder->charAt(2));
	}
	
	/**
	 * Tests StringBuilder->delete()
	 */
	public function testDelete() {
		try {
			$this->StringBuilder->delete(1, 2);
			$this->fail("Should throw an OutOfBoundsException");
		}
		catch (\OutOfBoundsException $e) { }
		$this->StringBuilder->append("123456");
		$this->StringBuilder->delete(0, 1);
		$this->assertSame("23456", $this->StringBuilder->__toString());
		$this->StringBuilder->delete(1, 3);
		$this->assertSame("256", $this->StringBuilder->__toString());
	}
	
	/**
	 * Tests StringBuilder->deleteCharAt()
	 */
	public function testDeleteCharAt() {
		try {
			$this->StringBuilder->deleteCharAt(1);
			$this->fail("Should throw an OutOfBoundsException");
		}
		catch (\OutOfBoundsException $e) { }
		$this->StringBuilder->append("123456");
		$this->StringBuilder->deleteCharAt(0);
		$this->assertSame("23456", $this->StringBuilder->__toString());
		$this->StringBuilder->deleteCharAt(1);
		$this->assertSame("2456", $this->StringBuilder->__toString());
	}
	
	/**
	 * Tests StringBuilder->indexOf()
	 */
	public function testIndexOf() {
		$this->StringBuilder->append("31313");
		$this->assertSame(0, $this->StringBuilder->indexOf("3"));
		$this->assertSame(1, $this->StringBuilder->indexOf("1"));
		$this->assertSame(false, $this->StringBuilder->indexOf("5"));
		
		$this->assertSame(2, $this->StringBuilder->indexOf("3", 1));
	}
	
	/**
	 * Tests StringBuilder->lastIndexOf()
	 */
	public function testLastIndexOf() {
		$this->StringBuilder->append("31313");
		$this->assertSame(4, $this->StringBuilder->lastIndexOf("3"));
		$this->assertSame(3, $this->StringBuilder->lastIndexOf("1"));
		$this->assertSame(false, $this->StringBuilder->lastIndexOf("5"));
	}
	
	/**
	 * Tests StringBuilder->insert()
	 */
	public function testInsert() {
		$this->StringBuilder->append("123");
		$this->StringBuilder->insert(0, "a");
		$this->assertSame("a123", $this->StringBuilder->__toString());
		$this->StringBuilder->insert(1, "b");
		$this->assertSame("ab123", $this->StringBuilder->__toString());
		$this->StringBuilder->insert(2, "xy");
		$this->assertSame("abxy123", $this->StringBuilder->__toString());
	}
	
	/**
	 * Tests StringBuilder->length()
	 */
	public function testLength() {
		$this->StringBuilder->append("123");
		$this->assertSame(3, $this->StringBuilder->length());
		$this->StringBuilder->append("123");
		$this->assertSame(6, $this->StringBuilder->length());
	}
	
	/**
	 * Tests StringBuilder->replace()
	 */
	public function testReplace() {
		$this->StringBuilder->append("123");
		$this->StringBuilder->replace(0, 1, "2");
		$this->assertSame("223", $this->StringBuilder->__toString());
		$this->StringBuilder->replace(0, 2, "2");
		$this->assertSame("23", $this->StringBuilder->__toString());
		$this->StringBuilder->replace(0, 1, "12");
		$this->assertSame("123", $this->StringBuilder->__toString());
	}
	
	/**
	 * Tests StringBuilder->substring()
	 */
	public function testSubstring() {
		$this->StringBuilder->append("123");
		$this->assertSame("1", $this->StringBuilder->substring(0, 1));
		$this->assertSame("2", $this->StringBuilder->substring(1, 2));
		$this->assertSame("23", $this->StringBuilder->substring(1, 3));
		$this->assertSame("23", $this->StringBuilder->substring(1));
		
		try {
			$this->StringBuilder->deleteCharAt(4);
			$this->fail("Should throw an OutOfBoundsException");
		}
		catch (\OutOfBoundsException $e) { }
	}
	
	/**
	 * Tests StringBuilder->setLength()
	 */
	public function testSetLength() {
		$this->StringBuilder->append("123");
		$this->StringBuilder->setLength(2);
		$this->assertSame("12", $this->StringBuilder->__toString());
		$this->StringBuilder->setLength(4);
		$this->assertSame("12\x00\x00", $this->StringBuilder->__toString());
		$this->StringBuilder->setLength(0);
		$this->assertSame("", $this->StringBuilder->__toString());
	}
	
	/**
	 * Tests StringBuilder->__toString()
	 */
	public function test__toString() {
		$this->StringBuilder->append("a");
		$this->assertSame("a", $this->StringBuilder->__toString());
		$this->StringBuilder->append("123");
		$this->assertSame("a123", $this->StringBuilder->__toString());
	}
}

