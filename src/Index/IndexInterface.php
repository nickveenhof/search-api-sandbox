<?php

/**
 * @file
 * Contains \Drupal\search_api\Index\IndexInterface.
 */

namespace Drupal\search_api\Index;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api\Server\ServerInterface;

/**
 * Defines the interface for index entities.
 */
interface IndexInterface extends ConfigEntityInterface {

  /**
   * String used to separate a datasource prefix from the rest of an identifier.
   *
   * Internal field identifiers of datasource-dependent fields in the Search API
   * consist of two parts: the ID of the datasource to which the field belongs;
   * and the property path to the field, with properties separated by colons.
   * The two parts are concatenated using this character as a separator to form
   * the complete field identifier. (In the case of datasource-independent
   * fields, the identifier doesn't contain the separator.)
   *
   * Likewise, internal item IDs consist of the datasource ID and the item ID
   * within that datasource, separated by this character.
   */
  const DATASOURCE_ID_SEPARATOR = '|';

  /**
   * Retrieves the index description.
   *
   * @return string
   *   The description of this index.
   */
  public function getDescription();

  /**
   * Determines whether this index is read-only.
   *
   * @return bool
   *   TRUE if this index is read-only, otherwise FALSE.
   */
  public function isReadOnly();

  /**
   * Gets the cache ID prefix used for this index's caches.
   *
   * @param $type
   *   The type of cache. Currently only "fields" is used.
   *
   * @return
   *   The cache ID (prefix) for this index's caches.
   */
  public function getCacheId($type = 'fields');

  /**
   * Retrieves an option.
   *
   * @param string $name
   *   The name of an option.
   * @param mixed $default
   *   The value return if the option wasn't set.
   *
   * @return mixed
   *   The value of the option.
   *
   * @see getOptions()
   */
  public function getOption($name, $default = NULL);

  /**
   * Retrieves an array of all options.
   *
   * The following options are known:
   * - cron_limit: The maximum number of items to be indexed per cron batch.
   * - index_directly: Boolean setting whether entities are indexed immediately
   *   after they are created or updated.
   * - fields: An array of all indexed fields for this index. Keys are the field
   *   identifiers, the values are arrays for specifying the field settings. The
   *   structure of those arrays looks like this:
   *   - type: The type set for this field. One of the types returned by
   *     \Drupal\search_api\Utility::getDefaultDataTypes().
   *   - boost: (optional) A boost value for terms found in this field during
   *     searches. Usually only relevant for fulltext fields. Defaults to 1.0.
   * - additional fields: An associative array with keys and values being the
   *   field identifiers of related entities whose fields should be displayed.
   * - processors: An array of all processors available for the index. The keys
   *   are the processor identifiers, the values are arrays containing the
   *   settings for that processor. The inner structure looks like this:
   *   - status: Boolean indicating whether the processor is enabled.
   *   - weight: Used for sorting the processors.
   *   - settings: Processor-specific settings, configured via the processor's
   *     configuration form.
   *
   * @return array
   *   An associative array of option values, keyed by the option name.
   */
  public function getOptions();

  /**
   * Sets the index's options.
   *
   * @param array $options
   *   The new index options.
   *
   * @return self
   *   The invoked object.
   */
  public function setOptions(array $options);

  /**
   * Sets an option.
   *
   * @param string $name
   *   The name of an option.
   * @param $option
   *   The new option.
   *
   * @return self
   *   The invoked object.
   */
  public function setOption($name, $option);

  /**
   * Retrieves the IDs of all datasources enabled for this index.
   *
   * @return string[]
   *   The IDs of the datasource plugins used by this index.
   */
  public function getDatasourceIds();

  /**
   * Determines whether the given datasource ID is valid for this index.
   *
   * The general contract of this method is that it should return TRUE if, and
   * only if, a call to getDatasource() with the same ID would not result in an
   * exception.
   *
   * @param string $datasource_id
   *   A datasource plugin ID.
   *
   * @return bool
   *   TRUE if the datasource with the given ID is enabled for this index and
   *   can be loaded. FALSE otherwise.
   */
  public function isValidDatasource($datasource_id);

