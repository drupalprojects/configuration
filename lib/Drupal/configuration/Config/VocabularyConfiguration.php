<?php

/**
 * @file
 * Definition of Drupal\configuration\Config\VocabularyConfiguration.
 */

namespace Drupal\configuration\Config;

use Drupal\configuration\Config\Configuration;

class VocabularyConfiguration extends Configuration {

  static protected $component = 'vocabulary';

  public function configForEntity() {
    return TRUE;
  }

  public function getEntityType() {
    return 'vocabulary';
  }

  protected function prepareBuild() {
    $vocabularies = taxonomy_get_vocabularies();
    foreach ($vocabularies as $vocabulary) {
      if ($vocabulary->machine_name == $this->identifier) {
        $this->data = $vocabulary;
        break;
      }
    }
    return $this;
  }

  static public function rebuildHook($vocabularies = array()) {
    if ($vocabularies) {
      $existing = taxonomy_get_vocabularies();
      foreach ($vocabularies as $config) {
        $vocabulary = (object) $config->getData();
        $vocabulary->original = $vocabulary;
        foreach ($existing as $existing_vocab) {
          if ($existing_vocab->machine_name === $vocabulary->machine_name) {
            $vocabulary->vid = $existing_vocab->vid;
            break;
          }
        }
        taxonomy_vocabulary_save($vocabulary);
      }
    }
  }

  static public function revertHook($vocabularies = array()) {
    static::rebuildHook($vocabularies);
  }

  /**
   * Returns all the identifiers available for this component.
   */
  public static function getAllIdentifiers() {
    $return = array();
    $vocabularies = taxonomy_get_vocabularies();
    foreach ($vocabularies as $vocabulary) {
      $return[] = $vocabulary->machine_name;
    }
    return $return;
  }

  public static function alterDependencies(Configuration $config, &$stack) {
    if ($config->getComponent() == 'field') {
      // Check if the field is using a image style
      $field = $config->data['field_config'];
      if ($field['type'] == 'taxonomy_term_reference' && $field['settings']['allowed_values']) {
        foreach ($field['settings']['allowed_values'] as $vocabulary) {
          if (empty($stack['vocabulary.' . $vocabulary['vocabulary']])) {
            $vocabulary_conf = new VocabularyConfiguration($vocabulary['vocabulary']);
            $vocabulary_conf->build();
            $config->addToDependencies($vocabulary_conf);
            $stack['vocabulary.' . $vocabulary['vocabulary']] = TRUE;
          }
        }
      }
    }
  }

  public function findRequiredModules() {
    $this->addToModules($this->data->module);
  }

}
