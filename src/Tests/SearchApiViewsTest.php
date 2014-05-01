<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\SearchApiViewsTest.
 */

namespace Drupal\search_api\Tests;

/**
 * Provides views tests for Search API.
 */
class SearchApiViewsTest extends SearchApiWebTestBase {

  use ExampleContentTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('search_api_test_views');

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Search API Views test',
      'description' => 'Test views integration',
      'group' => 'Search API',
    );
  }

  public function setUp() {
    parent::setUp();
    $this->setUpExampleStructure();
  }


  /**
   * Tests a view with a fulltext search field.
   */
  public function testFulltextSearch() {
    $this->insertExampleContent();
    $this->createServer();
    $this->createIndex();

    $this->drupalGet('search-api-test-fulltext');
  }

}
