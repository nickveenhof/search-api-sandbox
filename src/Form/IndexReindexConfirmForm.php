<?php
/**
 * @file
 * Contains \Drupal\search_api\Form\IndexReindexConfirmForm.
 */

namespace Drupal\search_api\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;

/**
 * Defines a reindex confirm form for the Index entity.
 */
class IndexReindexConfirmForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to reindex the search index %name?', array('%name' => $this->entity->label()));
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelRoute() {
    return array(
      'route_name' => 'search_api.index_view',
      'route_parameters' => array(
        'search_api_index' => $this->entity->id(),
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array $form, array &$form_state) {
    // Get the search index entity object.
    $entity = $this->getEntity();
    // Reindex the search index.
    if ($entity->reindex()) {
      // Notify the user about the reindex being successful.
      drupal_set_message($this->t('The search index %name was successfully reindexed.', array('%name' => $entity->label())));
    }
    else {
      // Notify the user about the reindex failure.
      drupal_set_message($this->t('Failed to reindex items for the search index %name.', array('%name' => $entity->label())), 'error');
    }
    // Redirect to the index view page.
    $form_state['redirect_route'] = array(
      'route_name' => 'search_api.index_view',
      'route_parameters' => array(
        'search_api_index' => $entity->id(),
      ),
    );
  }

}