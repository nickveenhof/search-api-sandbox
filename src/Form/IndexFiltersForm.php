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
use Drupal\search_api\Processor\ProcessorInterface;
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
    if (!$form_state->has('processors')) {
      $all_processors = $this->entity->getProcessors(FALSE);
      $sort_processors = function (ProcessorInterface $a, ProcessorInterface $b) {
        return strnatcasecmp($a->label(), $b->label());
      };
      uasort($all_processors, $sort_processors);
      $form_state->set('processors', $all_processors);
    }
    else {
      $all_processors = $form_state->get('processors');
    }

    $stages = $this->processorPluginManager->getProcessingStages();
    $processors_by_stage = array();
    foreach ($stages as $stage => $definition) {
      $processors_by_stage[$stage] = $this->entity->getProcessorsByStage($stage, FALSE);
    }

    $processor_settings = $this->entity->getOption('processors');

    $form['#tree'] = TRUE;
    $form['#attached']['library'][] = 'search_api/drupal.search_api.index-active-formatters';
    $form['#title'] = $this->t('Manage filters for search index %label', array('%label' => $this->entity->label()));
    $form['description']['#markup'] = '<p>' . $this->t('Configure processors which will pre- and post-process data at index and search time.') . '</p>';

    // Add the list of processors with checkboxes to enable/disable them.
    $form['status'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Enabled'),
      '#attributes' => array('class' => array(
        'search-api-status-wrapper',
      )),
    );
    foreach ($all_processors as $processor_id => $processor) {
      $form['status'][$processor_id] = array(
        '#type' => 'checkbox',
        '#title' => $processor->label(),
        '#default_value' => !empty($processor_settings[$processor_id]),
        '#description' => $processor->getDescription(),
        '#attributes' => array('class' => array(
          'search-api-processor-status-' . Html::cleanCssIdentifier($processor_id),
        )),
      );
    }

    $form['weights'] = array(
      '#type' => 'fieldset',
      '#title' => t('Processor order'),
    );
    // Order enabled processors per stage.
    foreach ($stages as $stage => $description) {
      $form['weights'][$stage] = array (
        '#type' => 'fieldset',
        '#title' => $description['label'],
        '#attributes' => array('class' => array(
          'search-api-stage-wrapper',
          'search-api-stage-wrapper-' . Html::cleanCssIdentifier($stage),
        )),
      );
      $form['weights'][$stage]['order'] = array(
        '#type' => 'table',
      );
      $form['weights'][$stage]['order']['#tabledrag'][] = array(
        'action' => 'order',
        'relationship' => 'sibling',
        'group' => 'search-api-processor-weight-' . Html::cleanCssIdentifier($stage),
      );
    }
    foreach ($processors_by_stage as $stage => $processors) {
      /** @var \Drupal\search_api\Processor\ProcessorInterface $processor */
      foreach ($processors as $processor_id => $processor) {
        $form['weights'][$stage]['order'][$processor_id]['#attributes']['class'][] = 'draggable';
        $form['weights'][$stage]['order'][$processor_id]['label'] = array(
          '#markup' => String::checkPlain($processor->label()),
        );
        $form['weights'][$stage]['order'][$processor_id]['weight'] = array(
          '#type' => 'weight',
          '#title' => $this->t('Weight for processor %title', array('%title' => $processor->label())),
          '#title_display' => 'invisible',
          '#default_value' => isset($processor_settings[$processor_id]['weights'][$stage])
            ? $processor_settings[$processor_id]['weights'][$stage]
            : $processor->getDefaultWeight($stage),
          '#parents' => array('processors', $processor_id, 'weights', $stage),
          '#attributes' => array('class' => array(
            'search-api-processor-weight-' . Html::cleanCssIdentifier($stage),
            'search-api-processor-weight-' . Html::cleanCssIdentifier($processor_id),
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

    $processor_form_states = array();
    foreach ($all_processors as $processor_id => $processor) {
      $processor_form_states[$processor_id] = new SubFormState($form_state, array('processors', $processor_id, 'settings'));
      $processor_form = $processor->buildConfigurationForm($form, $processor_form_states[$processor_id]);
      if ($processor_form) {
        $form['settings'][$processor_id] = array(
          '#type' => 'details',
          '#title' => $processor->label(),
          '#group' => 'processor_settings',
          '#parents' => array('processors', $processor_id, 'settings'),
          '#attributes' => array('class' => array(
            'search-api-processor-settings-' . Html::cleanCssIdentifier($processor_id),
          )),
        );
        $form['settings'][$processor_id] += $processor_form;
      }
      else {
        // We don't need a form state for processors without settings form from
        // here on. We will also use this to determine which processors have
        // forms and which can be skipped for validation/submission.
        unset($processor_form_states[$processor_id]);
      }
    }

    $form_state->set('processor_form_states', $processor_form_states);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validate(array $form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    /** @var \Drupal\search_api\Processor\ProcessorInterface[] $processors */
    $processors = $form_state->get('processors');

    foreach ($form_state->get('processor_form_states') as $processor_id => $processor_form_state) {
      if (!empty($values['status'][$processor_id])) {
        $processors[$processor_id]->validateConfigurationForm($form['settings'][$processor_id], $processor_form_state);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $processor_form_states = $form_state->get('processor_form_states');
    $new_settings = array();

    // Store processor settings.
    // @todo Go through all available processors, enable/disable with method on
    //   processor plugin to allow reaction.
    /** @var \Drupal\search_api\Processor\ProcessorInterface $processor */
    foreach ($form_state->get('processors') as $processor_id => $processor) {
      if (empty($values['status'][$processor_id])) {
        continue;
      }
      $new_settings[$processor_id] = array(
        'processor_id' => $processor_id,
        'weights' => array(),
        'settings' => array(),
      );
      $processor_values = $values['processors'][$processor_id];
      if (!empty($processor_values['weights'])) {
        $new_settings[$processor_id]['weights'] = $processor_values['weights'];
      }
      if (isset($processor_form_states[$processor_id])) {
        $processor->submitConfigurationForm($form['settings'][$processor_id], $processor_form_states[$processor_id]);
        $new_settings[$processor_id]['settings'] = $processor->getConfiguration();
      }
    }

    // Sort the processors so we won't have unnecessary changes.
    ksort($new_settings);
    if (!$this->entity->getOption('processors', array()) !== $new_settings) {
      $this->entity->setOption('processors', $new_settings);
      $this->entity->save();
      $this->entity->reindex();
      drupal_set_message($this->t('The indexing workflow was successfully edited. All content was scheduled for reindexing so the new settings can take effect.'));
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
