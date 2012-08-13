<?php

/**
 * @file
 * Definition of Drupal\configuration\Config\PermissionConfiguration.
 */

namespace Drupal\configuration\Config;

use Drupal\configuration\Config\Configuration;

class PermissionConfiguration extends Configuration {

  function __construct($identifier) {
    parent::__construct('permission', $identifier);
    $this->storage->setFileName('permission.' . str_replace(' ', '_', $identifier) . '.inc');
  }

  function build($include_dependencies = TRUE) {
    $permissions = module_invoke_all('permission');
    $permissions_roles = $this->get_permissions();
    $this->data = array(
      'definition' => $permissions[$this->identifier],
      'roles' => $permissions_roles[$this->identifier],
    );
    if ($include_dependencies) {
      $this->findDependencies();
    }
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
          $field = new PermissionConfiguration($identifier);
          $field->build();
          $config->addToDependencies($field);
          $stack['permission.' . $identifier] = TRUE;
        }
      }
    }
  }

  /**
   * Generate $rid => $role with role names untranslated.
   */
  protected function get_roles($builtin = TRUE) {
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
  protected function get_permissions($by_role = TRUE) {
    $map = user_permission_get_modules();
    $roles = $this->get_roles();
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
}
