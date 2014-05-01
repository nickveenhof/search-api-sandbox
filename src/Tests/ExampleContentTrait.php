<?php
/**
 * Contains \Drupal\search_api\Tests\TestDataTrait.
 */

namespace Drupal\search_api\Tests;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Language\Language;
use Drupal\search_api\Index\IndexInterface;

/**
 * Contains helpers to create data that can be used by tests.
 */
trait ExampleContentTrait {

  /**
   * A Search API server ID.
   *
   * @var string
   */
  protected $serverId;

  /**
   * A Search API index ID.
   *
   * @var string
   */
  protected $indexId;

  /**
   * Sets up the necessary bundles and fields.
   */
  protected function setUpExampleStructure() {
    // Create the required bundles.
    entity_test_create_bundle('item');
    entity_test_create_bundle('article');

    // Create a 'body' field on the test entity type.
    entity_create('field_config', array(
        'name' => 'body',
        'entity_type' => 'entity_test',
        'type' => 'text_with_summary',
        'cardinality' => 1,
      ))->save();
    entity_create('field_instance_config', array(
        'field_name' => 'body',
        'entity_type' => 'entity_test',
        'bundle' => 'item',
        'label' => 'Body',
        'settings' => array('display_summary' => TRUE),
      ))->save();
    entity_create('field_instance_config', array(
        'field_name' => 'body',
        'entity_type' => 'entity_test',
        'bundle' => 'article',
        'label' => 'Body',
        'settings' => array('display_summary' => TRUE),
      ))->save();

    // Create a 'keywords' field on the test entity type.
    entity_create('field_config', array(
        'name' => 'keywords',
        'entity_type' => 'entity_test',
        'type' => 'string',
        'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      ))->save();
    entity_create('field_instance_config', array(
        'field_name' => 'keywords',
        'entity_type' => 'entity_test',
        'bundle' => 'item',
        'label' => 'Keywords',
      ))->save();
    entity_create('field_instance_config', array(
        'field_name' => 'keywords',
        'entity_type' => 'entity_test',
        'bundle' => 'article',
        'label' => 'Keywords',
      ))->save();
  }

  protected function insertExampleContent() {
    $count = \Drupal::entityQuery('entity_test')->count()->execute();

    entity_create('entity_test', array(
        'id' => 1,
        'name' => 'foo bar baz',
        'body' => 'test test',
        'type' => 'item',
        'keywords' => array('orange'),
      ))->save();
    entity_create('entity_test', array(
        'id' => 2,
        'name' => 'foo test',
        'body' => 'bar test',
        'type' => 'item',
        'keywords' => array('orange', 'apple', 'grape'),
      ))->save();
    entity_create('entity_test', array(
        'id' => 3,
        'name' => 'bar',
        'body' => 'test foobar',
        'type' => 'item',
      ))->save();
    entity_create('entity_test', array(
        'id' => 4,
        'name' => 'foo baz',
        'body' => 'test test test',
        'type' => 'article',
        'keywords' => array('apple', 'strawberry', 'grape'),
      ))->save();
    entity_create('entity_test', array(
        'id' => 5,
        'name' => 'bar baz',
        'body' => 'foo',
        'type' => 'article',
        'keywords' => array('orange', 'strawberry', 'grape', 'banana'),
      ))->save();
    $count = \Drupal::entityQuery('entity_test')->count()->execute() - $count;
    $this->assertEqual($count, 5, "$count items inserted.");
  }

  protected function createServer() {
    $this->serverId = 'database_search_server';
    $values = array(
      'name' => 'Database search server',
      'machine_name' => $this->serverId,
      'status' => 1,
      'description' => 'A server used for testing.',
      'backendPluginId' => 'search_api_db',
      'backendPluginConfig' => array(
        'min_chars' => 3,
        'database' => 'default:default',
      ),
    );
    $success = (bool) entity_create('search_api_server', $values)->save();
    $this->assertTrue($success, 'The server was successfully created.');
  }

  protected function createIndex() {
    $this->indexId = 'test_index';
    $values = array(
      'name' => 'Test index',
      'machine_name' => $this->indexId,
      'datasourcePluginIds' => array('entity:entity_test'),
      'trackerPluginId' => 'default_tracker',
      'status' => 1,
      'description' => 'An index used for testing.',
      'serverMachineName' => $this->serverId,
      'options' => array(
        'cron_limit' => -1,
        'index_directly' => TRUE,
        'fields' => array(
          $this->getFieldId('id') => array(
            'type' => 'integer',
          ),
          $this->getFieldId('name') => array(
            'type' => 'text',
            'boost' => '5.0',
          ),
          $this->getFieldId('body') => array(
            'type' => 'text',
          ),
          $this->getFieldId('type') => array(
            'type' => 'string',
          ),
          $this->getFieldId('keywords') => array(
            'type' => 'string',
          ),
        ),
      ),
    );

    /** @var \Drupal\search_api\Index\IndexInterface $index */
    $index = entity_create('search_api_index', $values);
    $success = (bool) $index->save();
    $this->assertTrue($success, 'The index was successfully created.');
    $this->assertEqual($index->getTracker()->getTotalItemsCount(), 5, 'Correct item count.');
    $this->assertEqual($index->getTracker()->getIndexedItemsCount(), 0, 'All items still need to be indexed.');
  }

  /**
   * Returns the internal field ID for the given entity field name.
   *
   * @param string $field_name
   *   The field name.
   *
   * @return string
   *   The internal field ID.
   */
  protected function getFieldId($field_name) {
    return 'entity:entity_test' . IndexInterface::DATASOURCE_ID_SEPARATOR . $field_name;
  }

  /**
   * Returns the idem IDs for the given entity IDs.
   *
   * @param array $entity_ids
   *   An array of entity IDs.
   *
   * @return array
   *   An array of item IDs.
   */
  protected function getItemIds(array $entity_ids) {
    return array_map(function ($entity_id) {
        return 'entity:entity_test' . IndexInterface::DATASOURCE_ID_SEPARATOR . $entity_id . ':' . Language::LANGCODE_NOT_SPECIFIED;
      }, $entity_ids);
  }

} 
