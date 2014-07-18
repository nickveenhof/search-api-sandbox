<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\Plugin\Processor\FieldsProcessorPluginBaseTest.
 */

namespace Drupal\search_api\Tests\Plugin\Processor;

use Drupal\search_api\Tests\Processor\TestItemsTrait;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the FieldsProcessorPluginBase class.
 *
 * @coversDefaultClass \Drupal\search_api\Processor\FieldsProcessorPluginBase
 *
 * @group search_api
 */
class FieldsProcessorPluginBaseTest extends UnitTestCase {

  use TestItemsTrait;

  /**
   * A search index mock to use in this test case.
   *
   * @var \Drupal\search_api\Index\IndexInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $index;

  /**
   * The class under test.
   *
   * @var \Drupal\search_api\Processor\FieldsProcessorPluginBase|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $processor;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->index = $this->getMock('Drupal\search_api\Index\IndexInterface');

    $this->processor = $this->getMockBuilder('Drupal\search_api\Processor\FieldsProcessorPluginBase')
      ->disableOriginalConstructor()
      ->getMock();
    $this->processor->expects($this->any())
      ->method('process')
      ->willReturnCallback(function ($value) { return "*$value"; });
  }

  /**
   * Tests whether the default implementation of testType() works correctly.
   */
  public function testTestTypeDefault() {
    $items = $this->getTestItem();
    $this->processor->preprocessIndexItems($items);
    $this->verifyFieldsProcessed($items, array('text_field', 'tokenized_text_field', 'string_field'));
  }

  /**
   * Returns an array with one test item suitable for this test case.
   *
   * @param string[] $types
   *   The types of fields to create.
   *
   * @return \Drupal\search_api\Item\ItemInterface[]
   *   An array containing one item.
   */
  protected function getTestItem($types = array('text', 'tokenized_text', 'string', 'integer', 'float')) {
    $fields = array();
    foreach ($types as $type) {
      $field_id = "{$type}_field";
      $fields[$field_id] = array(
        'type' => $type,
        'values' => array(
          "$field_id value 1",
          "$field_id value 2",
        ),
      );
    }
    return $this->createItems($this->index, 1, $fields);
  }

  /**
   * Returns an array with one test item suitable for this test case.
   *
   * @param \Drupal\search_api\Item\ItemInterface[] $items
   *   An array containing one item.
   * @param string[] $processed_fields
   *   The fields which should be processed.
   */
  protected function verifyFieldsProcessed(array $items, array $processed_fields) {
    $processed_fields = array_fill_keys($processed_fields, TRUE);
    foreach ($items as $item) {
      foreach ($item->getFields() as $field_id => $field) {
        if (!empty($processed_fields[$field_id])) {
          $expected = array(
            "*$field_id value 1",
            "*$field_id value 2",
          );
        }
        else {
          $expected = array(
            "$field_id value 1",
            "$field_id value 2",
          );
        }
        $this->assertEquals($expected, $field->getValues(), "Field $field_id is correct.");
      }
    }
  }

}
