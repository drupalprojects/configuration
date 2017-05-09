<?php

namespace Configuration\Handlers;

use Configuration\Configuration;
use Configuration\Handlers\ConfigurationHandler;


class FieldInstanceConfigurationHandler extends ConfigurationHandler {

  protected $purgue_batch = 0;

  static public function getSupportedTypes() {
    return array('field_instance');
  }

  public function getIdentifiers() {
    $identifiers = array();
    foreach ($this->configuration_manager->drupal()->field_info_fields() as $field_name => $field) {
      foreach ($field['bundles'] as $entity_type => $bundles) {
        foreach ($bundles as $bundle_name) {
          $identifier = "{$entity_type}.{$bundle_name}.{$field_name}";
          $identifiers[$identifier] = t('Field base: @field part of (@entity.@bundle)',
            array(
              '@field' => $field_name,
              '@entity' => $entity_type,
              '@bundle' => $bundle_name
            )
          );
        }
      }
    }
    return $identifiers;
  }

  public function loadFromDatabase($identifier) {
    $name = $this->getInternalId($identifier);

    list($entity_type, $bundle, $field_name) = explode('.', $name);
    $instance_info = field_info_instance($entity_type, $field_name, $bundle);

    $configuration = new Configuration;
    $configuration->setIdentifier($identifier);
    if (empty($instance_info)) {

    }
    else {
      unset($instance_info['id']);
      unset($instance_info['field_id']);
      $configuration->setData($instance_info);

      $configuration->addModule($instance_info['widget']['module']);
    }

    $event = $this->triggerEvent('load_from_database', $configuration);

    return $event->configuration;
  }

  public function writeToDatabase(Configuration $configuration) {
    $this->configuration_manager->drupal()->field_info_cache_clear();

    // Load all the existing fields and instance up-front so that we don't
    // have to rebuild the cache all the time.
    $existing_instances = $this->configuration_manager->drupal()->field_info_instances();

    $field_instance = $configuration->getData();
    if (empty($field_instance)) {
      return;
    }
    if (isset($existing_instances[$field_instance['entity_type']][$field_instance['bundle']][$field_instance['field_name']])) {
      $existing_instance = $existing_instances[$field_instance['entity_type']][$field_instance['bundle']][$field_instance['field_name']];
      if ($field_instance + $existing_instance != $existing_instance) {
        $this->configuration_manager->drupal()->field_update_instance($field_instance);
      }
    }
    else {
      $this->configuration_manager->drupal()->field_create_instance($field_instance);
    }

    $this->configuration_manager->drupal()->variable_set('menu_rebuild_needed', TRUE);
  }

  public function removeFromDatabase(Configuration $configuration) {
    $name = $this->getInternalId($configuration->getIndentifier());

    list($entity_type, $bundle, $field_name) = explode('.', $name);

    $instance = $this->configuration_manager->drupal()->field_info_instance($entity_type, $field_name, $bundle);
    $this->configuration_manager->drupal()->field_delete_instance($instance, TRUE);

    $this->purge_batch++;
  }

  public static function getSubscribedEvents() {
    return array(
      'load_from_database.entity' => array('onLoadFromDatabaseEntity', 0),
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

  public function onLoadFromDatabaseEntity($event) {
    $entity_type = $event->getSetting('entity_type');
    $bundle_name = $event->getSetting('bundle_name');

    $entity_info = $this->configuration_manager->drupal()->entity_get_info($entity_type);
    if (!empty($entity_info['fieldable'])) {
      foreach (array_keys($this->configuration_manager->drupal()->field_info_instances($entity_type, $bundle_name)) as $field_instance) {
        $id = "field_instance.${entity_type}.${bundle_name}.${field_instance}";
        $this->configuration_manager->newPart($event->configuration, $id);
      }
    }
  }

  protected function jsonAsArray() {
    return TRUE;
  }
}
