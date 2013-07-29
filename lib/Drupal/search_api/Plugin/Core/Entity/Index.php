<?php

/**
 * @file
 * Contains Drupal\search_api\Plugin\Core\Entity\Index.
 */

namespace Drupal\search_api\Plugin\Core\Entity;

use Drupal\Core\Annotation\Translation;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Annotation\EntityType;
use Drupal\Core\Entity\EntityStorageControllerInterface;
use Drupal\search_api\IndexInterface;

/**
 * Defines a search index configuration entity class.
 *
 * @EntityType(
 *   id = "search_api_index",
 *   label = @Translation("Search index"),
 *   module = "search_api",
 *   controllers = {
 *     "storage" = "Drupal\search_api\IndexStorageController",
 *     "access" = "Drupal\search_api\IndexAccessController",
 *     "render" = "Drupal\search_api\IndexRenderController",
 *     "form" = {
 *       "default" = "Drupal\search_api\IndexFormController",
 *       "delete" = "Drupal\search_api\Form\IndexDeleteForm"
 *     }
 *   },
 *   config_prefix = "search_api.index",
 *   entity_keys = {
 *     "id" = "machine_name",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "status" = "enabled"
 *   },
 *   links = {
 *     "canonical" = "/admin/config/search/search_api/index/{search_api_index}",
 *     "edit-form" = "/admin/config/search/search_api/index/{search_api_index}/edit",
 *   }
 * )
 */
class Index extends ConfigEntityBase implements IndexInterface {

  // Database values that will be set when object is loaded.

  /**
   * The machine name of the index.
   *
   * @var string
   */
  public $machine_name;

  /**
   * A name to be displayed for the index.
   *
   * @var string
   */
  public $name;

  /**
   * A Universally Unique Identifier for the index.
   *
   * @var string
   */
  public $uuid;

  /**
   * A string describing the index' use to users.
   *
   * @var string
   */
  public $description;

  /**
   * The machine_name of the server with which data should be indexed.
   *
   * @var string
   */
  public $server;

  /**
   * The type of items stored in this index.
   * Immutable.
   *
   * @var string
   */
  public $item_type;

  /**
   * An array of options for configuring this index. The layout is as follows:
   * - cron_limit: The maximum number of items to be indexed per cron batch.
   * - index_directly: Boolean setting whether entities are indexed immediately
   *   after they are created or updated.
   * - fields: An array of all indexed fields for this index. Keys are the field
   *   identifiers, the values are arrays for specifying the field settings. The
   *   structure of those arrays looks like this:
   *   - type: The type set for this field. One of the types returned by
   *     search_api_default_field_types().
   *   - real_type: (optional) If a custom data type was selected for this
   *     field, this type will be stored here, and "type" contain the fallback
   *     default data type.
   *   - boost: (optional) A boost value for terms found in this field during
   *     searches. Usually only relevant for fulltext fields. Defaults to 1.0.
   *   - entity_type (optional): If set, the type of this field is really an
   *     entity. The "type" key will then just contain the primitive data type
   *     of the ID field, meaning that servers will ignore this and merely index
   *     the entity's ID. Components displaying this field, though, are advised
   *     to use the entity label instead of the ID.
   * - additional_fields: An associative array with keys and values being the
   *   field identifiers of related entities whose fields should be displayed.
   * - data_alter_callbacks: An array of all data alterations available. Keys
   *   are the alteration identifiers, the values are arrays containing the
   *   settings for that data alteration. The inner structure looks like this:
   *   - status: Boolean indicating whether the data alteration is enabled.
   *   - weight: Used for sorting the data alterations.
   *   - settings: Alteration-specific settings, configured via the alteration's
   *     configuration form.
   * - processors: An array of all processors available for the index. The keys
   *   are the processor identifiers, the values are arrays containing the
   *   settings for that processor. The inner structure looks like this:
   *   - status: Boolean indicating whether the processor is enabled.
   *   - weight: Used for sorting the processors.
   *   - settings: Processor-specific settings, configured via the processor's
   *     configuration form.
   *
   * @var array
   */
  public $options = array();

  /**
   * A flag indicating whether this index is enabled.
   *
   * @var integer
   */
  public $enabled = 1;

  /**
   * A flag indicating whether to write to this index.
   *
   * @var integer
   */
  public $read_only = 0;

  // Cache values, set when the corresponding methods are called for the first
  // time.

