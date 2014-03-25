<?php
/**
 * @file
 * Contains \Drupal\search_api\Form\IndexFieldsForm.
 */

namespace Drupal\search_api\Form;

use Drupal\Core\Entity\EntityFormController;
use Drupal\Core\Entity\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Utility\String;

/**
 * Provides a fields form controller for the Index entity.
 */
class IndexFieldsForm extends EntityFormController {

  /**
   * The index where the fields will be configured for
   *
   * @var \Drupal\search_api\Index\IndexInterface
   */
  protected $entity;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManager
   */
  private $entityManager;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'search_api_index_fields';
  }

  /**
   * If getFormId is implemented, we do not need getBaseFormID().
   *
   * {@inheritdoc}
   */
  public function getBaseFormID() {
    return NULL;
  }

  /**
   * Get the entity manager.
   *
   * @return \Drupal\Core\Entity\EntityManager
   *   An instance of EntityManager.
   */
  protected function getEntityManager() {
    return $this->entityManager;
  }

  /**
   * Constructs a ContentEntityFormController object.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   */
  public function __construct(EntityManagerInterface $entity_manager) {
    $this->entityManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var \Drupal\Core\Entity\EntityManagerInterface $entity_manager */
    $entity_manager = $container->get('entity.manager');
    return new static($entity_manager);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state) {
    // Get the index
    $index = $this->entity;

    // Get all options
    $options = $index->getFields(FALSE, TRUE);
    $fields = $options['fields'];
    $additional = $options['additional fields'];

    // An array of option arrays for types, keyed by nesting level.
    $types = array(0 => search_api_field_types());

    // Get all entity types
    $entity_types = $this->getEntityManager()->getDefinitions();
    $boost_values = array('0.1', '0.2', '0.3', '0.5', '0.8', '1.0', '2.0', '3.0', '5.0', '8.0', '13.0', '21.0');
    $boosts = array_combine($boost_values, $boost_values);

    $fulltext_types = array(0 => array('text'));
    // Add all custom data types with fallback "text" to fulltext types as well.
    foreach (search_api_get_data_type_info() as $id => $type) {
      if ($type['fallback'] != 'text') {
        continue;
      }
      $fulltext_types[0][] = $id;
    }

    $form_state['index'] = $index;
    $form['#theme'] = 'search_api_admin_fields_table';
    $form['#tree'] = TRUE;
    $form['description'] = array(
      '#type' => 'item',
      '#title' => t('Select fields to index'),
      '#description' => t('<p>The datatype of a field determines how it can be used for searching and filtering. Fields indexed with type "Fulltext" and multi-valued fields (marked with <sup>1</sup>) cannot be used for sorting. ' .
        'The boost is used to give additional weight to certain fields, e.g. titles or tags. It only takes effect for fulltext fields.</p>' .
        '<p>Whether detailed field types are supported depends on the type of server this index resides on. ' .
        'In any case, fields of type "Fulltext" will always be fulltext-searchable.</p>'),
    );
    if ($index->getServer()) {
      $form['description']['#description'] .= '<p>' . t('Check the <a href="@server-url">' . "server's</a> service class description for details.",
          array('@server-url' => url('admin/config/search/search_api/server/' . $index->getServer()->machine_name))) . '</p>';
    }
    foreach ($fields as $key => $info) {
      $form['fields'][$key]['title']['#markup'] = String::checkPlain($info['name']);
      if (search_api_is_list_type($info['type'])) {
        $form['fields'][$key]['title']['#markup'] .= ' <sup><a href="#note-multi-valued" class="note-ref">1</a></sup>';
        $multi_valued_field_present = TRUE;
      }
      $form['fields'][$key]['machine_name']['#markup'] = String::checkPlain($key);
      if (isset($info['description'])) {
        $form['fields'][$key]['description'] = array(
          '#type' => 'value',
          '#value' => $info['description'],
        );
      }
      $form['fields'][$key]['indexed'] = array(
        '#type' => 'checkbox',
        '#default_value' => $info['indexed'],
      );
      if (empty($info['entity_type'])) {
        // Determine the correct type options (with the correct nesting level).
        $level = search_api_list_nesting_level($info['type']);
        if (empty($types[$level])) {
          $type_prefix = str_repeat('list<', $level);
          $type_suffix = str_repeat('>', $level);
          $types[$level] = array();
          foreach ($types[0] as $type => $name) {
            // We use the singular name for list types, since the user usually
            // doesn't care about the nesting level.
            $types[$level][$type_prefix . $type . $type_suffix] = $name;
          }
          foreach ($fulltext_types[0] as $type) {
            $fulltext_types[$level][] = $type_prefix . $type . $type_suffix;
          }
        }
        $css_key = '#edit-fields-' . drupal_clean_css_identifier($key);
        $form['fields'][$key]['type'] = array(
          '#type' => 'select',
          '#options' => $types[$level],
          '#default_value' => isset($info['real_type']) ? $info['real_type'] : $info['type'],
          '#states' => array(
            'visible' => array(
              $css_key . '-indexed' => array('checked' => TRUE),
            ),
          ),
        );
        $form['fields'][$key]['boost'] = array(
          '#type' => 'select',
          '#options' => $boosts,
          '#default_value' => (isset($info['boost'])) ? $info['boost'] : '',
          '#states' => array(
            'visible' => array(
              $css_key . '-indexed' => array('checked' => TRUE),
            ),
          ),
        );
        // Only add the multiple visible states if the VERSION string is >= 7.14.
        // See https://drupal.org/node/1464758.
        if (version_compare(\Drupal::VERSION, '7.14', '>=')) {
          foreach ($fulltext_types[$level] as $type) {
            $form['fields'][$key]['boost']['#states']['visible'][$css_key . '-type'][] = array('value' => $type);
          }
        }
        else {
          $form['fields'][$key]['boost']['#states']['visible'][$css_key . '-type'] = array('value' => reset($fulltext_types[$level]));
        }
      }
      else {
        // This is an entity.
        $label = $entity_types[$info['entity_type']]['label'];
        if (!isset($entity_description_added)) {
          $form['description']['#description'] .= '<p>' .
            t('Note that indexing an entity-valued field (like %field, which has type %type) directly will only index the entity ID. ' .
              'This will be used for filtering and also sorting (which might not be what you expect). ' .
              'The entity label will usually be used when displaying the field, though. ' .
              'Use the "Add related fields" option at the bottom for indexing other fields of related entities.',
              array('%field' => $info['name'], '%type' => $label)) . '</p>';
          $entity_description_added = TRUE;
        }
        $form['fields'][$key]['type'] = array(
          '#type' => 'value',
          '#value' => $info['type'],
        );
        $form['fields'][$key]['entity_type'] = array(
          '#type' => 'value',
          '#value' => $info['entity_type'],
        );
        $form['fields'][$key]['type_name'] = array(
          '#markup' => String::checkPlain($label),
        );
        $form['fields'][$key]['boost'] = array(
          '#type' => 'value',
          '#value' => $info['boost'],
        );
        $form['fields'][$key]['boost_text'] = array(
          '#markup' => '&nbsp;',
        );
      }
      if ($key == 'search_api_language') {
        // Is treated specially to always index the language.
        $form['fields'][$key]['type']['#default_value'] = 'string';
        $form['fields'][$key]['type']['#disabled'] = TRUE;
        $form['fields'][$key]['boost']['#default_value'] = '1.0';
        $form['fields'][$key]['boost']['#disabled'] = TRUE;
        $form['fields'][$key]['indexed']['#default_value'] = 1;
        $form['fields'][$key]['indexed']['#disabled'] = TRUE;
      }
    }

    if (!empty($multi_valued_field_present)) {
      $form['note']['#markup'] = '<div id="note-multi-valued"><small><sup>1</sup> ' . t('Multi-valued field') . '</small></div>';
    }

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Save changes'),
    );

    if ($additional) {
      reset($additional);
      $form['additional'] = array(
        '#type' => 'fieldset',
        '#title' => t('Add related fields'),
        '#description' => t('There are entities related to entities of this type. ' .
            'You can add their fields to the list above so they can be indexed too.') . '<br />',
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
        '#attributes' => array('class' => array('container-inline')),
        'field' => array(
          '#type' => 'select',
          '#options' => $additional,
          '#default_value' => key($additional),
        ),
        'add' => array(
          '#type' => 'submit',
          '#value' => t('Add fields'),
        ),
      );
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    // TODO: Implement submitForm() method.
  }

}