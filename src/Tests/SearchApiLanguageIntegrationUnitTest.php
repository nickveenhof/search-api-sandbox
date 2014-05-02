<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\SearchApiLanguageIntegrationUnitTest.
 */

namespace Drupal\search_api\Tests;

use Drupal\Component\Utility\String;
use Drupal\Core\Language\Language;
use Drupal\system\Tests\Entity\EntityLanguageTestBase;

/**
 * Tests translation handling of the content entity datasource.
 */
class SearchApiLanguageIntegrationUnitTest extends EntityLanguageTestBase {

  /**
   * The test entity type used in the test.
   *
   * @var string
   */
  protected $testEntityTypeId = 'entity_test_mul';

  /**
   * The test server.
   *
   * @var \Drupal\search_api\Server\ServerInterface
   */
  protected $server;

  /**
   * The test index.
   *
   * @var \Drupal\search_api\Index\IndexInterface
   */
  protected $index;

  /**
   * The content entity datasource.
   *
   * @var \Drupal\search_api\Datasource\DatasourceInterface
   */
  protected $datasouce;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('search_api', 'search_api_test_backend');

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Search API Item Translation',
      'description' => 'Tests Search API item translation functionality.',
      'group' => 'Search API',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->installSchema('search_api', array('search_api_item', 'search_api_task'));

    // Create a test server.
    $this->server = entity_create('search_api_server', array(
      'name' => $this->randomString(),
      'machine_name' => $this->randomName(),
      'status' => 1,
      'backendPluginId' => 'search_api_test_backend',
    ));
    $this->server->save();

    // Create a test index.
    $this->index = entity_create('search_api_index', array(
      'name' => $this->randomString(),
      'machine_name' => $this->randomName(),
      'status' => 1,
      'datasourcePluginIds' => array('entity:' . $this->testEntityTypeId),
      'trackerPluginId' => 'default_tracker',
      'serverMachineName' => $this->server->id(),
    ));
    $this->index->save();

    $this->datasouce = $this->index->getDatasource('entity:' . $this->testEntityTypeId);
  }

  /**
   * Tests translation handling of the content entity datasource.
   */
  public function testItemTranslations() {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity_1 */
    $entity_1 = entity_create($this->testEntityTypeId, array(
      'id' => 1,
      'name' => 'test 1',
      'user_id' => $this->container->get('current_user')->id(),
    ));
    $entity_1->save();
    $this->assertEqual($entity_1->language()->id, Language::LANGCODE_NOT_SPECIFIED, String::format('%entity_type: Entity language not specified.', array('%entity_type' => $this->testEntityTypeId)));
    $this->assertFalse($entity_1->getTranslationLanguages(FALSE), String::format('%entity_type: No translations are available', array('%entity_type' => $this->testEntityTypeId)));

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity_2 */
    $entity_2 = entity_create($this->testEntityTypeId, array(
      'id' => 2,
      'name' => 'test 2',
      'user_id' => $this->container->get('current_user')->id(),
    ));
    $entity_2->save();
    $this->assertEqual($entity_2->language()->id, Language::LANGCODE_NOT_SPECIFIED, String::format('%entity_type: Entity language not specified.', array('%entity_type' => $this->testEntityTypeId)));
    $this->assertFalse($entity_2->getTranslationLanguages(FALSE), String::format('%entity_type: No translations are available', array('%entity_type' => $this->testEntityTypeId)));

    // Test that the datasource returns the correct item IDs.
    $datasource_item_ids = $this->datasouce->getItemIds();
    sort($datasource_item_ids);
    $expected = array(
      '1:' . Language::LANGCODE_NOT_SPECIFIED,
      '2:' . Language::LANGCODE_NOT_SPECIFIED,
    );
    $this->assertEqual($datasource_item_ids, $expected, 'Datasource returns correct item ids.');

    // Test indexing the new entity
    $this->assertEqual($this->index->getTracker()->getIndexedItemsCount(), 0, 'The index is empty.');
    $this->assertEqual($this->index->getTracker()->getTotalItemsCount(), 2, 'There are two items to be indexed.');
    $this->index->index();
    $this->assertEqual($this->index->getTracker()->getIndexedItemsCount(), 2, 'Two items have been indexed.');

    // Now, make the first entity language-specific by assigning a language.
    $default_langcode = $this->langcodes[0];
    $entity_1->langcode->value = $default_langcode;
    $entity_1->save();
    $this->assertEqual($entity_1->language(), \Drupal::languageManager()->getLanguage($this->langcodes[0]), String::format('%entity_type: Entity language retrieved.', array('%entity_type' => $this->testEntityTypeId)));
    $this->assertFalse($entity_1->getTranslationLanguages(FALSE), String::format('%entity_type: No translations are available', array('%entity_type' => $this->testEntityTypeId)));

    // Test that the datasource returns the correct item IDs.
    $datasource_item_ids = $this->datasouce->getItemIds();
    sort($datasource_item_ids);
    $expected = array(
      '1:' . $this->langcodes[0],
      '2:' . Language::LANGCODE_NOT_SPECIFIED,
    );
    $this->assertEqual($datasource_item_ids, $expected, 'Datasource returns correct item ids.');

    // Test that the index needs to be updated.
    $this->assertEqual($this->index->getTracker()->getIndexedItemsCount(), 1, 'The updated item needs to be re-indexed.');
    $this->assertEqual($this->index->getTracker()->getTotalItemsCount(), 2, 'There are two items in total.');

    // Set two translations for the first entity and test that the datasource
    // returns three separate item IDs for each translation.
    $entity_1->getTranslation($this->langcodes[1])->save();
    $entity_1->getTranslation($this->langcodes[2])->save();
    $this->assertTrue($entity_1->getTranslationLanguages(FALSE), String::format('%entity_type: Translations are available', array('%entity_type' => $this->testEntityTypeId)));

    $datasource_item_ids = $this->datasouce->getItemIds();
    sort($datasource_item_ids);
    $expected = array(
      '1:' . $this->langcodes[0],
      '1:' . $this->langcodes[1],
      '1:' . $this->langcodes[2],
      '2:' . Language::LANGCODE_NOT_SPECIFIED,
    );
    $this->assertEqual($datasource_item_ids, $expected, 'Datasource returns correct item ids for a translated entity.');

    // Test that the index needs to be updated.
    $this->assertEqual($this->index->getTracker()->getIndexedItemsCount(), 1, 'The updated items needs to be re-indexed.');
    $this->assertEqual($this->index->getTracker()->getTotalItemsCount(), 4, 'There are four items in total.');

    // Delete one translation and test that the datasource returns only three
    // items.
    $entity_1->removeTranslation($this->langcodes[2]);
    $entity_1->save();

    $datasource_item_ids = $this->datasouce->getItemIds();
    sort($datasource_item_ids);
    $expected = array(
      '1:' . $this->langcodes[0],
      '1:' . $this->langcodes[1],
      '2:' . Language::LANGCODE_NOT_SPECIFIED,
    );
    $this->assertEqual($datasource_item_ids, $expected, 'Datasource returns correct item ids for a translated entity.');

    // Test re-indexing.
    $this->assertEqual($this->index->getTracker()->getTotalItemsCount(), 3, 'There are three items in total.');
    $this->assertEqual($this->index->getTracker()->getIndexedItemsCount(), 1, 'The updated items needs to be re-indexed.');
    $this->index->index();
    $this->assertEqual($this->index->getTracker()->getIndexedItemsCount(), 3, 'Three items are indexed.');
  }

}