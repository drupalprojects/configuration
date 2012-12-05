<?php

/**
 * @file
 * Definition of Drupal\configuration\Config\RoleConfiguration.
 */

namespace Drupal\configuration\Config;

use Drupal\configuration\Config\Configuration;
use Drupal\configuration\Utils\ConfigIteratorSettings;

class RoleConfiguration extends Configuration {

  public static function isActive() {
    return module_exists('role_export');
  }

  static public function getComponentHumanName($component, $plural = FALSE) {
    return $plural ? t('Roles') : t('Role');
  }

  public function getComponent() {
    return 'role';
  }

  static public function supportedComponents() {
    return array('role');
  }

  public function findRequiredModules() {
    $this->addToModules('role_export');
  }

  /**
   * Returns all the identifiers available for this component.
   */
  public static function getAllIdentifiers($component) {
    $identifiers = array();
    foreach (role_export_roles() as $role) {
      if (!empty($role->machine_name)) {
        $identifiers[$role->machine_name] = $role->name;
      }
    }
    return $identifiers;
  }

  protected function prepareBuild() {
    foreach (role_export_roles() as $role) {
      if ($role->machine_name == $this->getIdentifier()) {
        $this->data = $role;
        unset($role->rid);
        break;
      }
    }
    return $this;
  }

  public function saveToActiveStore(ConfigIteratorSettings &$settings) {
    $role = $this->getData();
    if (!empty($role->machine_name) && $existing = db_query("SELECT rid FROM {role} WHERE machine_name = :machine_name", array(':machine_name' => $role->machine_name))->fetchField()) {
      $role->rid = $existing;
    }
    user_role_save($role);
    $settings->addInfo('imported', $this->getUniqueId());
  }

  public static function alterDependencies(Configuration $config, &$stack) {
    if ($config->getComponent() == 'permission') {
      $data = $config->getData();
      if (!empty($data['roles'])) {
        $role_objects = db_select('role', 'r')
                        ->fields('r', array(
                          'machine_name',
                          'rid',
                        ))
                        ->condition('name', $data['roles'], 'IN')
                        ->execute();
        foreach ($role_objects as $role_object) {
          if ($role_object->rid > 2 && !empty($role_object->machine_name)) {
            $role_config = ConfigurationManagement::createConfigurationInstance('role.' . $role_object->machine_name);
            $config->addToDependencies($role_config);
          }
        }
      }
    }
  }
}
