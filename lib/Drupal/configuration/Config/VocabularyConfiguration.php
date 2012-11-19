<?php

/**
 * @file
 * Definition of Drupal\configuration\Config\VocabularyConfiguration.
 */

namespace Drupal\configuration\Config;

use Drupal\configuration\Config\Configuration;
use Drupal\configuration\Utils\ConfigIteratorSettings;

class VocabularyConfiguration extends Configuration {

  public function configForEntity() {
    return TRUE;
  }

  static public function getComponentHumanName($component, $plural = FALSE) {
    return $plural ? t('Vocabularies') : t('Vocabulary');
  }

  public static function isActive() {
    return module_exists('taxonomy');
  }

  public function getComponent() {
    return 'vocabulary';
  }

  static public function supportedComponents() {
    return array('vocabulary');
  }

  public function getEntityType() {
    return 'vocabulary';
  }

  protected function prepareBuild() {
    $vocabularies = taxonomy_get_vocabularies();
    foreach ($vocabularies as $vocabulary) {
      if ($vocabulary->machine_name == $this->getIdentifier()) {
        $this->data = $vocabulary;
        break;
      }
    }
    return $this;
  }

  public function saveToActiveStore(ConfigIteratorSettings &$settings) {
    $vocabulary = (object) $this->getData();
    $existing = taxonomy_get_vocabularies();
    foreach ($existing as $existing_vocab) {
      if ($existing_vocab->machine_name === $vocabulary->machine_name) {
        $vocabulary->vid = $existing_vocab->vid;
        break;
      }
    }
    taxonomy_vocabulary_save($vocabulary);
    $settings->addInfo('imported', $this->getUniqueId());
  }

  /**
   * Returns all the identifiers available for this component.
   */
  public static function getAllIdentifiers($component) {
    $return = array();
    $vocabularies = taxonomy_get_vocabularies();
    foreach ($vocabularies as $vocabulary) {
      $return[$vocabulary->machine_name] = $vocabulary->name;
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
