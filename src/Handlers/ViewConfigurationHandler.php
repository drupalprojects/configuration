<?php

namespace Configuration\Handlers;

use Configuration\Configuration;
use Configuration\Handlers\ConfigurationHandler;

class ViewConfigurationHandler extends ConfigurationHandler {

  static public function getSupportedTypes() {
    return array('view');
  }

  public function getIdentifiers() {
    return $this->configuration_manager->drupal()->view_getIdentifiers();
  }

  public function loadFromDatabase($identifier) {
    $name =  $this->getInternalId($identifier);

    $configuration = new Configuration;
    $configuration->setIdentifier($identifier);
    $view = $this->configuration_manager->drupal()->views_get_view($name);

    unset($view->vid);
    $configuration->setData($view);
    $configuration->addModule('views');

    $event = $this->triggerEvent('load_from_database', $configuration);

    return $event->configuration;
  }

  public function writeToDatabase(Configuration $configuration) {
    $name = $this->getInternalId($configuration->getIdentifier());

    $event = $this->triggerEvent('write_to_database', $configuration);

    $this->configuration_manager->drupal()->views_save_view($event->configuration->getData());
  }

  public function removeFromDatabase(Configuration $configuration) {
    $name = $this->getInternalId($configuration->getIdentifier());

    $event = $this->triggerEvent('remove_from_database', $configuration);
    $view = $this->configuration_manager->drupal()->views_get_view($name);
    if (!empty($view)) {
      $this->configuration_manager->drupal()->views_delete_view($view);
    }
  }

  protected function jsonAsArray() {
    return TRUE;
  }

  protected function importFromJsonAsArray($file_content) {
    // Load the view as an array, it will be converted to proper views objects later.
    $array = json_decode($file_content, TRUE);

    $configuration_data = $array['data'];
    // Convert the array into objects that Views modules recognizes.
    $view = $this->configuration_manager->drupal()->views_new_view();
    $view->vid = NULL;
    foreach ($configuration_data['display'] as $display) {
      $view->add_display($display['display_plugin'], $display['display_title'], $display['id']);
    }

    foreach ($configuration_data as $property => $value) {
      if ($property != 'display') {
        $view->$property = $value;
      }
      else {
        foreach ($configuration_data['display'] as $id => $display_settings) {
          foreach ($display_settings as $key => $value){
            $view->display[$id]->$key = $value;
          }
        }
      }
    }

    $object = new \stdClass;
    $object->identifier = $array['identifier'];
    $object->notes = $array['notes'];
    $object->tags = $array['tags'];
    $object->dependencies = $array['dependencies'];
    $object->parts = $array['parts'];
    $object->modules = $array['modules'];
    $object->data = $view;
    unset($array);
    return $object;
  }

}
