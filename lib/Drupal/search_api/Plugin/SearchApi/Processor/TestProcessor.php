<?php

namespace Drupal\search_api\Plugin\SearchApi\Processor;

use Drupal\search_api\Processor\ProcessorPluginBase;

/**
 * @SearchApiProcessor(
 *   id = "search_api_test_processor",
 *   label = @Translation("Test processor"),
 *   description = @Translation("Dummy processor implementation")
 * )
 */
class TestProcessor extends ProcessorPluginBase {
  public function buildConfigurationForm(array $form, array &$form_state) {

  }
  public function defaultConfiguration() {

  }
  public function getConfiguration() {

  }
  public function setConfiguration(array $configuration) {

  }
  public function submitConfigurationForm(array &$form, array &$form_state) {

  }
  public function validateConfigurationForm(array &$form, array &$form_state) {

  }
}
