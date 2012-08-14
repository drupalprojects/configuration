<?php

/**
 * @file
 * Definition of Drupal\configuration\Config\FieldConfiguration.
 */

namespace Drupal\configuration\Config;

use Drupal\configuration\Config\Configuration;

class FieldConfiguration extends Configuration {

  static protected $component = 'field';

  /**
   * Set the component identifier of this configuration.
   *
   * Identifiers for fields are build using the entity_type,
   * bundle and field name. For example node.page.body
   */
  public function setIdentifier($entity_type, $field_name, $bundle_name) {
    $this->identifier = $entity_type . "." . $field_name  . "." . $bundle_name;
  }

  public function build($include_dependencies = TRUE) {
    $this->data = $this->field_load($this->identifier);
    if ($include_dependencies) {
      $this->findDependencies();
    }
    return $this;
  }

  /**
   * Returns all the identifiers available for this component.
   */
  public static function getAllIdentifiers() {
    $identifiers = array();
    foreach (field_info_fields() as $field) {
      foreach ($field['bundles'] as $entity_type => $bundles) {
        foreach ($bundles as $bundle_name) {
          $identifiers[] = $entity_type . '.' . $field['field_name'] . '.' . $bundle_name;
        }
      }
    }
    return $identifiers;
  }

  /**
   * Load a field's configuration and instance configuration by an
   * entity_type-bundle-field_name identifier.
   */
  protected function field_load($identifier) {
    list($entity_type, $field_name, $bundle) = explode('.', $identifier);
    $field_info = field_info_field($field_name);
    $instance_info = field_info_instance($entity_type, $field_name, $bundle);
    if ($field_info && $instance_info) {
      unset($field_info['id']);
      unset($field_info['bundles']);
      unset($instance_info['id']);
      unset($instance_info['field_id']);
      return array(
        'field_config' => $field_info,
        'field_instance' => $instance_info,
      );
    }
    return FALSE;
  }

  public static function alterDependencies(Configuration $config, &$stack) {
    if ($config->configForEntity()) {
      $entity_type = $config->getEntityType();
      $fields = field_info_instances($entity_type, $config->getIdentifier());
      foreach ($fields as $name => $field) {
        $identifier = $entity_type . "." . $field['field_name']  . "." . $field['bundle'];

        // Avoid include multiple times the same dependency.
        if (empty($stack['field.' . $identifier])) {
          $field = new FieldConfiguration($identifier);
          $field->build();
          $config->addToDependencies($field);
          $stack['field.' . $identifier] = TRUE;
        }
      }
    }
  }
}
