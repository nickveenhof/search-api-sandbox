<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\Transliteration.
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor;

use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\search_api\Processor\FieldsProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @SearchApiProcessor(
 *   id = "transliteration",
 *   label = @Translation("Transliteration processor"),
 *   description = @Translation("Processor for making searches insensitive to accents and other non-ASCII characters.")
 * )
 */
class Transliteration extends FieldsProcessorPluginBase {

  /**
  * @var object
  */
  protected $transliterator = NULL;

  /**
  * @var string
  */
  protected $langcode = NULL;

  /**
   * Constructs a Transliteration object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Component\Transliteration\TransliterationInterface $transliterator
   *   The transliteration service.
   * @param string $langcode
   *   (optional) The language code of the language the content is in. Defaults
   *   to 'en' if not provided.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, TransliterationInterface $transliterator, $langcode = 'en') {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->transliterator = $transliterator;
    $this->langcode = $langcode;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $transliterator = \Drupal::service('transliteration');
    $langcode = \Drupal::languageManager()->getDefaultLanguage()->id;
    return new static($configuration, $plugin_id, $plugin_definition, $transliterator, $langcode);
  }

  /**
   * {@inheritdoc}
   */
  protected function process(&$value) {
    // We don't touch integers, NULL values or the like.
    if (is_string($value)) {
      if ($this->langcode && $this->transliterator) {
        $value = $this->transliterator->transliterate($value, $this->langcode);
      }
      else {
        //@todo - what should our fallback position be here?
      }
    }
  }

}
