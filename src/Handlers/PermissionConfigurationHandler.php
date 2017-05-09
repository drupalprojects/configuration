<?php

namespace Configuration\Handlers;

use Configuration\Configuration;
use Configuration\Handlers\ConfigurationHandler;


class PermissionConfigurationHandler extends ConfigurationHandler {

  // Map Role ID -> Role Name.
  protected $roles_names;

  // Map Role Name -> Role ID.
  protected $roles_ids;

  // An array of all the available permissions of the site.
  protected $cached_permissions;

  // Map Permission -> Module that provides the permission via hook_permissions.
  protected $cached_permissions_modules;

  public function __construct($type, $configuration_manager) {
    parent::__construct($type, $configuration_manager);

    // This cache never changes since is only modified when installing new modules.
    $this->cached_permissions_modules = $this->configuration_manager->drupal()->user_permission_get_modules();

    $this->buildCache();
  }

  protected function buildCache() {
    $this->roles_names = array(
      DRUPAL_ANONYMOUS_RID => 'anonymous_user',
      DRUPAL_AUTHENTICATED_RID => 'authenticated_user',
    );
    $this->roles_ids = array(
      'anonymous_user' => DRUPAL_ANONYMOUS_RID,
      'authenticated_user' => DRUPAL_AUTHENTICATED_RID
    );
    foreach ($this->configuration_manager->drupal()->role_export_roles() as $role) {
      if ($role->rid > 2) {
        $this->roles_ids[$role->machine_name] = $role->rid;
        $this->roles_names[$role->rid] = $role->machine_name;
      }
    }

    $this->cached_permissions = array();
    foreach ($this->cached_permissions_modules as $permission => $module) {
      $id = preg_replace("/[^a-zA-Z0-9]+/", "_", $permission);
      $this->cached_permissions[$id] = array(
        'permission' => $permission,
        'module' => $module,
        'roles' => array(),
      );
    }

    foreach ($this->configuration_manager->drupal()->user_role_permissions($this->roles_names) as $rid => $role_permissions) {
      foreach (array_keys($role_permissions) as $permission) {
        $id = preg_replace("/[^a-zA-Z0-9]+/", "_", $permission);
        if (isset($this->cached_permissions_modules[$permission])) {
          $this->cached_permissions[$id]['roles'][] = $this->roles_names[$rid];
        }
      }
    }
  }

  static public function getSupportedTypes() {
    return array('permission');
  }

  public function getIdentifiers() {
    $return = array();
    $permissions = $this->configuration_manager->drupal()->module_invoke_all('permission');
    foreach ($permissions as $permission => $info) {
      $id = preg_replace("/[^a-zA-Z0-9]+/", "_", $permission);
      $return[$id] = $permission;
    }
    return $return;
  }

  public function loadFromDatabase($identifier) {
    $name = $this->getInternalId($identifier);

    $configuration = new Configuration;
    $configuration->setIdentifier($identifier);
    $configuration->setData($this->cached_permissions[$name]);
    $configuration->addModule($this->cached_permissions[$name]['module']);

    $event = $this->triggerEvent('load_from_database', $configuration);

    return $event->configuration;
  }

  public function writeToDatabase(Configuration $configuration) {
    $name = $this->getInternalId($configuration->getIdentifier());

    $event = $this->triggerEvent('write_to_database', $configuration);
    $permission = $event->configuration->getData();

    // Delete all the configured roles for this permission.
    $this->configuration_manager->drupal()->permission_deletePermission($permission['permission']);

    $this->configuration_manager->drupal()->permission_savePermission($permission);

    // Clear the user access cache.
    $this->configuration_manager->drupal()->drupal_static_reset('user_access');
    $this->configuration_manager->drupal()->drupal_static_reset('user_role_permissions');
  }

  public function removeFromDatabase(Configuration $configuration) {
    // Revoke access to all the roles for this permission.
    $event = $this->triggerEvent('remove_from_database', $configuration);

    $permission = $event->configuration->getData();

    $this->configuration_manager->drupal()->permission_deletePermission($permission['permission']);

    // Clear the user access cache.
    $this->configuration_manager->drupal()->drupal_static_reset('user_access');
    $this->configuration_manager->drupal()->drupal_static_reset('user_role_permissions');
  }

  public static function getSubscribedEvents() {
    return array(
      'load_from_database.content_type' => array('onContentTypeLoad', 0),
      'modules_installed' => array('onModulesInstalled', 0),
    );
  }

  public function onContentTypeLoad($event) {
    $type = $this->getInternalId($event->configuration->getIdentifier());

    $node_list_permissions = array(
      "create $type content",
      "edit own $type content",
      "edit any $type content",
      "delete own $type content",
      "delete any $type content",
    );

    foreach ($node_list_permissions as $permission) {
      $id = preg_replace("/[^a-zA-Z0-9]+/", "_", $permission);
      $this->configuration_manager->newDependency($event->configuration, 'permission.' . $id);
    }
  }

  public function onModulesInstalled($event) {
    $modules = $event->getModules();
    if (!empty($modules)) {
      $this->cached_permissions_modules = $this->configuration_manager->drupal()->user_permission_get_modules();
    }
  }

  protected function jsonAsArray() {
    return TRUE;
  }

}
