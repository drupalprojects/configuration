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

  static public function rebuildHook() {
    $vocabularies = db_select('configuration_staging', 'c')
                ->fields('c', array('data'))
                ->condition('component', self::$component)
                ->execute()
                ->fetchCol();

    if ($vocabularies) {
      $existing = taxonomy_get_vocabularies();
      foreach ($vocabularies as $serialized_vocabulary) {
        $vocabulary = unserialize($serialized_vocabulary);
        $vocabulary = (object) $vocabulary;
        foreach ($existing as $existing_vocab) {
          if ($existing_vocab->machine_name === $vocabulary->machine_name) {
            $vocabulary->vid = $existing_vocab->vid;
          }
        }
        taxonomy_vocabulary_save($vocabulary);
      }
    }
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
