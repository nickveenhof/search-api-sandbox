<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\views\row\SearchApiRow.
 */

namespace Drupal\search_api\Plugin\views\row;

use Drupal\Component\Utility\String;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\row\RowPluginBase;
use Drupal\views\ResultRow;
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
class SearchApiRow extends RowPluginBase {

  /**
   * The table the entity is using for storage.
   *
   * @var string
   */
  public $base_table;

  /**
   * The actual field which is used for the entity id.
   *
   * @var string
   */
  public $base_field;

  /**
   * Stores the entity type ID of the result entities.
   *
   * @var string
   */
  protected $entityTypeId;

  /**
   * Contains the entity type of this row plugin instance.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected $entityType;

  /**
   * The renderer to be used to render the entity row.
   *
   * @var \Drupal\views\Entity\Render\RendererBase
   */
  protected $renderer;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  public $entityManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * A list of entity render arrays.
   *
   * @var array
   */
  protected $build = array();

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityManagerInterface $entity_manager, LanguageManagerInterface $language_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityManager = $entity_manager;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);

    $index = $view->storage->get('base_table');
    $id = substr($index, strlen('search_api_index_'));
    $index = $this->entityManager->getStorage('search_api_index')->load($id);
    $datasources = $index->getDataSources();
    $datasource = reset($datasources);

    $this->entityTypeId = $datasource->getEntityTypeId();
    $this->entityType = $this->entityManager->getDefinition($this->entityTypeId);
    $this->base_table = $this->entityType->getBaseTable();
    $this->base_field = $this->entityType->getKey('id');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('entity.manager'), $container->get('language_manager'));
  }

  /**
   * Overrides Drupal\views\Plugin\views\row\RowPluginBase::defineOptions().
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['view_mode'] = array('default' => 'default');
    // @todo Make the current language renderer the default as soon as we have a
    //   translation language filter. See https://drupal.org/node/2161845.
    $options['rendering_language'] = array('default' => 'translation_language_renderer');

    return $options;
  }

  /**
   * Overrides Drupal\views\Plugin\views\row\RowPluginBase::buildOptionsForm().
   */
  public function buildOptionsForm(&$form, &$form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['view_mode'] = array(
      '#type' => 'select',
      '#options' => \Drupal::entityManager()->getViewModeOptions($this->entityTypeId),
      '#title' => t('View mode'),
      '#default_value' => $this->options['view_mode'],
    );

    $options = $this->buildRenderingLanguageOptions();
    $form['rendering_language'] = array(
      '#type' => 'select',
      '#options' => $options,
      '#title' => t('Rendering language'),
      '#default_value' => $this->options['rendering_language'],
      '#access' => $this->languageManager->isMultilingual(),
    );
  }

  /**
   * Returns the available rendering strategies for language-aware entities.
   *
   * @return array
   *   An array of available entity row renderers keyed by renderer identifiers.
   */
  protected function buildRenderingLanguageOptions() {
    // @todo Consider making these plugins. See https://drupal.org/node/2173811.
    return array(
      'current_language_renderer' => $this->t('Current language'),
      'default_language_renderer' => $this->t('Default language'),
      'translation_language_renderer' => $this->t('Translation language'),
    );
  }

  /**
   * Overrides Drupal\views\Plugin\views\PluginBase::summaryTitle().
   */
  public function summaryTitle() {
    $options = \Drupal::entityManager()->getViewModeOptions($this->entityTypeId);
    if (isset($options[$this->options['view_mode']])) {
      return String::checkPlain($options[$this->options['view_mode']]);
    }
    else {
      return t('No view mode selected');
    }
  }
  /**
   * {@inheritdoc}
   */
  public function preRender($result) {
    parent::preRender($result);
    /** @var \Drupal\Core\Entity\ContentEntityInterface[] $entities */
    $entities = array();

    /** @var \Drupal\views\ResultRow $row */
    foreach ($result as $row) {
      if (is_object($entity = $row->_entity)) {
        if ($entity = $row->_entity) {
          $entity->view = $this->view;
          $entities[$entity->id()] = $entity;
        }
      }
    }

    if ($entities) {
      $view_builder = $this->entityManager->getViewBuilder($this->entityTypeId);
      $this->build = $view_builder->viewMultiple($entities, $this->view->rowPlugin->options['view_mode']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function render($row) {
    $entity_id = $row->_entity->id();
    return $this->build[$entity_id];
  }

}
