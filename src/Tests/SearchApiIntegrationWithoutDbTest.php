<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\SearchApiIntegrationWithoutDbTest.
 */

namespace Drupal\search_api\Tests;

/**
 * Provides integration tests for Search API.
 */
class SearchApiIntegrationWithoutDbTest extends SearchApiWebTestBase {

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
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Search API integration without backend db test',
      'description' => 'Test creation of Search API indexes and servers without a backend db through the UI.',
      'group' => 'Search API',
    );
  }

  /**
   * Tests various UI interactions between servers and indexes.
   */
  public function testFramework() {
    // Login as an admin user for the rest of the tests.
    $this->drupalLogin($this->adminUser);

    module_uninstall(array('search_api_test_backend'));

    $this->createServer();
  }

  protected function createServer() {
    $server_id = drupal_strtolower($this->randomName());
    $settings_path = $this->urlGenerator->generateFromRoute('search_api.server_add', array(), array('absolute' => TRUE));

    $this->drupalGet($settings_path);
    $this->assertResponse(200, 'Server add page exists');

    $edit = array(
      'name' => '',
      'status' => 1,
      'description' => 'A server used for testing.',
      'backendPluginId' => '',
    );

    $this->drupalPostForm($settings_path, $edit, t('Save'));
    $this->assertText(t('!name field is required.', array('!name' => t('Server name'))));
    $this->assertText(t('!name field is required.', array('!name' => t('Backend'))));
    $this->assertText(t('!name field is required.', array('!name' => t('Machine-readable name'))));

    $edit = array(
      'name' => 'Search API test server',
      'status' => 1,
      'description' => 'A server used for testing.',
      'backendPluginId' => '',
    );
    $this->drupalPostForm($settings_path, $edit, t('Save'));
    $this->assertText(t('!name field is required.', array('!name' => t('Machine-readable name'))));
    $this->assertText(t('!name field is required.', array('!name' => t('Backend'))));

    $edit = array(
      'name' => 'Search API test server',
      'machine_name' => $server_id,
      'status' => 1,
      'description' => 'A server used for testing.',
      'backendPluginId' => '',
    );

    $this->drupalPostForm(NULL, $edit, t('Save'));

    $this->assertText(t('!name field is required.', array('!name' => t('Backend'))));
  }
}