  /**
   * Retrieves a specific datasource plugin for this index.
   *
   * @param string $datasource_id
   *   The ID of the datasource plugin to return.
   *
   * @return \Drupal\search_api\Datasource\DatasourceInterface
   *   The datasource plugin with the given ID.
   *
   * @throws \Drupal\search_api\Exception\SearchApiException
   *   If the specified datasource isn't enabled for this index, or couldn't be
   *   loaded.
   */
  public function getDatasource($datasource_id);

  /**
   * Retrieves this index's datasource plugins.
   *
   * @return \Drupal\search_api\Datasource\DatasourceInterface[]
   *   The datasource plugins used by this index, keyed by plugin ID.
   */
  public function getDatasources();

  /**
   * Determine whether the tracker is valid.
   *
   * @return bool
   *   TRUE if the tracker is valid, otherwise FALSE.
   */
  public function hasValidTracker();

  /**
   * Retrieves the tracker plugin's ID.
   *
   * @return string
   *   The ID of the tracker plugin used by this index.
   */
  public function getTrackerId();

  /**
   * Retrieves the tracker plugin.
   *
   * @return \Drupal\search_api\Tracker\TrackerInterface
   *   An instance of TrackerInterface.
   *
   * @throws \Drupal\search_api\Exception\SearchApiException
   *   If the tracker couldn't be instantiated.
   */
  public function getTracker();

  /**
   * Determines whether this index is lying on a valid server.
   *
   * @return bool
   *   TRUE if the index's server is set and valid, otherwise FALSE.
   */
  public function hasValidServer();

  /**
   * Checks if this index has an enabled server.
   *
   * @return bool
   *   TRUE if this index is attached to a valid, enabled server.
   */
  public function isServerEnabled();

  /**
   * Retrieves the ID of the server the index is attached to.
   *
   * @return string|null
   *   The index's server's machine name, or NULL if the index doesn't have a
   *   server.
   */
  public function getServerId();

  /**
   * Retrieves the server the index is attached to.
   *
   * @return \Drupal\search_api\Server\ServerInterface|null
   *   An instance of ServerInterface, or NULL if the index doesn't have a
   *   server.
   *
   * @throws \Drupal\search_api\Exception\SearchApiException
   *   If the server couldn't be loaded.
   */
  public function getServer();

  /**
   * Sets the server the index is attached to
   *
   * @param \Drupal\search_api\Server\ServerInterface|null $server
   *   The server to move this index to, or NULL.
   */
  public function setServer(ServerInterface $server = NULL);

  /**
   * Loads all enabled processors for this index in proper order.
   *
   * @param bool $all
   *   Also include non-active processors
   * @param string $sortBy
   *
   * @return \Drupal\search_api\Processor\ProcessorInterface[]
   *   All enabled processors for this index, as
   *   \Drupal\search_api\Plugin\search_api\ProcessorInterface objects.
   */
  public function getProcessors($all = FALSE, $sortBy = 'weight');

  /**
   * Preprocesses data items for indexing.
   *
   * Lets all enabled processors for this index preprocess the indexed data.
   *
   * @param array $items
   *   An array of items to be preprocessed for indexing.
   */
  public function preprocessIndexItems(array &$items);

  /**
   * Preprocesses a search query.
   *
   * Lets all enabled processors for this index preprocess the search query.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The object representing the query to be executed.
   */
  public function preprocessSearchQuery(QueryInterface $query);

  /**
   * Postprocesses search results before display.
   *
   * If a class is used for both pre- and post-processing a search query, the
   * same object will be used for both calls (so preserving some data or state
   * locally is possible).
   *
   * @param \Drupal\search_api\Query\ResultSetInterface $results
   *   The search results.
   */
  public function postprocessSearchResults(ResultSetInterface $results);

