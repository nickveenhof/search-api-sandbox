<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\views\row\SearchApiRow.
 */

namespace Drupal\search_api\Plugin\views\row;

use Drupal\Core\DependencyInjection\Container;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\row\EntityRow;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generic entity row plugin to provide a common base for all entity types.
 *
 * @ViewsRow(
 *   id = "search_api",
 *   title = @Translation("Rendered Search API item"),
 *   help = @Translation("Displays entity of the matching search API item"),
 * )
 */
class SearchApiRow extends EntityRow {

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    $index = $view->storage->get('base_table');

    $id = substr($index, strlen('search_api_index_'));
    $index = $this->entityManager->getStorage('search_api_index')->load($id);
    $datasources = $index->getDataSources();
    $datasource = reset($datasources);
    $this->definition['entity_type'] = $datasource->getEntityTypeId();

    parent::init($view, $display, $options);
    $this->base_table = NULL;
    $this->base_field = NULL;
  }

}
