<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\Synonyms.
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor;

use Drupal\search_api\Processor\FieldsProcessorPluginBase;
use Drupal\search_api\Query\QueryInterface;

/**
 * @SearchApiProcessor(
 *   id = "search_api_synonyms_processor",
 *   label = @Translation("Synonyms processor"),
 *   description = @Translation("Words that expand in more words during indexing")
 * )
 */
class Synonyms extends FieldsProcessorPluginBase {

  /**
   * Holds all words ignored for the last query.
   *
   * @var array
   */
  protected $ignored = array();

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array(
      'synonyms' => array(
        "xs" => array("extra small"),
        "s" => array("small"),
        "m" => array("medium"),
        "l" => array("large"),
        "xl" => array("extra large"),
        "xxl" => array("super extra large"),
      ),
      'file' => '',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, array &$form_state) {
    $form = array();

    $form['help'] = array(
      '#markup' => '<p>' . $this->t('Provide a synonyms file or enter the words in this form. If you do both, both will be used. Read about <a href="!synonyms">synonyms</a>.', array('!synonyms' => 'https://en.wikipedia.org/wiki/Synonyms')) . '</p>'
    );

    // Only include full text fields. Important as only those can be tokenized.
    $fields = $this->index->getFields();
    $field_options = array();
    $default_fields = array();
    if (isset($this->configuration['fields'])) {
      $default_fields = array_keys($this->configuration['fields']);
      $default_fields = array_combine($default_fields, $default_fields);
    }

    foreach ($fields as $name => $field) {
      if ($field['type'] == 'text') {
        if ($this->testType($field['type'])) {
          $field_options[$name] = $field['name_prefix'] . $field['name'];
          if (!isset($this->configuration['fields']) && $this->testField($name, $field)) {
            $default_fields[$name] = $name;
          }
        }
      }
    }

    $form['fields'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Enable this processor on the following fields'),
      '#options' => $field_options,
      '#default_value' => $default_fields,
    );

    $form['file'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Synonyms file'),
      '#description' => $this->t('This must be a stream-type description like <code>public://synonyms/synonyms.txt</code> or <code>http://example.com/synonyms.txt</code> or <code>private://synonyms.txt</code>.'),
    );
    $form['synonyms'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Synonyms'),
      '#description' => $this->t('Enter a linebreak separated list of synonyms that will be expanded in multiple words in content before it is indexed. Each synonym has a Parent that will be searched for and expanded with the childs of that synonyms. Example XL;Extra Large;X-Large. Separation of Parent and Childs need to be with ;'),
      '#default_value' => $this->synonymsImplode($this->configuration['synonyms']),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, array &$form_state) {
    parent::validateConfigurationForm($form, $form_state);

    $uri = $form_state['values']['file'];
    if (!empty($uri) && !@file_get_contents($uri)) {
      $el = $form['file'];
      \Drupal::formBuilder()->setError($el, $form_state, $this->t('Synonyms file') . ': ' . $this->t('The file %uri is not readable or does not exist.', array('%uri' => $uri)));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, array &$form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['synonyms'] = $this->synonymsExplode($form_state['values']['stopwords']);
    dpm($this->configuration['synonyms']);
  }

  /**
   * {@inheritdoc}
   */
  public function process(&$value) {
    $synonyms = $this->getSynonyms();
    if (empty($synonyms) || !is_string($value)) {
      return;
    }

    $str_replace_array = array();
    foreach ($synonyms as $synonym_parent => $synonym_childs) {
      $str_replace_array[$synonym_parent] = implode(' ', $synonym_childs);
    }

    $value = str_replace(array_keys($str_replace_array),array_values($str_replace_array),$value);
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessSearchQuery(QueryInterface $query) {
    $this->ignored = array();
    parent::preprocessSearchQuery($query);
  }

  /**
   * {@inheritdoc}
   */
  public function postprocessSearchResults(array &$response, QueryInterface $query) {
    if ($this->ignored) {
      if (isset($response['ignored'])) {
        $response['ignored'] = array_merge($response['ignored'], $this->ignored);
      }
      else {
        $response['ignored'] = $this->ignored;
      }
    }
  }

  /**
   * Gets all the stopwords.
   *
   * @return
   *   An array whose keys are the stopwords set in either the file or the text
   *   field.
   */
  protected function getSynonyms() {
    if (isset($this->synonyms)) {
      return $this->synonyms;
    }
    $file_words = $form_words = array();
    if (!empty($this->configuration['file']) && $synonyms_file = file_get_contents($this->configuration['file'])) {
      $file_words = $this->synonymsExplode($synonyms_file);
    }
    if (!empty($this->configuration['synonyms'])) {
      $form_words = $this->configuration['synonyms'];
    }
    $this->synonyms = array_merge($file_words, $form_words);
    return $this->synonyms;
  }

  protected function synonymsExplode($synonyms_text) {
    $synonyms = array();
    // Convert our text input to an array
    $synonyms_lines = explode(PHP_EOL, $synonyms_text);
    if (is_array($synonyms_line)) {
      foreach ($synonyms_list as $synonym_line) {
        if (!strstr($synonym_line, ';')) {
          return false;
        }

        $synonym = explode(';', $synonym_line);

        if (is_array($synonym)) {
          $parent = array_shift($synonym);
          $synonyms[$parent] = $synonym;
        }
      }
    }
    return $synonyms;
  }

  protected function synonymsImplode($synonyms) {
    if (!is_array($synonyms)) {
      return false;
    }

    $synonyms_imploded = "";
    foreach ($synonyms as $synonym_parent => $synonym_childs) {
      $synonyms_imploded .= $synonym_parent . ";" . implode(';', $synonym_childs) . "\n";
    }

    return $synonyms_imploded;
  }
}
