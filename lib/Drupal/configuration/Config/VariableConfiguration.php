<?php

/**
 * @file
 * Definition of Drupal\configuration\Config\VariableConfiguration.
 */

namespace Drupal\configuration\Config;

use Drupal\configuration\Config\Configuration;

class VariableConfiguration extends Configuration {

  function __construct($identifier) {
    parent::__construct('variable', $identifier);
  }

  function build($include_dependencies = TRUE) {
    $this->data = variable_get($this->getIdentifier(), NULL);
    if ($include_dependencies) {
      $this->findDependencies();
    }
    return $this;
  }

  /**
   * Returns all the identifiers available for this component.
   */
  public static function getAllIdentifiers() {
    $variables = db_query("SELECT name FROM variable")->fetchAll();
    $return = array();
    foreach ($variables as $variable) {
      $return[] = $variable->name;
    }
    return $return;
  }

  public static function alterDependencies(Configuration $config, &$stack) {
    if ($config->getComponent() == 'content_type') {
      $variables = array(
        'comment',
        'comment_anonymous',
        'comment_controls',
        'comment_default_mode',
        'comment_default_order',
        'comment_default_per_page',
        'comment_form_location',
        'comment_preview',
        'comment_subject_field',
        'field_bundle_settings_node_',
        'language_content_type',
        'menu_options',
        'menu_parent',
        'node_options',
        'node_preview',
        'node_submitted',
      );

      $entity_type = $config->getEntityType();
      $fields = field_info_instances($entity_type, $config->getIdentifier());
      foreach ($variables as $variable) {
        $identifier = $variable . '_' .$config->getIdentifier();

        // Avoid include multiple times the same dependency.
        if (empty($stack['variable.' . $identifier])) {
          $field = new VariableConfiguration($identifier);
          $field->build();
          $config->addToDependencies($field);
          $stack['variable.' . $identifier] = TRUE;
        }
      }
    }
  }
}
