<?php

namespace Configuration\Handlers;

use Configuration\Configuration;
use Configuration\Handlers\ConfigurationHandler;


class MenuConfigurationHandler extends ConfigurationHandler {

  static public function getSupportedTypes() {
    return array('menu');
  }

  public function getIdentifiers() {
    return $this->configuration_manager->drupal()->menu_getIdentifiers();
  }

  public function loadFromDatabase($identifier) {
    $name = $this->getInternalId($identifier);

    $menu = $this->configuration_manager->drupal()->menu_load(str_replace('_', '-', $name));
    $configuration = new Configuration;
    $configuration->setIdentifier($identifier);
    $configuration->setData($menu);

    $event = $this->triggerEvent('load_from_database', $configuration);

    return $event->configuration;
  }

  public function writeToDatabase(Configuration $configuration) {
    $name = $this->getInternalId($configuration->getIdentifier());

    $event = $this->triggerEvent('write_to_database', $configuration);

    $this->configuration_manager->drupal()->menu_save($event->configuration->getData());
  }

  public function removeFromDatabase(Configuration $configuration) {
    $name = $this->getInternalId($configuration->getIdentifier());

    $event = $this->triggerEvent('remove_from_database', $configuration);

    $this->configuration_manager->drupal()->menu_delete($event->configuration->getData());
  }

  public static function getSubscribedEvents() {
    return array(
      'load_from_database.menu_link' => array('onMenuLinkLoad', 0),
    );
  }

  public function onMenuLinkLoad($event) {
    $menu_link = $event->configuration->getData();
    // Only for menu links in the parent level
    if (empty($menu_link['plid'])) {
      $menu_name = 'menu.' . str_replace('-', '_', $menu_link['menu_name']);
      $this->configuration_manager->newDependency($event->configuration, $menu_name);
    }
  }

  protected function jsonAsArray() {
    return TRUE;
  }


}
