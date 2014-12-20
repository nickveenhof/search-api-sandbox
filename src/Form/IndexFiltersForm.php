<?php

/**
 * @file
 * Contains \Drupal\search_api\Form\IndexFiltersForm.
 */

namespace Drupal\search_api\Form;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\String;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Processor\ProcessorPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for configuring the processors of a search index.
 */
class IndexFiltersForm extends EntityForm {

  /**
   * The index being configured.
   *
   * @var \Drupal\search_api\IndexInterface
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
   * Constructs an IndexFiltersForm object.
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
    /** @var \Drupal\Core\Entity\EntityManagerInterface $entity_manager */
    $entity_manager = $container->get('entity.manager');
    /** @var \Drupal\search_api\Processor\ProcessorPluginManager $processor_plugin_manager */
    $processor_plugin_manager = $container->get('plugin.manager.search_api.processor');
    return new static($entity_manager, $processor_plugin_manager);
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
  public function form(array $form, FormStateInterface $form_state) {
    // Retrieve lists of all processors, and the stages and weights they have.
    $all_processors = $this->entity->getProcessors(FALSE);
    ksort($all_processors);
    $stages = $this->processorPluginManager->getProcessingStages();
    $processors_by_stage = array();
    foreach ($stages as $stage => $definition) {
      $processors_by_stage[$stage] = $this->entity->getProcessorsByStage($stage, TRUE);
    }

    if (!$form_state->has('processors')) {
      $form_state->set('processors', $all_processors);
    }
    $processors_settings = $this->entity->getOption('processors');

    $form['#tree'] = TRUE;
    $form['#attached']['library'][] = 'search_api/drupal.search_api.index-active-formatters';
    $form['#title'] = $this->t('Manage filters for search index %label', array('%label' => $this->entity->label()));
    $form['#prefix'] = '<p>' . $this->t('Configure processors which will pre- and post-process data at index and search time.') . '</p>';

    // Add the list of processors with checkboxes to enable/disable them.
    $form['status'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Enabled'),
      '#attributes' => array('class' => array(
        'search-api-status-wrapper',
      )),
    );
    foreach ($all_processors as $name => $processor) {
      $form['status'][$name] = array(
        '#type' => 'checkbox',
        '#title' => $processor->label(),
        '#default_value' => isset($processors_settings[$name]['status']) ? $processors_settings[$name]['status'] : 0,
        '#parents' => array('processors', $name, 'status'),
        '#description' => $processor->getDescription(),
        '#attributes' => array('class' => array(
          'search-api-processor-status-' . Html::cleanCssIdentifier($name),
        )),
      );
    }

    $form['weight'] = array(
      '#type' => 'fieldset',
      '#title' => t('Processor order'),
    );
    // Order enabled processors per stage.
    foreach ($stages as $stage => $description) {
      $form['weight'][$stage] = array (
        '#type' => 'fieldset',
        '#title' => $description['label'],
        '#attributes' => array('class' => array(
          'search-api-stage-wrapper',
          'search-api-stage-wrapper-' . Html::cleanCssIdentifier($stage),
        )),
      );
      $form['weight'][$stage]['order'] = array(
        '#type' => 'table',
      );
      $form['weight'][$stage]['order']['#tabledrag'][] = array(
        'action' => 'order',
        'relationship' => 'sibling',
        'group' => 'search-api-processor-weight-' . Html::cleanCssIdentifier($stage),
      );
    }
    foreach ($processors_by_stage as $stage => $processors) {
      /** @var \Drupal\search_api\Processor\ProcessorInterface $processor */
      foreach ($processors as $name => $processor) {
        $form['weight'][$stage]['order'][$name]['#attributes']['class'][] = 'draggable';
        $form['weight'][$stage]['order'][$name]['label'] = array(
          '#markup' => String::checkPlain($processor->label()),
        );
        $form['weight'][$stage]['order'][$name]['weight'] = array(
          '#type' => 'weight',
          '#title' => $this->t('Weight for processor %title', array('%title' => $processor->label())),
          '#title_display' => 'invisible',
          '#default_value' => isset($processors_settings[$name][$stage]['weight'])
            ? $processors_settings[$name][$stage]['weight']
            : $processor->getDefaultWeight($stage),
          '#parents' => array('processors', $name, $stage, 'weight'),
          '#attributes' => array('class' => array(
            'search-api-processor-weight-' . Html::cleanCssIdentifier($stage),
            'search-api-processor-weight-' . Html::cleanCssIdentifier($name),
          )),
        );
      }
    }

    // Add vertical tabs containing the settings for the processors. Tabs for
    // disabled processors are hidden with JS magic, but need to be included in
    // case the processor is enabled.
    $form['processor_settings'] = array(
      '#title' => $this->t('Processor settings'),
      '#type' => 'vertical_tabs',
    );

    foreach ($all_processors as $name => $processor) {
      $settings_form = $processor->buildConfigurationForm($form, $form_state);
      if (!empty($settings_form)) {
        $form['settings'][$name] = array(
          '#type' => 'details',
          '#title' => $processor->label(),
          '#group' => 'processor_settings',
          '#parents' => array('processors', $name, 'settings'),
          '#attributes' => array('class' => array(
            'search-api-processor-settings-' . Html::cleanCssIdentifier($name),
          )),
        );
        $form['settings'][$name] += $settings_form;
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validate(array $form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    /** @var $processor \Drupal\search_api\Processor\ProcessorInterface */
    foreach ($form_state->get('processors') as $name => $processor) {
      if (isset($values['processors'][$name]['settings'])) {
        if (!empty($values['processors'][$name]['status'])) {
          $processor_form_state = new SubFormState($form_state, array('processors', $name, 'settings'));
          $processor->validateConfigurationForm($form['settings'][$name], $processor_form_state);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    // Due to the "#parents" settings, these are all empty arrays.
    unset($values['settings']);
    unset($values['status']);
    unset($values['order']);

    $options = $this->entity->getOptions();

    // Store processor settings.
    // @todo Go through all available processors, enable/disable with method on
    //   processor plugin to allow reaction.
    /** @var \Drupal\search_api\Processor\ProcessorInterface $processor */
    foreach ($form_state->get('processors') as $processor_id => $processor) {
      $processor_form = array();
      if (isset($form['settings'][$processor_id])) {
        $processor_form = &$form['settings'][$processor_id];
      }
      $default_settings = array(
        'settings' => array(),
        'processorPluginId' => $processor_id,
      );
      $values['processors'][$processor_id] += $default_settings;
      $processor_form_state = new SubFormState($form_state, array('processors', $processor_id, 'settings'));
      $processor->submitConfigurationForm($processor_form, $processor_form_state);

      $values['processors'][$processor_id]['settings'] = $processor->getConfiguration();
    }

    if (!isset($options['processors']) || $options['processors'] !== $values['processors']) {
      // Save the already sorted arrays to avoid having to sort them at each
      // use.
      uasort($values['processors'], array('Drupal\Component\Utility\SortArray', 'sortByWeightElement'));
      $this->entity->setOption('processors', $values['processors']);

      $this->entity->save();
      $this->entity->reindex();
      drupal_set_message($this->t("The indexing workflow was successfully edited. All content was scheduled for reindexing so the new settings can take effect."));
    }
    else {
      drupal_set_message($this->t('No values were changed.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);

    // We don't have a "delete" action here.
    unset($actions['delete']);

    return $actions;
  }

}
