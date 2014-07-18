<?php

/**
 * @file
 * Contains Drupal\search_api\Tests\Plugin\Processor\TestFieldsProcessorPlugin.
 */

namespace Drupal\search_api\Tests\Plugin\Processor;

use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Processor\FieldsProcessorPluginBase;

/**
 * Test class for processors that work on individual fields.
 */
class TestFieldsProcessorPlugin extends FieldsProcessorPluginBase {

  /**
   * Array of method overrides.
   *
   * @var callable[]
   */
  protected $methodOverrides = array();

  /**
   * Overrides a method in this processor.
   *
   * @param string $method
   *   The name of the method to override.
   * @param callable|null $override
   *   The new code of the method, or NULL to use the default.
   */
  public function setMethodOverride($method, $override = NULL) {
    $this->methodOverrides[$method] = $override;
  }

  /**
   * {@inheritdoc}
   */
  protected function testField($name, FieldInterface $field) {
    if (isset($this->methodOverrides[__FUNCTION__])) {
      return $this->methodOverrides[__FUNCTION__]($name, $field);
    }
    return parent::testField($name, $field);
  }

  /**
   * {@inheritdoc}
   */
  protected function testType($type) {
    if (isset($this->methodOverrides[__FUNCTION__])) {
      return $this->methodOverrides[__FUNCTION__]($type);
    }
    return parent::testType($type);
  }

  /**
   * {@inheritdoc}
   */
  protected function processFieldValue(&$value, &$type) {
    if (isset($this->methodOverrides[__FUNCTION__])) {
      $this->methodOverrides[__FUNCTION__]($value, $type);
      return;
    }
    parent::processFieldValue($value, $type);
  }

  /**
   * {@inheritdoc}
   */
  protected function processKey(&$value) {
    if (isset($this->methodOverrides[__FUNCTION__])) {
      $this->methodOverrides[__FUNCTION__]($value);
      return;
    }
    parent::processKey($value);
  }

  /**
   * {@inheritdoc}
   */
  protected function processFilterValue(&$value) {
    if (isset($this->methodOverrides[__FUNCTION__])) {
      $this->methodOverrides[__FUNCTION__]($value);
      return;
    }
    parent::processFilterValue($value);
  }

  /**
   * {@inheritdoc}
   */
  protected function process(&$value) {
    if (isset($value)) {
      $value = "*$value";
    }
    else {
      $value = 'undefined';
    }
  }

}