  /**
   * Returns a list of all known fields of this index.
   *
   * @param bool $only_indexed
   *   (optional) Return only indexed fields, not all known fields.
   *
   * @return \Drupal\search_api\Item\FieldInterface[]
   *   An array of all known (or indexed, if $only_indexed is TRUE) fields for
   *   this index, keyed by field identifier.
   */
  public function getFields($only_indexed = TRUE);

  /**
   * Returns a list of all known fields of a specific datasource.
   *
   * @param string|null $datasource_id
   *   The ID of the datasource whose fields should be retrieved, or NULL to
   *   retrieve all datasource-independent fields.
   * @param bool $only_indexed
   *   (optional) Return only indexed fields, not all known fields.
   *
   * @return \Drupal\search_api\Item\FieldInterface[]
   *   An array of all known (or indexed, if $only_indexed is TRUE) fields for
   *   the given datasource, keyed by field identifier.
   */
  public function getFieldsByDatasource($datasource_id, $only_indexed = TRUE);

  /**
   * Retrieves a list of complex fields on this index.
   *
   * The related properties of these fields can be added to the index.
   *
   * @return \Drupal\search_api\Item\AdditionalFieldInterface[]
   *   The additional fields available for the index, keyed by field IDs.
   */
  public function getAdditionalFields();

  /**
   * Retrieves a list of complex fields from a specific datasource.
   *
   * The related properties of these fields can be added to the index.
   *
   * @param string|null $datasource_id
   *   The ID of the datasource whose additional fields should be retrieved, or
   *   NULL to retrieve all datasource-independent additional fields.
   *
   * @return \Drupal\search_api\Item\AdditionalFieldInterface[]
   *   The additional fields available for the datasource, keyed by field IDs.
   */
  public function getAdditionalFieldsByDatasource($datasource_id);

  /**
   * Convenience method for getting all of this index's fulltext fields.
   *
   * @param bool $only_indexed
   *   (optional) If set to TRUE, only the indexed fulltext fields will be
   *   returned. Otherwise, this method will return all available fulltext
   *   fields.
   *
   * @return string[]
   *   An array containing the IDs of all (or all indexed) fulltext fields
   *   defined for this index.
   */
  public function getFulltextFields($only_indexed = TRUE);

  /**
   * Retrieves the properties available for this index.
   *
   * @param string $datasource_id
   *   The ID of the datasource for which the properties should be retrieved.
   * @param bool $alter
   *   (optional) Whether to pass the property definitions to the index's
   *   enabled processors for altering before returning them.
   *
   * @return \Drupal\Core\TypedData\DataDefinitionInterface[]
   *   The properties included in this index, defined by the datasource and
   *   optionally altered by the enabled processors.
   *
   * @throws \Drupal\search_api\Exception\SearchApiException
   *   If the specified datasource isn't enabled for this index, or couldn't be
   *   loaded.
   */
  public function getPropertyDefinitions($datasource_id, $alter = TRUE);

  /**
   * Loads a single search object of this index.
   *
   * @param string $item_id
   *   The internal item ID of the object, with datasource prefix.
   *
   * @return \Drupal\Core\TypedData\ComplexDataInterface|null
   *   The loaded object, or NULL if the item does not exist.
   */
  public function loadItem($item_id);

  /**
   * Loads multiple search objects for this index.
   *
   * @param array $item_ids
   *   The internal item IDs of the objects, with datasource prefix.
   * @param bool $group_by_datasource
   *   (optional) If TRUE, items will be returned grouped by datasource instead
   *   of in a single, flat array.
   *
   * @return \Drupal\Core\TypedData\ComplexDataInterface[]
   *   The loaded items. If $flat is TRUE, a single-dimensional array mapping
   *   internal item IDs to the loaded items. Otherwise, an array mapping
   *   datasource IDs to arrays of items (keyed by internal item ID) loaded for
   *   that datasource.
   */
  public function loadItemsMultiple(array $item_ids, $group_by_datasource = FALSE);

