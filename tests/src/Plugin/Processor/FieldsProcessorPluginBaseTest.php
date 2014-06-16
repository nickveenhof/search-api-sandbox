<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\Plugin\Processor\FieldsProcessorPluginBaseTest.
 */

namespace Drupal\search_api\Tests\Plugin\Processor;

use Drupal\Tests\UnitTestCase;
use Drupal\search_api\Tests\Plugin\Processor\TestFieldsProcessorPlugin;

/**
 * Tests the FieldsProcessorPluginBase class.
 *
 * @coversDefaultClass \Drupal\search_api\Processor\FieldsProcessorPluginBase
 *
 * @group Drupal
 * @group search_api
 */
class FieldsProcessorPluginBaseTest extends UnitTestCase {

  /**
   * The class under test.
   *
   * @var TestFieldsProcessorPlugin
   */
  protected $class;

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Fields processor plugin base test',
      'description' => 'Test the base class for fields processor plugins.',
      'group' => 'Search API',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->class = $this->getMock('\Drupal\search_api\Tests\Plugin\Processor\TestFieldsProcessorPlugin', NULL, array(array(), '', array()));
  }

  /**
   * Tests preprocessIndexItems() with an item that should not be processed.
   *
   * @covers ::preprocessIndexItems
   */
  public function testPreprocessIndexItemsWithoutProcessableField() {
    // Prepare an item containing an unprocessable field type.
    $field = $this->getMock('Drupal\search_api\Item\FieldInterface');
    $field->expects($this->once())
      ->method('getType')
      ->will($this->returnValue('some unprocessable type'));

    $item = $this->getMockBuilder('Drupal\search_api\Item\Item')
      ->disableOriginalConstructor()
      ->getMock();

    $item->expects($this->once())
      ->method('getIterator')
      ->will($this->returnValue(new \ArrayIterator(array('field1' => $field))));

    $items = array($item);

    $this->class->preprocessIndexItems($items);

    // @todo Verify that the value has not changed.
  }

}
