<?php

/**
 * @file
 * Contains \Drupal\search_api\Processor\ProcessorPluginBase.
 */

namespace Drupal\search_api\Processor;

use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Plugin\IndexPluginBase;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSetInterface;

/**
 * Defines a base class from which other processors may extend.
 *
 * Plugins extending this class need to define a plugin definition array through
 * annotation. These definition arrays may be altered through
 * hook_search_api_processor_info_alter(). The definition includes the following
 * keys:
 * - id: The unique, system-wide identifier of the processor.
 * - label: The human-readable name of the processor, translated.
 * - description: A human-readable description for the processor, translated.
 *
 * A complete sample plugin definition should be defined as in this example:
 *
 * @code
 * @SearchApiProcessor(
 *   id = "my_processor",
 *   label = @Translation("My Processor"),
 *   description = @Translation("Does … something.")
 * )
 * @endcode
 */
abstract class ProcessorPluginBase extends IndexPluginBase implements ProcessorInterface {

  // @todo translate labels here?
  public static $stages = array(
    ProcessorInterface::PROCESSOR_STAGE_PREPROCESS_INDEX => array(
      'label' => 'Preprocess index',
    ),
    ProcessorInterface::PROCESSOR_STAGE_PREPROCESS_QUERY => array(
      'label' => 'Preprocess query',
    ),
    ProcessorInterface::PROCESSOR_STAGE_POSTPROCESS => array(
      'label' => 'Postprocess'
    ),
  );

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsStage($stage_identifier) {
    $plugin_definition = $this->getPluginDefinition();
    return isset($plugin_definition['stages'][$stage_identifier]);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultWeight($stage) {
    $plugin_definition = $this->getPluginDefinition();
    return $plugin_definition['stages'][$stage];
  }

  /**
   * {@inheritdoc}
   */
  public function alterPropertyDefinitions(array &$properties, DatasourceInterface $datasource = NULL) {}

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array &$items) {}

  /**
   * {@inheritdoc}
   */
  public function preprocessSearchQuery(QueryInterface $query) {}

  /**
   * {@inheritdoc}
   */
  public function postprocessSearchResults(ResultSetInterface $results) {}

}
