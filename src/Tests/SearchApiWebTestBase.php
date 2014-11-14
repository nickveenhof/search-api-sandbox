<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\SearchApiWebTestBase.
 */

namespace Drupal\search_api\Tests;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\simpletest\WebTestBase;

/**
 * Provides the base class for web tests for Search API.
 */
abstract class SearchApiWebTestBase extends WebTestBase {

  use StringTranslationTrait;

  /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  public static $modules = array('node', 'search_api', 'search_api_test_backend');

  /**
   * An admin user used for this test.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $adminUser;

  /**
   * A user without Search API admin permission.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $unauthorizedUser;

  /**
   * The anonymous user used for this test.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $anonymousUser;

  /**
   * The URL generator.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Create the users used for the tests.
    $this->adminUser = $this->drupalCreateUser(array('administer search_api', 'access administration pages'));
    $this->unauthorizedUser = $this->drupalCreateUser(array('access administration pages'));
    $this->anonymousUser = $this->drupalCreateUser();

    // Get the URL generator.
    $this->urlGenerator = $this->container->get('url_generator');

    // Create a node article type.
    $this->drupalCreateContentType(array(
      'type' => 'article',
      'name' => 'Article',
    ));

    // Create a node page type.
    $this->drupalCreateContentType(array(
      'type' => 'page',
      'name' => 'Page',
    ));
  }

  /**
   * Creates or deletes a server.
   *
   * @param string $name
   *   (optional) The name of the server.
   * @param string $machine_name
   *   (optional) The ID of the server.
   * @param string $backend_id
   *   (optional) The ID of the backend to set for the server.
   * @param array $backend_config
   *   (optional) The backend configuration to set for the server.
   * @param bool $reset
   *   (optional) If TRUE, delete the server instead of creating it. (Only the
   *   server's ID is required in that case.)
   *
   * @return \Drupal\search_api\Server\ServerInterface
   *   A search server.
   */
  public function getTestServer($name = 'WebTest server', $machine_name = 'webtest_server', $backend_id = 'search_api_test_backend', $backend_config = array(), $reset = FALSE) {
    if ($reset) {
      $server = Server::load($machine_name);
      if ($server) {
        $server->delete();
      }
    }
    else {
      $server = Server::create(array(
        'machine_name' => $machine_name,
        'name' => $name,
        'description' => $name,
        'backend' => $backend_id,
        'backend_config' => $backend_config,
      ));
      $server->save();
    }

    return $server;
  }

  /**
   * Creates or deletes an index.
   *
   * @param string $name
   *   (optional) The name of the index.
   * @param string $machine_name
   *   (optional) The ID of the index.
   * @param string $server_id
   *   (optional) The server to which the index should be attached.
   * @param string $datasource_id
   *   (optional) The ID of a datasource to set for this index.
   * @param bool $reset
   *   (optional) If TRUE, delete the index instead of creating it. (Only the
   *   index's ID is required in that case.)
   *
   * @return \Drupal\search_api\Index\IndexInterface
   *   A search index.
   */
  public function getTestIndex($name = 'WebTest Index', $machine_name = 'webtest_index', $server_id = 'webtest_server', $datasource_id = 'entity:node', $reset = FALSE) {
    if ($reset) {
      $index = Index::load($machine_name);
      if ($index) {
        $index->delete();
      }
    }
    else {
      $index = Index::create(array(
        'machine_name' => $machine_name,
        'name' => $name,
        'description' => $name,
        'server' => $server_id,
        'datasources' => array($datasource_id),
      ));
      $index->save();
    }

    return $index;
  }

}
