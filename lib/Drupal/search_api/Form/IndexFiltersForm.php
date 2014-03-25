<?php
/**
 * @file
 * Contains \Drupal\search_api\Form\IndexFiltersFormController.
 */

namespace Drupal\search_api\Form;

use Drupal\Core\Entity\EntityFormController;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\search_api\Processor\ProcessorInterface;
use Drupal\search_api\Processor\ProcessorPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a filters form for the Index entity.
 */
class IndexFiltersForm extends EntityFormController {

  /**
   * The index being configured.
   *
   * @var \Drupal\search_api\Index\IndexInterface
   */
  protected $entity;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The datasource manager.
   *
   * @var \Drupal\search_api\Processor\ProcessorPluginManager
   */
  protected $processorPluginManager;

  /**
   * Constructs a ContentEntityFormController object.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\search_api\Processor\ProcessorPluginManager $processor_plugin_manager
   *   The processor plugin manager.
   */
  public function __construct(EntityManagerInterface $entity_manager, ProcessorPluginManager $processor_plugin_manager) {
    $this->entityManager = $entity_manager;
    $this->processorPluginManager = $processor_plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager'),
      $container->get('search_api.processor.plugin.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseFormID() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, array &$form_state) {
    // Fetch all the selected options
    $options = $this->entity->getOptions();

    // Fetch all the processor plugins
    $processor_info = $this->processorPluginManager->getDefinitions();


    $form['#tree'] = TRUE;
    //$form['#attached']['js'][] = drupal_get_path('module', 'search_api') . '/search_api.admin.js';

    // Processors
    $processors = $this->entity->getOption('processors');
    $processor_objects = isset($form_state['processors']) ? $form_state['processors'] : array();
    foreach ($processor_info as $name => $processor) {
      if (!isset($processors[$name])) {
        $processors[$name]['status'] = 0;
        $processors[$name]['weight'] = 0;
      }
      $settings = empty($processors[$name]['settings']) ? array() : $processors[$name]['settings'];
      $settings['index'] = $this->entity;

      if (empty($processor_objects[$name]) && class_exists($processor['class'])) {
        $processor_objects[$name] = $this->processorPluginManager->createInstance($name, $settings);
      }

      if (!(class_exists($processor['class']) && $processor_objects[$name] instanceof ProcessorInterface)) {
        watchdog('search_api', t('Processor @id specifies illegal processor class @class.', array('@id' => $name, '@class' => $processor['class'])), NULL, WATCHDOG_WARNING);
        unset($processor_info[$name]);
        unset($processors[$name]);
        unset($processor_objects[$name]);
        continue;
      }
      if (!$processor_objects[$name]->supportsIndex($this->entity)) {
        unset($processor_info[$name]);
        unset($processors[$name]);
        unset($processor_objects[$name]);
        continue;
      }
    }

    $form_state['processors'] = $processor_objects;
    $form['#processors'] = $processors;
    $form['processors'] = array(
      '#type' => 'details',
      '#title' => t('Processors'),
      '#description' => t('Select processors which will pre- and post-process data at index and search time, and their order. ' .
        'Most processors will only influence fulltext fields, but refer to their individual descriptions for details regarding their effect.'),
      '#open' => TRUE,
    );

    // Processor status.
    $form['processors']['status'] = array(
      '#type' => 'item',
      '#title' => t('Enabled processors'),
      '#prefix' => '<div class="search-api-status-wrapper">',
      '#suffix' => '</div>',
    );

    foreach ($processor_info as $name => $processor) {
      $form['processors']['status'][$name] = array(
        '#type' => 'checkbox',
        '#title' => $processor['label'],
        '#default_value' => $processors[$name]['status'],
        '#parents' => array('processors', $name, 'status'),
        '#description' => $processor['description'],
        //'#weight' => $processor['weight'],
      );
    }

    // Processor order (tabledrag).
    $form['processors']['order'] = array(
      '#markup' =>  t('Processor processing order'),
      '#description' => t('Set the order in which preprocessing will be done at index and search time. ' .
        'Postprocessing of search results will be in the exact opposite direction.'),
    );

    $header = array(
      array('data' => t('Processor')),
      array('data' => t('Weight')),
    );

    $rows = array();
    foreach ($processor_info as $name => $processor) {
      $row = array();
      $row[]['data'] = $processor['label'];
      $row[]['data'] = $processors[$name]['weight'];

      $rows[] = $row;
    }

    $form['processors']['order'] = array(
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#attributes' => array('id' => "search-api-processors"),
      '#tabledrag' => array(
        array(
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => "search-api-processor-weight",
        ),
      ),
    );

    // Processor settings.
    $form['processors']['settings_title'] = array(
      '#type' => 'item',
      '#title' => t('Processor settings'),
    );
    $form['processors']['processor_settings'] = array(
      '#type' => 'vertical_tabs',
    );

    foreach ($processor_info as $name => $processor) {
      /** @var $processor_plugin \Drupal\search_api\Processor\ProcessorInterface */
      $processor_plugin = $processor_objects[$name];
      $settings_form = $processor_plugin->buildConfigurationForm($form, $form_state);

      if (!empty($settings_form)) {
        $form['processors']['settings'][$name] = array(
          '#type' => 'details',
          '#title' => $processor['label'],
          '#group' => 'processor_settings',
          //'#weight' => $processor['weight'],
        );
        $form['processors']['settings'][$name] += $settings_form;
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validate(array $form, array &$form_state) {
    foreach ($form_state['processors'] as $name => $processor) {
      if (isset($form['processors']['settings'][$name]) && isset($form_state['values']['processors'][$name]['settings'])) {
        /** @var $processor \Drupal\search_api\Processor\ProcessorInterface */
        $processor->validateConfigurationForm($form['processors']['settings'][$name], $form_state['values']['processors'][$name]['settings'], $form_state);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array $form, array &$form_state) {
    $values = $form_state['values'];
    unset($values['processors']['settings']);

    $options = $this->entity->getOptions();

    // Store processor settings.
    foreach ($form_state['processors'] as $name => $processor) {
      $processor_form = isset($form['processors']['settings'][$name]) ? $form['processors']['settings'][$name] : array();
      $values['processors'][$name] += array('settings' => array());
      /** @var $processor \Drupal\search_api\Processor\ProcessorInterface */
      $values['processors'][$name]['settings'] = $processor->submitConfigurationForm($processor_form, $values['processors'][$name]['settings'], $form_state);
    }


    if (!isset($options['processors']) || $options['processors'] != $values['processors']) {
      // Save the already sorted arrays to avoid having to sort them at each use.
      uasort($values['processors'], 'search_api_admin_element_compare');
      $this->entity->setOption('processors', $values['processors']);

      // Reset the index's internal property cache to correctly incorporate the
      // new data alterations.
      //$this->entity->resetCaches();

      $this->entity->save();
      //$this->entity->reindex();
      drupal_set_message(t("The indexing workflow was successfully edited. All content was scheduled for re-indexing so the new settings can take effect."));
    }
    else {
      drupal_set_message(t('No values were changed.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, array &$form_state) {
    $actions = parent::actions($form, $form_state);

    // Remove the delete action
    unset($actions['delete']);

    return $actions;
  }

}
