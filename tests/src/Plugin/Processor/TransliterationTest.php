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
   * The processor plugin under test.
   *
   * @var Drupal\search_api\Processor\FieldsProcessorPluginBase
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
   * @covers ::process
   * @dataProvider transliterationStringProvider
   *
   * @param mixed $value
   *   The value to process.
   * @param mixed $expected
   *   The expected result.
   * @param bool $call_transliterate_method
   *   TRUE if the TransliterationInterface::transliterate() is expected to be
   *   called.
   */
  public function testProcess($value, $expected, $call_transliterate_method) {
    if ($call_transliterate_method) {
      $this->transliterationService->expects($this->once())
        ->method('transliterate')
        ->with($value)
        ->will($this->returnValue($expected));
    }
    $this->assertProcess($value, $expected);
  }

  /**
   * Dataprovider for testProcess().
   *
   * @return array
   *   The test data.
   */
  public function transliterationStringProvider() {
    return array(
      // Tests that integers are not affected.
      array(5, 5, FALSE),
      // Tests that floating point numbers are not affected.
      array(3.14, 3.14, FALSE),
      // Tests that ASCII strings are not affected.
      array('ABCDEfghijk12345/$*', 'ABCDEfghijk12345/$*', TRUE),
      // Tests correct transliteration of umlaut and accented characters.
      array('Größe à férfi', 'Grosse a ferfi', TRUE),
    );
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
