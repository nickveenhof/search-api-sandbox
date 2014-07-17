<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\Plugin\Processor\TransliterationTest.
 */

namespace Drupal\search_api\Tests\Plugin\Processor;

use Drupal\search_api\Plugin\SearchApi\Processor\AggregatedField;
use Drupal\search_api\Plugin\SearchApi\Processor\Transliteration;
use Drupal\search_api\Tests\Processor\TestItemsTrait;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the "Transliteration" processor.
 *
 * @group search_api
 *
 * @see \Drupal\search_api\Plugin\SearchApi\Processor\Transliteration
 */
class AggregatedFieldTest extends UnitTestCase {

  use ProcessorTestTrait, TestItemsTrait;

  /**
   * A test index mock to use for tests.
   *
   * @var \Drupal\search_api\Index\IndexInterface
   */
  protected $index;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    $this->index = $this->getMock('Drupal\search_api\Index\IndexInterface');
    $this->processor = new AggregatedField(array(), 'aggregated_field', array());
  }

  /**
   * Tests that integers are not affected.
   */
  public function testAggregatedFieldConcat() {
    $field_value = 5;

    $field = Utility::createField($this->index, 'field_1');
    $field->setType('text');

    $items = $this->createSingleFieldItem($this->index, 'int', $field_value, $field);

    $this->processor->setConfiguration(array('fields' => array('field_1' => array('label' => 'new_field', 'type' => 'concat'))));

    $this->processor->preprocessIndexItems($items);
    $this->assertEquals(array($field_value), $field->getValues(), 'Integer not affected by transliteration.');

  }

}
