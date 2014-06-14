<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\Plugin\Processor\TransliterationTest.
 */

namespace Drupal\search_api\Tests\Plugin\Processor;

use Drupal\search_api\Plugin\SearchApi\Processor\Transliteration;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the Transliteration processor plugin.
 *
 * @coversDefaultClass \Drupal\search_api\Plugin\SearchApi\Processor\Transliteration
 *
 * @group Drupal
 * @group search_api
 */
class TransliterationTest extends UnitTestCase {

  /**
   * A public version of the Transliteration::process() method.
   *
   * @var \ReflectionMethod
   */
  protected $processMethod;

  /**
   * The Transliteration processor plugin under test.
   *
   * @var Drupal\search_api\Tests\Plugin\Processor
   */
  protected $processor;

  /**
   * The Transliteration service mock used for testing.
   *
   * @var \PHPUnit_Framework_MockObject_MockObject
   */
  protected $transliterationService;

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Transliteration processor test',
      'description' => 'Test if the transliteration processor works.',
      'group' => 'Search API',
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->transliterationService = $this->getMock('\Drupal\Component\Transliteration\TransliterationInterface');
    $this->processor = new Transliteration(array(), 'transliteration', array(), $this->transliterationService);

    // The Transliteration plugin is overriding a protected method from
    // FieldsProcessorPluginBase. Make a public version of it available for
    // testing.
    $reflection_class = new \ReflectionClass($this->processor);
    $this->processMethod = $reflection_class->getMethod('process');
    $this->processMethod->setAccessible(TRUE);
  }

  /**
   * Tests that integers are not affected.
   */
  public function testTransliterationWithInteger() {
    $value = 5;
    $this->assertProcess($value, $value);
  }

  /**
   * Tests that floating point numbers are not affected.
   */
  public function testTransliterationWithDouble() {
    $value = 3.14;
    $this->assertProcess($value, $value);
  }

  /**
   * Tests that ASCII strings are not affected.
   */
  public function testTransliterationWithUSAscii() {
    $value = 'ABCDEfghijk12345/$*';

    $this->transliterationService->expects($this->once())
      ->method('transliterate')
      ->with($value)
      ->will($this->returnValue($value));

    $this->assertProcess($value, $value);
  }

  /**
   * Tests correct transliteration of umlaut and accented characters.
   */
  public function testTransliterationWithNonUSAscii() {
    $value = 'Größe à férfi';
    $expected = 'Grosse a ferfi';

    $this->transliterationService->expects($this->once())
      ->method('transliterate')
      ->with($value)
      ->will($this->returnValue($expected));

    $this->assertProcess($value, $expected);
  }

  /**
   * Asserts that the ::process() method changes $value to the expected result.
   *
   * @param mixed $value
   *   The value to process.
   * @param mixed $expected
   *   The expected result.
   */
  protected function assertProcess($value, $expected) {
    $this->processMethod->invokeArgs($this->processor, array(&$value));
    $this->assertEquals($expected, $value);
  }

}
