<?php

namespace Configuration\Handlers;

use Configuration\Configuration;
use Configuration\Handlers\ConfigurationHandler;


class RoleConfigurationHandler extends ConfigurationHandler {


  static public function getSupportedTypes() {
    return array('role');
  }

  protected function registerProcessors() {
    foreach (\Configuration\Processors\RoleProcessor::availableProcessors() as $name) {
      $processor = new \Configuration\Processors\RoleProcessor($name, $this->configuration_manager);
      $this->configuration_manager->registerProcessor($name, $processor);
    }
  }

  public function getIdentifiers() {
    $identifiers = array(
      'anonymous_user' => t('Anonymous user'),
      'authenticated_user' => t('Authenticated user'),
    );
    foreach ($this->configuration_manager->drupal()->role_export_roles() as $role) {
      if ($role->rid > 2) {
        $identifiers[$role->machine_name] = $role->name;
      }
    }
    return $identifiers;
  }

  public function loadFromDatabase($identifier) {
    $name = $this->getInternalId($identifier);

    $configuration = new Configuration;
    $configuration->setIdentifier($identifier);
    foreach ($this->configuration_manager->drupal()->role_export_roles() as $role) {
      if ($role->machine_name == $name) {
        unset($role->rid);
        $configuration->setData($role);
        $configuration->addModule('role_export');
        break;
      }
    }

    $event = $this->triggerEvent('load_from_database', $configuration);

    return $event->configuration;
  }

  public function writeToDatabase(Configuration $configuration) {
    $name = $this->getInternalId($configuration->getIdentifier());

    if ($name == 'anonymous_user' || $name == 'authenticated_user') {
      return;
    }

    $event = $this->triggerEvent('write_to_database', $configuration);

    $role = $event->configuration->getData();
    $existent_role = $this->configuration_manager->drupal()->role_roleExists($name);
    if ($existent_role) {
      // Updating an existent role.
      $role->rid = $existent_role;
    }
    $this->configuration_manager->drupal()->user_role_save($role);
  }

  public function removeFromDatabase(Configuration $configuration) {
    $name = $this->getInternalId($configuration->getIdentifier());

    if ($name == 'anonymous_user' || $name == 'authenticated_user') {
      return;
    }

    $event = $this->triggerEvent('remove_from_database', $configuration);

    $role = $event->configuration->getData();
    $this->configuration_manager->drupal()->user_role_delete($role);
  }

  public static function getSubscribedEvents() {
    return array(
      'load_from_database.permission' => array('onPermissionLoad', 0),
    );
  }

  public function onPermissionLoad($event) {
    $permission = $event->configuration->getData();
    foreach ($permission['roles'] as $role) {
      $this->configuration_manager->newDependency($event->configuration, 'role.' . $role);
    }
  }
}