  /**
   * Indexes a set amount of items.
   *
   * Will fetch the items to be indexed from the datasource and send them to
   * indexItems(). It will then mark all successfully indexed items as such in
   * the datasource.
   *
   * @param int $limit
   *   (optional) The maximum number of items to index.
   * @param string|null $datasource_id
   *   (optional) If specified, only items of the datasource with that ID are
   *   indexed.
   *
   * @return int
   *   The number of items successfully indexed.
   */
  public function index($limit = -1, $datasource_id = NULL);

  /**
   * Indexes some objects on this index.
   *
   * Will return the IDs of items that should be marked as indexed – i.e., items
   * that were either rejected from indexing or were successfully indexed.
   *
   * @param \Drupal\Core\TypedData\ComplexDataInterface[] $search_objects
   *   An array of search objects to be indexed, keyed by their item IDs.
   *
   * @return string[]
   *   The IDs of all items that should be marked as indexed.
   *
   * @throws \Drupal\search_api\Exception\SearchApiException
   *   If any error occurred during indexing.
   */
  public function indexItems(array $search_objects);

  /**
   * Adds items from a specific datasource to the index.
   *
   * Note that this method receives datasource-specific item IDs as the
   * parameter, not containing the datasource prefix.
   *
   * @param string $datasource_id
   *   The ID of the datasource to which the items belong.
   * @param array $ids
   *   An array of datasource-specific item IDs.
   *
   * @return bool
   *   TRUE if the operation was successful, otherwise FALSE.
   */
  public function trackItemsInserted($datasource_id, array $ids);

  /**
   * Updates items from a specific datasource present in the index.
   *
   * Note that this method receives datasource-specific item IDs as the
   * parameter, not containing the datasource prefix.
   *
   * @param string $datasource_id
   *   The ID of the datasource to which the items belong.
   * @param array $ids
   *   An array of datasource-specific item IDs.
   *
   * @return bool
   *   TRUE if the operation was successful, otherwise FALSE.
   */
  public function trackItemsUpdated($datasource_id, array $ids);

  /**
   * Deletes items from the index.
   *
   * Note that this method receives datasource-specific item IDs as the
   * parameter, not containing the datasource prefix.
   *
   * @param string $datasource_id
   *   The ID of the datasource to which the items belong.
   * @param array $ids
   *   An array of datasource-specific items IDs.
   *
   * @return bool
   *   TRUE if the operation was successful, otherwise FALSE.
   */
  public function trackItemsDeleted($datasource_id, array $ids);

  /**
   * Marks all items in this index for reindexing.
   *
   * @throws \Drupal\search_api\Exception\SearchApiException
   *   If an internal error prevented the operation from succeeding. E.g., if
   *   the tracker couldn't be loaded.
   */
  public function reindex();

  /**
   * Clears all items in this index and marks them for reindexing.
   *
   * @throws \Drupal\search_api\Exception\SearchApiException
   *   If the server couldn't be loaded, for example.
   */
  public function clear();

  /**
   * Resets the static and stored caches associated with this index.
   */
  public function resetCaches();

  /**
   * Creates a query object for this index.
   *
   * @param array $options
   *   (optional) Associative array of options configuring this query.
   *
   * @return \Drupal\search_api\Query\QueryInterface
   *   A query object for searching this index.
   *
   * @throws \Drupal\search_api\Exception\SearchApiException
   *   If the index is currently disabled or its server doesn't exist.
   *
   * @see \Drupal\search_api\Query\QueryInterface::create()
   */
  public function query(array $options = array());

  /**
   * Starts tracking for this index.
   */
  public function startTracking();

  /**
   * Stops tracking for this index.
   */
  public function stopTracking();

}
