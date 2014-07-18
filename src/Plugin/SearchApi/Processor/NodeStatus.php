<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\NodeStatus.
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor;

use Drupal\node\NodeInterface;
use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;

/**
 * @SearchApiProcessor(
 *   id = "node_status",
 *   label = @Translation("Node status"),
 *   description = @Translation("Exclude unpublished nodes from node indexes.")
 * )
 */
class NodeStatus extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index) {
    foreach ($index->getDatasources() as $datasource) {
      if ($datasource->getEntityTypeId() == 'node') {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array &$items) {
    // Annoyingly, this doc comment is needed for PHPStorm. See
    // http://youtrack.jetbrains.com/issue/WI-23586
    /** @var \Drupal\search_api\Item\ItemInterface $item */
    foreach ($items as $item_id => $item) {
      $object = $item->getOriginalObject();
      if ($object instanceof NodeInterface && !$object->isPublished()) {
        unset($items[$item_id]);
      }
    }
  }

}
