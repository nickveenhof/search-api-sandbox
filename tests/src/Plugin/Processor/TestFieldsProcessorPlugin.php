<?php

/**
 * @file
 * Contains Drupal\search_api\Tests\Plugin\Processor\TestFieldsProcessorPlugin.
 */

namespace Drupal\search_api\Tests\Plugin\Processor;

use Drupal\search_api\Processor\FieldsProcessorPluginBase;

/**
 * Test class for processors that work on individual fields.
 */
class TestFieldsProcessorPlugin extends FieldsProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function process(&$value) {
    $value = 'processed';
  }

}