  /**
   * Cached return value of datasource().
   *
   * @var \Drupal\search_api\Plugin\search_api\DatasourceInterface
   */
  protected $datasource = NULL;

  /**
   * Cached return value of server().
   *
   * @var \Drupal\search_api\ServerInterface
   */
  protected $server_object = NULL;

  /**
   * All enabled data alterations for this index.
   *
   * @var array
   */
  protected $callbacks = NULL;

  /**
   * All enabled processors for this index.
   *
   * @var array
   */
  protected $processors = NULL;

  /**
   * The properties added by data alterations on this index.
   *
   * @var array
   */
  protected $added_properties = NULL;

  /**
   * Static cache for the results of getFields().
   *
   * Can be accessed as follows: $this->fields[$only_indexed][$get_additional].
   *
   * @var array
   */
  protected $fields = array();

  /**
   * An array containing two arrays.
   *
   * At index 0, all fulltext fields of this index. At index 1, all indexed
   * fulltext fields of this index.
   *
   * @var array
   */
  protected $fulltext_fields = array();

  /**
   * {@inheritdoc}
   */
  public function id() {
    return isset($this->machine_name) ? $this->machine_name : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function uri() {
    return array(
      'path' => 'admin/config/search/search_api/index/' . $this->id(),
      'options' => array(
        'entity_type' => $this->entityType,
        'entity' => $this,
      ),
    );
  }

  /**
   * Overrides \Drupal\Core\Config\Entity\ConfigEntityBase::preSave().
   *
   * Corrects some settings with specific restrictions.
   */
  public function preSave(EntityStorageControllerInterface $storage_controller) {
    parent::preSave($storage_controller);

    if (empty($this->description)) {
      $this->description = NULL;
    }
    if (empty($this->server)) {
      $this->server = NULL;
      $this->enabled = FALSE;
    }
    // This will also throw an exception if the server doesn't exist – which is good.
    elseif (!$this->server(TRUE)->enabled) {
      $this->enabled = FALSE;
    }
  }

  /**
   * Overrides \Drupal\Core\Entity\Entity::postSave().
   *
   * Executes necessary tasks for newly created indexes.
   */
  public function postSave(EntityStorageControllerInterface $storage_controller, $update = TRUE) {
    parent::postSave($storage_controller, $update);

    if (!$update) {
      if ($this->enabled) {
        $this->queueItems();
      }
      $server = $this->server();
      if ($server) {
        // Tell the server about the new index.
        if ($server->enabled) {
          $server->addIndex($this);
        }
        else {
          $tasks = \Drupal::state()->get('search_api_tasks') ?: array();
          // When we add or remove an index, we can ignore all other tasks.
          $tasks[$server->machine_name][$this->machine_name] = array('add');
          \Drupal::state()->set('search_api_tasks', $tasks);
        }
      }
    }
    else {
      $this->postUpdate();
    }
  }

  /**
   * Handles updates of this index.
   *
   * Called from postSave() if it was an update.
   */
  protected function postUpdate() {
    // Reset the index's internal property cache to correctly incorporate new
    // settings.
    $this->resetCaches();

    // If the server was changed, we have to call the appropriate service class
    // hook methods.
    if ($this->server != $this->original->server) {
      // Server changed - inform old and new ones.
      if ($this->original->server) {
        $old_server = search_api_server_load($this->original->server);
        // The server might have changed because the old one was deleted:
        if ($old_server) {
          if ($old_server->enabled) {
            $old_server->removeIndex($this);
          }
          else {
            $tasks = \Drupal::state()->get('search_api_tasks') ? : array();
            // When we add or remove an index, we can ignore all other tasks.
            $tasks[$old_server->machine_name][$this->machine_name] = array('remove');
            \Drupal::state()->set('search_api_tasks', $tasks);
          }
        }
      }

      if ($this->server) {
        $new_server = $this->server(TRUE);
        // If the server is enabled, we call addIndex(); otherwise, we save the task.
        if ($new_server->enabled) {
          $new_server->addIndex($this);
        }
        else {
          $tasks = \Drupal::state()->get('search_api_tasks') ? : array();
          // When we add or remove an index, we can ignore all other tasks.
          $tasks[$new_server->machine_name][$this->machine_name] = array('add');
          \Drupal::state()->set('search_api_tasks', $tasks);
          unset($new_server);
        }
      }

      // We also have to re-index all content.
      _search_api_index_reindex($this);
    }

    // If the fields were changed, call the appropriate service class hook method
    // and re-index the content, if necessary. Also, clear the fields cache.
    $old_fields = $this->original->options + array('fields' => array());
    $old_fields = $old_fields['fields'];
    $new_fields = $this->options + array('fields' => array());
    $new_fields = $new_fields['fields'];
    if ($old_fields != $new_fields) {
      cache_clear_all($this->getCacheId(), 'cache', TRUE);
      if ($this->server && $this->server()->fieldsUpdated($this)) {
        _search_api_index_reindex($this);
      }
    }

    // If additional fields changed, clear the index's specific cache which
    // includes them.
    $old_additional = $this->original->options + array('additional fields' => array());
    $old_additional = $old_additional['additional fields'];
    $new_additional = $this->options + array('additional fields' => array());
    $new_additional = $new_additional['additional fields'];
    if ($old_additional != $new_additional) {
      cache_clear_all($this->getCacheId() . '-0-1', 'cache');
    }

    // If the index's enabled or read-only status is being changed, queue or
    // dequeue items for indexing.
    if (!$this->read_only && $this->enabled != $this->original->enabled) {
      if ($this->enabled) {
        $this->queueItems();
      }
      else {
        $this->dequeueItems();
      }
    }
    elseif ($this->read_only != $this->original->read_only) {
      if ($this->read_only) {
        $this->dequeueItems();
      }
      else {
        $this->queueItems();
      }
    }

    // If the cron batch size changed, empty the cron queue for this index.
    $old_cron = $this->original->options + array('cron_limit' => NULL);
    $old_cron = $old_cron['cron_limit'];
    $new_cron = $this->options + array('cron_limit' => NULL);
    $new_cron = $new_cron['cron_limit'];
    if ($old_cron !== $new_cron) {
      _search_api_empty_cron_queue($this, TRUE);
    }
  }

  /**
   * Overrides \Drupal\Core\Entity\Entity::postDelete().
   *
   * Executes necessary tasks when the index is removed from the database.
   */
  public static function postDelete(EntityStorageControllerInterface $storage_controller, array $entities) {
    foreach ($entities as $index) {
      if ($server = $index->server()) {
        if ($server->enabled) {
          $server->removeIndex($index);
        }
        // Once the index is deleted, servers won't be able to tell whether it was
        // read-only. Therefore, we prefer to err on the safe side and don't call
        // the server method at all if the index is read-only and the server
        // currently disabled.
        elseif (empty($index->read_only)) {
          $tasks = \Drupal::state()->get('search_api_tasks') ?: array();
          $tasks[$server->machine_name][$index->machine_name] = array('remove');
          \Drupal::state()->set('search_api_tasks', $tasks);
        }
      }

      // Stop tracking entities for indexing.
      $index->dequeueItems();

      // Delete index's cache.
      cache_clear_all($index->getCacheId(''), 'cache', TRUE);
    }
  }

  /**
   * Puts all of this index's items into the indexing queue.
   *
   * Called when the index is created or enabled.
   */
  public function queueItems() {
    if (!$this->read_only) {
      $this->datasource()->startTracking(array($this));
    }
  }

  /**
   * Clear this index's indexing queue.
   *
   * Called when the index is disabled or deleted.
   */
  public function dequeueItems() {
    $this->datasource()->stopTracking(array($this));
    _search_api_empty_cron_queue($this);
  }

  /**
   * Schedules this search index for re-indexing.
   *
   * @return bool
   *   TRUE on success, FALSE on failure.
   */
  public function reindex() {
    if (!$this->server || $this->read_only) {
      return TRUE;
    }
    _search_api_index_reindex($this);
    \Drupal::moduleHandler()->invokeAll('search_api_index_reindex', $this, FALSE);
    return TRUE;
  }

  /**
   * Clears this search index and schedules all of its items for re-indexing.
   *
   * @return bool
   *   TRUE on success, FALSE on failure.
   */
  public function clear() {
    if (!$this->server || $this->read_only) {
      return TRUE;
    }

    $server = $this->server();
    if ($server->enabled) {
      $server->deleteItems('all', $this);
    }
    else {
      $tasks = \Drupal::state()->get('search_api_tasks') ?: array();
      // If the index was cleared or newly added since the server was last
      // enabled, we don't need to do anything.
      if (!isset($tasks[$server->machine_name][$this->machine_name])
          || (array_search('add', $tasks[$server->machine_name][$this->machine_name]) === FALSE
              && array_search('clear', $tasks[$server->machine_name][$this->machine_name]) === FALSE)) {
        $tasks[$server->machine_name][$this->machine_name][] = 'clear';
        \Drupal::state()->set('search_api_tasks', $tasks);
      }
    }

    _search_api_index_reindex($this);
    module_invoke_all('search_api_index_reindex', $this, TRUE);
    return TRUE;
  }

  /**
   * Magic method for determining which fields should be serialized.
   *
   * Don't serialize properties that are basically only caches.
   *
   * @return array
   *   An array of properties to be serialized.
   */
  public function __sleep() {
    $ret = get_object_vars($this);
    unset($ret['server_object'], $ret['datasource'], $ret['processors'], $ret['added_properties'], $ret['fulltext_fields']);
    return array_keys($ret);
  }

  /**
   * Get the controller object of the data source used by this index.
   *
   * @throws SearchApiException
   *   If the specified item type or data source doesn't exist or is invalid.
   *
   * @return DatasourceInterface
   *   The data source controller for this index.
   */
  public function datasource() {
    if (!isset($this->datasource)) {
      $this->datasource = search_api_get_datasource_controller($this->item_type);
    }
    return $this->datasource;
  }

  /**
   * Get the entity type of items in this index.
   *
   * @return string|null
   *   An entity type string if the items in this index are entities; NULL
   *   otherwise.
   */
  public function getEntityType() {
    return $this->datasource()->getEntityType();
  }

  /**
   * Get the server this index lies on.
   *
   * @param $reset
   *   Whether to reset the internal cache. Set to TRUE when the index' $server
   *   property has just changed.
   *
   * @throws SearchApiException
   *   If $this->server is set, but no server with that machine name exists.
   *
   * @return Server
   *   The server associated with this index, or NULL if this index currently
   *   doesn't lie on a server.
   */
  public function server($reset = FALSE) {
    if (!isset($this->server_object) || $reset) {
      $this->server_object = $this->server ? search_api_server_load($this->server) : FALSE;
      if ($this->server && !$this->server_object) {
        throw new SearchApiException(t('Unknown server @server specified for index @name.', array('@server' => $this->server, '@name' => $this->machine_name)));
      }
    }
    return $this->server_object ? $this->server_object : NULL;
  }

  /**
   * Create a query object for this index.
   *
   * @param $options
   *   Associative array of options configuring this query. See
   *   QueryInterface::__construct().
   *
   * @throws SearchApiException
   *   If the index is currently disabled.
   *
   * @return QueryInterface
   *   A query object for searching this index.
   */
  public function query($options = array()) {
    if (!$this->enabled) {
      throw new SearchApiException(t('Cannot search on a disabled index.'));
    }
    return $this->server()->query($this, $options);
  }


  /**
   * Indexes items on this index. Will return an array of IDs of items that
   * should be marked as indexed – i.e., items that were either rejected by a
   * data-alter callback or were successfully indexed.
   *
   * @param array $items
   *   An array of items to index.
   *
   * @return array
   *   An array of the IDs of all items that should be marked as indexed.
   */
  public function index(array $items) {
    if ($this->read_only) {
      return array();
    }
    if (!$this->enabled) {
      throw new SearchApiException(t("Couldn't index values on '@name' index (index is disabled)", array('@name' => $this->name)));
    }
    if (empty($this->options['fields'])) {
      throw new SearchApiException(t("Couldn't index values on '@name' index (no fields selected)", array('@name' => $this->name)));
    }
    $fields = $this->options['fields'];
    $custom_type_fields = array();
    foreach ($fields as $field => $info) {
      if (isset($info['real_type'])) {
        $custom_type = search_api_extract_inner_type($info['real_type']);
        if ($this->server()->supportsFeature('search_api_data_type_' . $custom_type)) {
          $fields[$field]['type'] = $info['real_type'];
          $custom_type_fields[$custom_type][$field] = search_api_list_nesting_level($info['real_type']);
        }
      }
    }
    if (empty($fields)) {
      throw new SearchApiException(t("Couldn't index values on '@name' index (no fields selected)", array('@name' => $this->name)));
    }

    // Mark all items that are rejected as indexed.
    $ret = array_keys($items);
    drupal_alter('search_api_index_items', $items, $this);
    if ($items) {
      $this->dataAlter($items);
    }
    $ret = array_diff($ret, array_keys($items));

    // Items that are rejected should also be deleted from the server.
    if ($ret) {
      $this->server()->deleteItems($ret, $this);
    }
    if (!$items) {
      return $ret;
    }

    $data = array();
    foreach ($items as $id => $item) {
      $data[$id] = search_api_extract_fields($this->entityWrapper($item), $fields);
      unset($items[$id]);
      foreach ($custom_type_fields as $type => $type_fields) {
        $info = search_api_get_data_type_info($type);
        if (isset($info['conversion callback']) && is_callable($info['conversion callback'])) {
          $callback = $info['conversion callback'];
          foreach ($type_fields as $field => $nesting_level) {
            if (isset($data[$id][$field]['value'])) {
              $value = $data[$id][$field]['value'];
              $original_type = $data[$id][$field]['original_type'];
              $data[$id][$field]['value'] = _search_api_convert_custom_type($callback, $value, $original_type, $type, $nesting_level);
            }
          }
        }
      }
    }

    $this->preprocessIndexItems($data);

    return array_merge($ret, $this->server()->indexItems($this, $data));
  }

  /**
   * Calls data alteration hooks for a set of items, according to the index
   * options.
   *
   * @param array $items
   *   An array of items to be altered.
   *
   * @return Index
   *   The called object.
   */
  public function dataAlter(array &$items) {
    // First, execute our own search_api_language data alteration.
    foreach ($items as &$item) {
      $item->search_api_language = isset($item->language) ? $item->language : LANGUAGE_NONE;
    }

    foreach ($this->getAlterCallbacks() as $callback) {
      $callback->alterItems($items);
    }

    return $this;
  }

  /**
   * Property info alter callback that adds the infos of the properties added by
   * data alter callbacks.
   *
   * @param EntityMetadataWrapper $wrapper
   *   The wrapped data.
   * @param $property_info
   *   The original property info.
   *
   * @return array
   *   The altered property info.
   */
  public function propertyInfoAlter(EntityMetadataWrapper $wrapper, array $property_info) {
    if (entity_get_property_info($wrapper->type())) {
      // Overwrite the existing properties with the list of properties including
      // all fields regardless of the used bundle.
      $property_info['properties'] = entity_get_all_property_info($wrapper->type());
    }

    if (!isset($this->added_properties)) {
      $this->added_properties = array(
        'search_api_language' => array(
          'label' => t('Item language'),
          'description' => t("A field added by the search framework to let components determine an item's language. Is always indexed."),
          'type' => 'token',
          'options list' => 'entity_metadata_language_list',
        ),
      );
      // We use the reverse order here so the hierarchy for overwriting property
      // infos is the same as for actually overwriting the properties.
      foreach (array_reverse($this->getAlterCallbacks()) as $callback) {
        $props = $callback->propertyInfo();
        if ($props) {
          $this->added_properties += $props;
        }
      }
    }
    // Let fields added by data-alter callbacks override default fields.
    $property_info['properties'] = array_merge($property_info['properties'], $this->added_properties);

    return $property_info;
  }

  /**
   * Loads all enabled data alterations for this index in proper order.
   *
   * @return array
   *   All enabled callbacks for this index, as ProcessorInterface
   *   objects.
   */
  public function getAlterCallbacks() {
    if (isset($this->callbacks)) {
      return $this->callbacks;
    }

    $this->callbacks = array();
    if (empty($this->options['data_alter_callbacks'])) {
      return $this->callbacks;
    }
    $callback_settings = $this->options['data_alter_callbacks'];
    $infos = search_api_get_alter_callbacks();

    foreach ($callback_settings as $id => $settings) {
      if (empty($settings['status'])) {
        continue;
      }
      if (empty($infos[$id]) || !class_exists($infos[$id]['class'])) {
        watchdog('search_api', t('Undefined data alteration @class specified in index @name', array('@class' => $id, '@name' => $this->name)), NULL, WATCHDOG_WARNING);
        continue;
      }
      $class = $infos[$id]['class'];
      $callback = new $class($this, empty($settings['settings']) ? array() : $settings['settings']);
      if (!($callback instanceof ProcessorInterface)) {
        watchdog('search_api', t('Unknown callback class @class specified for data alteration @name', array('@class' => $class, '@name' => $id)), NULL, WATCHDOG_WARNING);
        continue;
      }

      $this->callbacks[$id] = $callback;
    }
    return $this->callbacks;
  }

  /**
   * Loads all enabled processors for this index in proper order.
   *
   * @return array
   *   All enabled processors for this index, as ProcessorInterface
   *   objects.
   */
  public function getProcessors() {
    if (isset($this->processors)) {
      return $this->processors;
    }

    $this->processors = array();
    if (empty($this->options['processors'])) {
      return $this->processors;
    }
    $processor_settings = $this->options['processors'];
    $infos = search_api_get_processors();

    foreach ($processor_settings as $id => $settings) {
      if (empty($settings['status'])) {
        continue;
      }
      if (empty($infos[$id]) || !class_exists($infos[$id]['class'])) {
        watchdog('search_api', t('Undefined processor @class specified in index @name', array('@class' => $id, '@name' => $this->name)), NULL, WATCHDOG_WARNING);
        continue;
      }
      $class = $infos[$id]['class'];
      $processor = new $class($this, isset($settings['settings']) ? $settings['settings'] : array());
      if (!($processor instanceof ProcessorInterface)) {
        watchdog('search_api', t('Unknown processor class @class specified for processor @name', array('@class' => $class, '@name' => $id)), NULL, WATCHDOG_WARNING);
        continue;
      }

      $this->processors[$id] = $processor;
    }
    return $this->processors;
  }

  /**
   * Preprocess data items for indexing. Data added by data alter callbacks will
   * be available on the items.
   *
   * Typically, a preprocessor will execute its preprocessing (e.g. stemming,
   * n-grams, word splitting, stripping stop words, etc.) only on the items'
   * fulltext fields. Other fields should usually be left untouched.
   *
   * @param array $items
   *   An array of items to be preprocessed for indexing.
   *
   * @return Index
   *   The called object.
   */
  public function preprocessIndexItems(array &$items) {
    foreach ($this->getProcessors() as $processor) {
      $processor->preprocessIndexItems($items);
    }
    return $this;
  }


  /**
   * Preprocess a search query.
   *
   * The same applies as when preprocessing indexed items: typically, only the
   * fulltext search keys should be processed, queries on specific fields should
   * usually not be altered.
   *
   * @param DefaultQuery $query
   *   The object representing the query to be executed.
   *
   * @return Index
   *   The called object.
   */
  public function preprocessSearchQuery(DefaultQuery $query) {
    foreach ($this->getProcessors() as $processor) {
      $processor->preprocessSearchQuery($query);
    }
    return $this;
  }

  /**
   * Postprocess search results before display.
   *
   * If a class is used for both pre- and post-processing a search query, the
   * same object will be used for both calls (so preserving some data or state
   * locally is possible).
   *
   * @param array $response
   *   An array containing the search results. See
   *   ServiceInterface->search() for the detailed format.
   * @param DefaultQuery $query
   *   The object representing the executed query.
   *
   * @return Index
   *   The called object.
   */
  public function postprocessSearchResults(array &$response, DefaultQuery $query) {
    // Postprocessing is done in exactly the opposite direction than preprocessing.
    foreach (array_reverse($this->getProcessors()) as $processor) {
      $processor->postprocessSearchResults($response, $query);
    }
    return $this;
  }

  /**
   * Returns a list of all known fields for this index.
   *
   * @param $only_indexed (optional)
   *   Return only indexed fields, not all known fields. Defaults to TRUE.
   * @param $get_additional (optional)
   *   Return not only known/indexed fields, but also related entities whose
   *   fields could additionally be added to the index.
   *
   * @return array
   *   An array of all known fields for this index. Keys are the field
   *   identifiers, the values are arrays for specifying the field settings. The
   *   structure of those arrays looks like this:
   *   - name: The human-readable name for the field.
   *   - description: A description of the field, if available.
   *   - indexed: Boolean indicating whether the field is indexed or not.
   *   - type: The type set for this field. One of the types returned by
   *     search_api_default_field_types().
   *   - real_type: (optional) If a custom data type was selected for this
   *     field, this type will be stored here, and "type" contain the fallback
   *     default data type.
   *   - boost: A boost value for terms found in this field during searches.
   *     Usually only relevant for fulltext fields.
   *   - entity_type (optional): If set, the type of this field is really an
   *     entity. The "type" key will then contain "integer", meaning that
   *     servers will ignore this and merely index the entity's ID. Components
   *     displaying this field, though, are advised to use the entity label
   *     instead of the ID.
   *   If $get_additional is TRUE, this array is encapsulated in another
   *   associative array, which contains the above array under the "fields" key,
   *   and a list of related entities (field keys mapped to names) under the
   *   "additional fields" key.
   */
  public function getFields($only_indexed = TRUE, $get_additional = FALSE) {
    $only_indexed = $only_indexed ? 1 : 0;
    $get_additional = $get_additional ? 1 : 0;

    // First, try the static cache and the persistent cache bin.
    if (empty($this->fields[$only_indexed][$get_additional])) {
      $cid = $this->getCacheId() . "-$only_indexed-$get_additional";
      $cache = cache_get($cid);
      if ($cache) {
        $this->fields[$only_indexed][$get_additional] = $cache->data;
      }
    }

    // Otherwise, we have to compute the result.
    if (empty($this->fields[$only_indexed][$get_additional])) {
      $fields = empty($this->options['fields']) ? array() : $this->options['fields'];
      $wrapper = $this->entityWrapper();
      $additional = array();
      $entity_types = entity_get_info();

      // First we need all already added prefixes.
      $added = ($only_indexed || empty($this->options['additional fields'])) ? array() : $this->options['additional fields'];
      foreach (array_keys($fields) as $key) {
        $len = strlen($key) + 1;
        $pos = $len;
        // The third parameter ($offset) to strrpos has rather weird behaviour,
        // necessitating this rather awkward code. It will iterate over all
        // prefixes of each field, beginning with the longest, adding all of them
        // to $added until one is encountered that was already added (which means
        // all shorter ones will have already been added, too).
        while ($pos = strrpos($key, ':', $pos - $len)) {
          $prefix = substr($key, 0, $pos);
          if (isset($added[$prefix])) {
            break;
          }
          $added[$prefix] = $prefix;
        }
      }

      // Then we walk through all properties and look if they are already
      // contained in one of the arrays.
      // Since this uses an iterative instead of a recursive approach, it is a bit
      // complicated, with three arrays tracking the current depth.

      // A wrapper for a specific field name prefix, e.g. 'user:' mapped to the user wrapper
      $wrappers = array('' => $wrapper);
      // Display names for the prefixes
      $prefix_names = array('' => '');
        // The list nesting level for entities with a certain prefix
      $nesting_levels = array('' => 0);

      $types = search_api_default_field_types();
      $flat = array();
      while ($wrappers) {
        foreach ($wrappers as $prefix => $wrapper) {
          $prefix_name = $prefix_names[$prefix];
          // Deal with lists of entities.
          $nesting_level = $nesting_levels[$prefix];
          $type_prefix = str_repeat('list<', $nesting_level);
          $type_suffix = str_repeat('>', $nesting_level);
          if ($nesting_level) {
            $info = $wrapper->info();
            // The real nesting level of the wrapper, not the accumulated one.
            $level = search_api_list_nesting_level($info['type']);
            for ($i = 0; $i < $level; ++$i) {
              $wrapper = $wrapper[0];
            }
          }
          // Now look at all properties.
          foreach ($wrapper as $property => $value) {
            $info = $value->info();
            // We hide the complexity of multi-valued types from the user here.
            $type = search_api_extract_inner_type($info['type']);
            // Treat Entity API type "token" as our "string" type.
            // Also let text fields with limited options be of type "string" by default.
            if ($type == 'token' || ($type == 'text' && !empty($info['options list']))) {
              // Inner type is changed to "string".
              $type = 'string';
              // Set the field type accordingly.
              $info['type'] = search_api_nest_type('string', $info['type']);
            }
            $info['type'] = $type_prefix . $info['type'] . $type_suffix;
            $key = $prefix . $property;
            if ((isset($types[$type]) || isset($entity_types[$type])) && (!$only_indexed || !empty($fields[$key]))) {
              if (!empty($fields[$key])) {
                // This field is already known in the index configuration.
                $flat[$key] = $fields[$key] + array(
                  'name' => $prefix_name . $info['label'],
                  'description' => empty($info['description']) ? NULL : $info['description'],
                  'boost' => '1.0',
                  'indexed' => TRUE,
                );
                // Update the type and its nesting level for non-entity properties.
                if (!isset($entity_types[$type])) {
                  $flat[$key]['type'] = search_api_nest_type(search_api_extract_inner_type($flat[$key]['type']), $info['type']);
                  if (isset($flat[$key]['real_type'])) {
                    $real_type = search_api_extract_inner_type($flat[$key]['real_type']);
                    $flat[$key]['real_type'] = search_api_nest_type($real_type, $info['type']);
                  }
                }
              }
              else {
                $flat[$key] = array(
                  'name'    => $prefix_name . $info['label'],
                  'description' => empty($info['description']) ? NULL : $info['description'],
                  'type'    => $info['type'],
                  'boost' => '1.0',
                  'indexed' => FALSE,
                );
              }
              if (isset($entity_types[$type])) {
                $base_type = isset($entity_types[$type]['entity keys']['name']) ? 'string' : 'integer';
                $flat[$key]['type'] = search_api_nest_type($base_type, $info['type']);
                $flat[$key]['entity_type'] = $type;
              }
            }
            if (empty($types[$type])) {
              if (isset($added[$key])) {
                // Visit this entity/struct in a later iteration.
                $wrappers[$key . ':'] = $value;
                $prefix_names[$key . ':'] = $prefix_name . $info['label'] . ' » ';
                $nesting_levels[$key . ':'] = search_api_list_nesting_level($info['type']);
              }
              else {
                $name = $prefix_name . $info['label'];
                // Add machine names to discern fields with identical labels.
                if (isset($used_names[$name])) {
                  if ($used_names[$name] !== FALSE) {
                    $additional[$used_names[$name]] .= ' [' . $used_names[$name] . ']';
                    $used_names[$name] = FALSE;
                  }
                  $name .= ' [' . $key . ']';
                }
                $additional[$key] = $name;
                $used_names[$name] = $key;
              }
            }
          }
          unset($wrappers[$prefix]);
        }
      }

      if (!$get_additional) {
        $this->fields[$only_indexed][$get_additional] = $flat;
      }
      else {
        $options = array();
        $options['fields'] = $flat;
        $options['additional fields'] = $additional;
        $this->fields[$only_indexed][$get_additional] =  $options;
      }
      cache_set($cid, $this->fields[$only_indexed][$get_additional]);
    }

    return $this->fields[$only_indexed][$get_additional];
  }

  /**
   * Convenience method for getting all of this index's fulltext fields.
   *
   * @param boolean $only_indexed
   *   If set to TRUE, only the indexed fulltext fields will be returned.
   *
   * @return array
   *   An array containing all (or all indexed) fulltext fields defined for this
   *   index.
   */
  public function getFulltextFields($only_indexed = TRUE) {
    $i = $only_indexed ? 1 : 0;
    if (!isset($this->fulltext_fields[$i])) {
      $this->fulltext_fields[$i] = array();
      $fields = $only_indexed ? $this->options['fields'] : $this->getFields(FALSE);
      foreach ($fields as $key => $field) {
        if (search_api_is_text_type($field['type'])) {
          $this->fulltext_fields[$i][] = $key;
        }
      }
    }
    return $this->fulltext_fields[$i];
  }

  /**
   * Get the cache ID prefix used for this index's caches.
   *
   * @param $type
   *   The type of cache. Currently only "fields" is used.
   *
   * @return
   *   The cache ID (prefix) for this index's caches.
   */
  public function getCacheId($type = 'fields') {
    return 'search_api:index-' . $this->machine_name . '--' . $type;
  }

  /**
   * Helper function for creating an entity metadata wrapper appropriate for
   * this index.
   *
   * @param $item
   *   Unless NULL, an item of this index's item type which should be wrapped.
   * @param $alter
   *   Whether to apply the index's active data alterations on the property
   *   information used. To also apply the data alteration to the wrapped item,
   *   execute Index::dataAlter() on it before calling this method.
   *
   * @return EntityMetadataWrapper
   *   A wrapper for the item type of this index, optionally loaded with the
   *   given data and having additional fields according to the data alterations
   *   of this index.
   */
  public function entityWrapper($item = NULL, $alter = TRUE) {
    $info['property info alter'] = $alter ? array($this, 'propertyInfoAlter') : '_search_api_wrapper_add_all_properties';
    $info['property defaults']['property info alter'] = '_search_api_wrapper_add_all_properties';
    return $this->datasource()->getMetadataWrapper($item, $info);
  }

  /**
   * Helper method to load items from the type lying on this index.
   *
   * @param array $ids
   *   The IDs of the items to load.
   *
   * @return array
   *   The requested items, as loaded by the data source.
   *
   * @see DatasourceInterface::loadItems()
   */
  public function loadItems(array $ids) {
    return $this->datasource()->loadItems($ids);
  }

  /**
   * Reset internal static caches.
   *
   * Should be used when things like fields or data alterations change to avoid
   * using stale data.
   */
  public function resetCaches() {
    $this->datasource = NULL;
    $this->server_object = NULL;
    $this->callbacks = NULL;
    $this->processors = NULL;
    $this->added_properties = NULL;
    $this->fields = array();
    $this->fulltext_fields = array();
  }

}
