<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\Resources\Pd.
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor\Resources;

class So implements unicodeListInterface {

  /**
   * {@inheritdoc}
   */
  public static function getRegularExpression() {
    return '\x{21}\x{23}\x{25}';
  }
}