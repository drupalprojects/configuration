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

  protected function prepareBuild() {
    $this->data = $this->field_load($this->identifier);
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
          $field->addToDependencies($config);
          $config->addToOptionalConfigurations($field);
          $stack['field.' . $identifier] = TRUE;
        }
      }
    }
  }

  public function findRequiredModules() {
    $this->addToModules($this->data['field_config']['storage']['module']);
    $this->addToModules($this->data['field_instance']['widget']['module']);
  }

  /**
   * There is no default hook for fields. This function creates the
   * fields when configurarion_rebuild() is called.
   */
  static function rebuildHook() {
    $fields = db_select('configuration_staging', 'c')
                ->fields('c', array('data'))
                ->condition('component', self::$component)
                ->execute()
                ->fetchCol();

    if ($fields) {
      field_info_cache_clear();

      // Load all the existing fields and instance up-front so that we don't
      // have to rebuild the cache all the time.
      $existing_fields = field_info_fields();
      $existing_instances = field_info_instances();

      foreach ($fields as $field_serialized) {
        $field = unserialize($field_serialized);
        // Create or update field.
        $field_config = $field['field_config'];
        if (isset($existing_fields[$field_config['field_name']])) {
          $existing_field = $existing_fields[$field_config['field_name']];
          if ($field_config + $existing_field != $existing_field) {
            field_update_field($field_config);
          }
        }
        else {
          field_create_field($field_config);
          $existing_fields[$field_config['field_name']] = $field_config;
        }

        // Create or update field instance.
        $field_instance = $field['field_instance'];
        if (isset($existing_instances[$field_instance['entity_type']][$field_instance['bundle']][$field_instance['field_name']])) {
          $existing_instance = $existing_instances[$field_instance['entity_type']][$field_instance['bundle']][$field_instance['field_name']];
          if ($field_instance + $existing_instance != $existing_instance) {
            field_update_instance($field_instance);
          }
        }
        else {
          field_create_instance($field_instance);
          $existing_instances[$field_instance['entity_type']][$field_instance['bundle']][$field_instance['field_name']] = $field_instance;
        }
      }

      if ($fields) {
        variable_set('menu_rebuild_needed', TRUE);
      }
    }
  }
}
