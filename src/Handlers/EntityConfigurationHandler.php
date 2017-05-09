<?php

namespace Configuration\Handlers;

use Configuration\Configuration;
use Configuration\Handlers\ConfigurationHandler;


class EntityConfigurationHandler extends ConfigurationHandler {

  static public function getSupportedTypes() {
    $supported = array();
    foreach (entity_crud_get_info() as $type => $info) {
      if (!empty($info['exportable'])) {
        $supported[] = $type;
      }
    }
    return $supported;
  }

  public function getIdentifiers() {
    $options = array();
    foreach ($this->configuration_manager->drupal()->entity_load_multiple_by_name($this->getType(), FALSE) as $name => $entity) {
      $options[$name] = $this->configuration_manager->drupal()->entity_label($this->getType(), $entity);
    }
    return $options;
  }

  public function loadFromDatabase($identifier) {
    $name = $this->getInternalId($identifier);

    $configuration = new Configuration;
    $configuration->setIdentifier($identifier);

    $entity = $this->configuration_manager->drupal()->entity_load_single($this->type, $name);
    $entity_info = $entity->entityInfo();

    $configuration->addModule($entity_info['module']);
    $configuration->setData($entity);

    $event = $this->triggerEvent('load_from_database', $configuration);

    $info = $entity->entityInfo();
    if (!empty($info['bundle of'])) {
      $event_settings = array(
        'entity_type' => $info['bundle of'],
        'bundle_name' => $name,
      );
      $event = $this->triggerEvent('load_from_database.entity', $event->configuration, $event_settings, FALSE);
    }

    return $event->configuration;
  }

  public function writeToDatabase(Configuration $configuration) {
    $name = $this->getInternalId($configuration->getIdentifier());

    $event = $this->triggerEvent('write_to_database', $configuration);

    $entity = $event->configuration->getData();
    if ($original = $this->configuration_manager->drupal()->entity_load_single($this->getType(), $this->getIdentifier())) {
      $entity->id = $original->id;
      unset($entity->is_new);
    }

    $this->configuration_manager->drupal()->entity_save($this->getType(), $entity);
  }

  public function removeFromDatabase(Configuration $configuration) {
    $name = $this->getInternalId($configuration->getIdentifier());

    $event = $this->triggerEvent('remove_from_database', $configuration);

    $entity = $event->configuration->getData();
    $this->configuration_manager->drupal()->entity_delete($this->getType(), $entityid->id);
  }

}
