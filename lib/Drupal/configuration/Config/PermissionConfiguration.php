<?php

/**
 * @file
 * Definition of Drupal\configuration\Config\PermissionConfiguration.
 */

namespace Drupal\configuration\Config;

use Drupal\configuration\Config\Configuration;

class PermissionConfiguration extends Configuration {

  static protected $component = 'permission';

  // Store the original permission before remove white spaces
  protected $permission;

  function __construct($identifier) {
    $this->permission = $identifier;
    parent::__construct(str_replace(' ', '_', $identifier));

    $this->storage->setFileName('permission.' . str_replace(' ', '_', $identifier));
  }

  protected function prepareBuild() {
    $permissions_roles = $this->get_permissions();
    $this->data = array(
      'permission' => $this->permission,
      'roles' => !empty($permissions_roles[$this->permission]) ? $permissions_roles[$this->permission] : array(),
    );
    return $this;
  }

  /**
   * Returns all the identifiers available for this component.
   */
  public static function getAllIdentifiers() {
    return array_keys(module_invoke_all('permission'));
  }

  public static function alterDependencies(Configuration $config, &$stack) {
    if ($config->getComponent() == 'content_type') {
      $permissions = node_list_permissions($config->getIdentifier());

      foreach ($permissions as $identifier => $permission) {
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
  }

  public function findRequiredModules() {
    $perm_modules = user_permission_get_modules();
    $this->addToModules($perm_modules[$this->permission]);
  }

  /**
   * Generate $rid => $role with role names untranslated.
   */
  static  protected function get_roles($builtin = TRUE) {
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
   * There is no default hook for permission. This function set the
   * permissions to the defined roles.
   */
  static public function rebuildHook($permissions = array()) {

    if ($permissions) {
      // Make sure the list of available node types is up to date, especially when
      // installing multiple features at once, for example from an install profile
      // or via drush.
      node_types_rebuild();

      $roles = static::get_roles();
      $permissions_by_role = static::get_permissions(FALSE);
      foreach ($permissions as $serialized_permission) {
        $permission = unserialize($serialized_permission->data);
        $perm = $permission['permission'];
        foreach ($roles as $role) {
          if (in_array($role, $permission['roles'])) {
            $permissions_by_role[$role][$perm] = TRUE;
          }
          else {
            $permissions_by_role[$role][$perm] = FALSE;
          }
        }
      }
      // Write the updated permissions.
      foreach ($roles as $rid => $role) {
        if (isset($permissions_by_role[$role])) {
          user_role_change_permissions($rid, $permissions_by_role[$role]);
        }
      }
    }
  }
}
