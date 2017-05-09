<?php

namespace Configuration\Handlers;

use Configuration\Configuration;
use Configuration\Handlers\ConfigurationHandler;

class ContentTypeConfigurationHandler extends ConfigurationHandler {

  static public function getSupportedTypes() {
    return array('content_type');
  }

  public function getIdentifiers() {
    return $this->configuration_manager->drupal()->node_type_get_names();
  }

  public function loadFromDatabase($identifier) {
    $content_type_name =  $this->getInternalId($identifier);

    $configuration = new Configuration;
    $configuration->setIdentifier($identifier);
    $content_type = (object)$this->configuration_manager->drupal()->node_type_get_type($content_type_name);

    $data = new \StdClass();
    $keys = array(
      'type',
      'name',
      'description',
      'has_title',
      'title_label',
      'base',
      'module',
      'help',
    );
    foreach ($keys as $key) {
      $data->$key = $content_type->$key;
    }

    // Force module name to be 'configuration' if set to 'node. If we leave as
    // 'node' the content type will be assumed to be database-stored by
    // the node module.
    if ($content_type->base === 'node') {
      $data->base = 'configuration';
    }

    $configuration->setData($data);

    $event = $this->triggerEvent('load_from_database', $configuration);
    $event_settings = array(
      'entity_type' => 'node',
      'bundle_name' => $data->type,
    );
    $event = $this->triggerEvent('load_from_database.entity', $event->configuration, $event_settings, FALSE);

    return $event->configuration;
  }

  public function writeToDatabase(Configuration $configuration) {
    $content_type_name = $this->getInternalId($configuration->getIdentifier());

    $event = $this->triggerEvent('write_to_database', $configuration);

    $content_type = $event->configuration->getData();
    $content_type->base = isset($content_type->base) ? $content_type->base : 'node_content';
    $content_type->module = isset($content_type->module) ? $content_type->module : 'node';
    $content_type->custom = 1;
    $content_type->modified = 1;
    $content_type->locked = 0;
    $this->configuration_manager->drupal()->node_type_save($content_type);
  }

  public function removeFromDatabase(Configuration $configuration) {
    $content_type_name = $this->getInternalId($configuration->getIdentifier());

    $event = $this->triggerEvent('remove_from_database', $configuration);

    $this->configuration_manager->drupal()->node_type_delete($content_type_name);
  }

}
