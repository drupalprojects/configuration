<?php

namespace Configuration\Handlers;

use Configuration\Configuration;
use Configuration\Handlers\ConfigurationHandler;


class FieldBaseConfigurationHandler extends ConfigurationHandler {

  protected $purgue_batch = 0;

  static public function getSupportedTypes() {
    return array('field_base');
  }

  public function getIdentifiers() {
    $identifiers = array();
    $fields = $this->configuration_manager->drupal()->field_info_fields();
    foreach ($fields as $field_name => $field) {
      $identifiers[$field_name] = $field_name;
    }
    return $identifiers;
  }

  public function loadFromDatabase($identifier) {
    $field_name = $this->getInternalId($identifier);

    $field_info = $this->configuration_manager->drupal()->field_info_field($field_name);

    $configuration = new Configuration;
    $configuration->setIdentifier($identifier);
    if (empty($field_info)) {

    }
    else {
      unset($field_info['id']);
      unset($field_info['bundles']);
      $configuration->setData($field_info);

      $configuration->addModule($field_info['storage']['module']);
      $configuration->addModule($field_info['module']);
    }

    $event = $this->triggerEvent('load_from_database', $configuration);

    return $event->configuration;
  }

  public function writeToDatabase(Configuration $configuration) {
    $this->configuration_manager->drupal()->field_info_cache_clear();

    // Load all the existing field bases up-front so that we don't
    // have to rebuild the cache all the time.
    $existing_fields = $this->configuration_manager->drupal()->field_info_fields();

    $event = $this->triggerEvent('write_to_database', $configuration);

    $field = $event->configuration->getData();

    if (empty($field)) {
      return;
    }

    // Create or update field.
    if (isset($existing_fields[$field['field_name']])) {
      $existing_field = $existing_fields[$field['field_name']];
      if ($field + $existing_field !== $existing_field) {
        field_update_field($field);
      }
    }
    else {
      $this->configuration_manager->drupal()->field_create_field($field);
      $existing_fields[$field['field_name']] = $field;
    }
    $this->configuration_manager->drupal()->variable_set('menu_rebuild_needed', TRUE);
  }

  public function removeFromDatabase(Configuration $configuration) {
    $field_name = $this->getInternalId($configuration->getIndentifier());

    $this->configuration_manager->drupal()->field_delete_field($field_name);

    $this->purge_batch++;
  }

  public static function getSubscribedEvents() {
    return array(
      'load_from_database.field_instance' => array('onLoadFromDatabaseFieldInstance', 0),
      'configuration_deleted_start' => array('onConfigurationsDeleteStart', 0),
      'configuration_deleted_end' => array('onConfigurationsDeleteEnd', 0),
    );
  }

  public function onConfigurationsDeleteStart($event) {
    $this->purge_batch = 0;
  }

  public function onConfigurationsDeleteEnd($event) {
    if (!empty($this->purge_batch)) {
      $this->configuration_manager->drupal()->field_purge_batch($this->purge_batch);
    }
    $this->purge_batch = 0;
  }

  public function onLoadFromDatabaseFieldInstance($event) {
    $name = $this->getInternalId($event->configuration->getIdentifier());
    list($entity_type, $bundle, $field_name) = explode('.', $name);
    $this->configuration_manager->newDependency($event->configuration, 'field_base.' . $field_name);
  }

  protected function jsonAsArray() {
    return TRUE;
  }
}
