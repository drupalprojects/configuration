<?php

/**
 * @file
 * Definition of Drupal\configuration\Config\PermissionConfiguration.
 */

namespace Drupal\configuration\Config;

use Drupal\configuration\Config\Configuration;
use Drupal\configuration\Utils\ConfigIteratorSettings;

class PermissionConfiguration extends Configuration {

  /**
   * Overrides Drupal\configuration\Config\Configuration::getComponentHumanName().
   */
  static public function getComponentHumanName($component, $plural = FALSE) {
    return $plural ? t('Permissions') : t('Permission');
  }
  /**
   * Overrides Drupal\configuration\Config\Configuration::getComponent().
   */
  public function getComponent() {
    return 'permission';
  }
  /**
   * Overrides Drupal\configuration\Config\Configuration::supportedComponents().
   */
  static public function supportedComponents() {
    return array('permission');
  }

  /**
   * Returns the original permission based of the identifier of the
   * configuration.
   *
   * @param  string $identifier
   *   The configuration identifier.
   *
   *
   * @return string
   *   The original permissions with spaces.
   */
  public static function getPermissionById($identifier) {
    $perms = static::getAllIdentifiers('permission');
    if (!empty($perms[$identifier])) {
      return $perms[$identifier];
    }
  }

  /**
   * Returns all the identifiers available for this component.
   */
  public static function getAllIdentifiers($component) {
    $return = array();
    $permissions = array_keys(module_invoke_all('permission'));
    foreach ($permissions as $permission) {
      $return[str_replace(' ', '_', $permission)] = $permission;
    }
    return $return;
  }

  /**
   * Overrides Drupal\configuration\Config\Configuration::alterDependencies().
   */
  public static function alterDependencies(Configuration $config, &$stack) {
    if ($config->getComponent() == 'permission') {

      foreach (node_permissions_get_configured_types() as $type) {
        foreach (array_keys(node_list_permissions($type)) as $permission) {
          $data = $config->getData();
          if ($permission == $data['permission']) {
            $content_type = ConfigurationManagement::createConfigurationInstance('content_type.' . $type);
            $config->addToDependencies($content_type);
            break;
          }
        }
      }
    }
    if ($config->getComponent() == 'content_type') {
      $permissions = node_list_permissions($config->getIdentifier());

      foreach (array_keys($permissions) as $permission) {
        $identifier = str_replace(' ', '_', $permission);
        // Avoid include multiple times the same dependency.
        if (empty($stack['permission.' . $identifier])) {
          $perm = new PermissionConfiguration($identifier);
          $perm->build();

          // Add the content type as a dependency of the permission.
          $perm->addToDependencies($config);

          // Add the permission as a child configuration of the content type
          // The permission is not required to load the content type but is
          // a nice to have.
          $config->addToOptionalConfigurations($perm);
          $stack['permission.' . $identifier] = TRUE;
        }
      }
    }
    elseif ($config->getComponent() == 'text_format') {
      $format = $config->getData();
      $permission = filter_permission_name($format);
      if (!empty($permission)) {
        $identifier = str_replace(' ', '_', $permission);
        // Avoid include multiple times the same dependency.
        if (empty($stack['permission.' . $identifier])) {
          $perm = new PermissionConfiguration($identifier);
          $perm->build();

          // Add the text format as a dependency of the permission.
          $perm->addToDependencies($config);

          // Add the permission as a child configuration of the filter
          // The permission is not required to load the filter format but is
          // a nice to have.
          $config->addToOptionalConfigurations($perm);
          $stack['permission.' . $identifier] = TRUE;
        }
      }
    }
  }

  /**
   * Overrides Drupal\configuration\Config\Configuration::findRequiredModules().
   */
  public function findRequiredModules() {
    $perm_modules = user_permission_get_modules();
    $this->addToModules($perm_modules[$this->data['permission']]);
  }

  /**
   * Generate $rid => $role with role names untranslated.
   */
  static protected function get_roles($builtin = TRUE) {
    $roles = array();
    foreach (user_roles() as $rid => $name) {
      switch ($rid) {
        case DRUPAL_ANONYMOUS_RID:
          if ($builtin) {
            $roles[$rid] = 'anonymous user';
          }
          break;
        case DRUPAL_AUTHENTICATED_RID:
          if ($builtin) {
            $roles[$rid] = 'authenticated user';
          }
          break;
        default:
          $roles[$rid] = $name;
          break;
      }
    }
    return $roles;
  }

  /**
   * Represent the current state of permissions as a perm to role name array map.
   */
  static protected function get_permissions($by_role = TRUE) {
    $map = user_permission_get_modules();
    $roles = static::get_roles();
    $permissions = array();
    foreach (user_role_permissions($roles) as $rid => $role_permissions) {
      if ($by_role) {
        foreach (array_keys(array_filter($role_permissions)) as $permission) {
          if (isset($map[$permission])) {
            $permissions[$permission][] = $roles[$rid];
          }
        }
      }
      else {
        $permissions[$roles[$rid]] = array();
        foreach ($role_permissions as $permission => $status) {
          if (isset($map[$permission])) {
            $permissions[$roles[$rid]][$permission] = $status;
          }
        }
      }
    }
    return $permissions;
  }

  /**
   * Implements Drupal\configuration\Config\Configuration::prepareBuild().
   */
  protected function prepareBuild() {
    $permissions_roles = $this->get_permissions();
    $permission = static::getPermissionById($this->identifier);
    $this->data = array(
      'permission' => $permission,
      'roles' => !empty($permissions_roles[$permission]) ? $permissions_roles[$permission] : array(),
    );
    return $this;
  }

  /**
   * Implements Drupal\configuration\Config\Configuration::saveToActiveStore().
   */
  public function saveToActiveStore(ConfigIteratorSettings &$settings) {
    node_types_rebuild();

    $roles = static::get_roles();
    $permissions_by_role = static::get_permissions(FALSE);

    $permission = $this->getData();
    $perm = $permission['permission'];
    foreach ($roles as $role) {
      if (in_array($role, $permission['roles'])) {
        $permissions_by_role[$role][$perm] = TRUE;
      }
      else {
        $permissions_by_role[$role][$perm] = FALSE;
      }
    }

    // Write the updated permissions.
    foreach ($roles as $rid => $role) {
      if (isset($permissions_by_role[$role])) {
        user_role_change_permissions($rid, $permissions_by_role[$role]);
      }
    }

    $settings->addInfo('imported', $this->getUniqueId());
  }

}
