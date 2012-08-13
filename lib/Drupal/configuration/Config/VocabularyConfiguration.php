<?php

/**
 * @file
 * Definition of Drupal\configuration\Config\VocabularyConfiguration.
 */

namespace Drupal\configuration\Config;

use Drupal\configuration\Config\Configuration;

class VocabularyConfiguration extends Configuration {

  function __construct($identifier) {
    parent::__construct('vocabulary', $identifier);
  }

  public function configForEntity() {
    return TRUE;
  }

  public function getEntityType() {
    return 'vocabulary';
  }

  public function build($include_dependencies = TRUE) {
    $vocabularies = taxonomy_get_vocabularies();
    foreach ($vocabularies as $vocabulary) {
      if ($vocabulary->machine_name == $this->identifier) {
        $this->data = $vocabulary;

        if ($include_dependencies) {
          $this->findDependencies();
        }
        break;
      }
    }
    return $this;
  }

  /**
   * Returns all the identifiers available for this component.
   */
  public static function getAllIdentifiers() {
    return array_keys(taxonomy_get_vocabularies());
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

}
