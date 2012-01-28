<?php

/**
 * Implements hook_configuration_api().
 */
function user_configuration_api() {
  return array(
    'user_role' => array(
      'name' => t('Roles'),
      'feature_source' => TRUE,
      'default_hook' => 'user_default_roles',
      'default_file' => CONFIGURATION_DEFAULTS_INCLUDED,
    ),
    'user_permission' => array(
      'name' => t('Permissions'),
      'feature_source' => TRUE,
      'default_hook' => 'user_default_permissions',
      'default_file' => CONFIGURATION_DEFAULTS_INCLUDED,
    ),
  );
}

/**
 * Implements hook_configuration_export().
 */
function user_permission_configuration_export($data, &$export, $module_name = '') {
  $export['dependencies']['configuration'] = 'configuration';

  // Ensure the modules that provide the given permissions are included as dependencies.
  $map = user_permission_get_modules();
  foreach ($data as $perm) {
    if (isset($map[$perm])) {
      $perm_module = $map[$perm];
      $export['dependencies'][$perm_module] = $perm_module;
      $export['configuration']['user_permission'][$perm] = $perm;
    }
  }

  return array();
}

/**
 * Implements hook_configuration_export_options().
 */
function user_permission_configuration_export_options() {
  $modules = array();
  $module_info = system_get_info('module');
  foreach (module_implements('permission') as $module) {
    $modules[$module_info[$module]['name']] = $module;
  }
  ksort($modules);

  $options = array();
  foreach ($modules as $display_name => $module) {
    if ($permissions = module_invoke($module, 'permission')) {
      foreach ($permissions as $perm => $perm_item) {
        $options[$perm] = strip_tags("{$display_name}: {$perm_item['title']}");
      }
    }
  }
  return $options;
}

/**
 * Implements hook_configuration_export_render().
 */
function user_permission_configuration_export_render($module, $data) {  
  $perm_modules = &drupal_static(__FUNCTION__ . '_perm_modules');
  if (!isset($perm_modules)) {
    $perm_modules = user_permission_get_modules();
  }
  
  $code = array();
  $code[] = '  $permissions = array();';
  $code[] = '';

  $permissions = _user_configuration_get_permissions();

  foreach ($data as $perm_name) {
    $permission = array();
    $permission['name'] = $perm_name;
    if (isset($permissions[$perm_name])) {
      sort($permissions[$perm_name]);
      $permission['roles'] = $permissions[$perm_name];
      $permission['module'] = $perm_modules[$perm_name];
    }
    else {
      $permission['roles'] = array();
    }
    $perm_identifier = configuration_var_export($perm_name);
    $perm_export = configuration_var_export($permission, '  ');
    $code[] = "  // Exported permission: {$perm_name}";
    $code[] = "  \$permissions[{$perm_identifier}] = {$perm_export};";
    $code[] = "";
  }

  $code[] = '  return $permissions;';
  $code = implode("\n", $code);
  return array('user_default_permissions' => $code);
}

/**
 * Implements hook_configuration_revert().
 */
function user_permission_configuration_revert($module, $module_name = 'configuration') {
  user_permission_configuration_rebuild($module, $module_name);
}

/**
 * Implements hook_configuration_rebuild().
 * Iterate through default permissions and update the permissions map.
 *
 * @param $module
 *   The module whose default user permissions should be rebuilt.
 */
function user_permission_configuration_rebuild($identifiers, $module_name = 'configuration') {
  if ($defaults = configuration_get_default('user_permission', $module_name)) {
    // Make sure the list of available node types is up to date, especially when
    // installing multiple configuration at once, for example from an install profile
    // or via drush.
    node_types_rebuild();

    $roles = _user_configuration_get_roles();
    $permissions_by_role = _user_configuration_get_permissions(FALSE);
    foreach ($defaults as $permission) {
      $perm = $permission['name'];
      if (in_array($perm, $identifiers)) {
        foreach ($roles as $role) {
          if (in_array($role, $permission['roles']) || $identifiers[0] == '#import_all') {
            $permissions_by_role[$role][$perm] = TRUE;
          }
          else {
            $permissions_by_role[$role][$perm] = FALSE;
          }
        }
      }
    }
    // Write the updated permissions.
    foreach ($roles as $rid => $role) {
      if (isset($permissions_by_role[$role])) {
        user_role_change_permissions($rid, $permissions_by_role[$role]);
        
      }
    }
    foreach ($identifiers as $identifier) {
      configuration_check_user_permission($identifier);
    }
    cache_clear_all('config_export', 'cache');
  }
}

/**
 * Implements hook_configuration_export().
 */
function user_role_configuration_export($data, &$export, $module_name = '') {
  $export['dependencies']['configuration'] = 'configuration';
  $map = configuration_get_default_map('user_role', 'name');
  foreach ($data as $role) {
    // Role is provided by another module. Add dependency.
    if (isset($map[$role]) && $map[$role] != $module_name) {
      $export['dependencies'][$map[$role]] = $map[$role];
    }
    // Export.
    if (user_role_load_by_name($role)) {
      $export['configuration']['user_role'][$role] = $role;
    }
  }
  return array();
}

/**
 * Implements hook_configuration_export_options().
 */
function user_role_configuration_export_options() {
  return drupal_map_assoc(_user_configuration_get_roles(FALSE));
}

/**
 * Implements hook_configuration_export_render().
 */
function user_role_configuration_export_render($module, $data) {
  $code = array();
  $code[] = '  $roles = array();';
  $code[] = '';

  foreach ($data as $name) {
    if ($role = user_role_load_by_name($name)) {
      unset($role->rid);
      $role_identifier = configuration_var_export($name);
      $role_export = configuration_var_export($role , '  ');
      $code[] = "  // Exported role: {$name}";
      $code[] = "  \$roles[{$role_identifier}] = {$role_export};";
      $code[] = "";
    }
  }

  $code[] = '  return $roles;';
  $code = implode("\n", $code);
  return array('user_default_roles' => $code);
}

/**
 * Implements hook_configuration_revert().
 */
function user_role_configuration_revert($module, $module_name = 'configuration') {
  user_role_configuration_rebuild($module, $module_name);
}

/**
 * Implements hook_configuration_rebuild().
 */
function user_role_configuration_rebuild($identifiers, $module_name = 'configuration') {
  if ($defaults = configuration_get_default('user_role', $module_name)) {
    foreach ($defaults as $role) {
      if (in_array($role['name'], $identifiers) || $identifiers[0] == '#import_all') {
        $role = (object) $role;
        if ($existing = user_role_load_by_name($role->name)) {
          $role->rid = $existing->rid;
        }
        user_role_save($role);
        configuration_check_user_role($role->name);
      }
    }
    cache_clear_all('config_export', 'cache');
  }
}

/**
 * Generate $rid => $role with role names untranslated.
 */
function _user_configuration_get_roles($builtin = TRUE) {
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
function _user_configuration_get_permissions($by_role = TRUE) {
  $map = user_permission_get_modules();
  $roles = _user_configuration_get_roles();
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

function configuration_check_user_permission($identifier) {
  // Get static variable that we can access across this request.
  $from_activestore = &drupal_static('configuration_from_activestore');
  $component = 'user_permission';
  
  if (file_exists("config://configuration.user_permission.inc")) {
    // Load the current configuration file on disk
    include_once "config://configuration.user_permission.inc";

    module_load_include('inc', 'configuration', "includes/configuration.user");
    module_load_include('inc', 'configuration', 'configuration.export');

    $config = configuration_get_configuration();

    $identifiers = is_array($identifier) ? $identifier : array($identifier);

    // Export the field we just saved and evaluate the export to $fields
    eval(array_pop(user_permission_configuration_export_render('configuration', $identifiers)));

    // Get the permissions on disk.
    $permissions_code = configuration_user_default_permissions();
    
    // If the activestore doesn't exist it is most likely because this configuration
    // only exists in code.
    if (empty($permissions)) {
      configuration_set_status($component, $identifier, CONFIGURATION_TRACKED_DATASTORE_ONLY);
    }

    // Compare what was just exported to what is on the file system.
    /**
     * @todo This code is reused in all component files.
     */
    foreach ($identifiers as $identifier) {
      $component = 'user_permission';
      $status = $config[$component][$identifier]['status'];
      $md5_datastore = (is_array($permissions_code) && array_key_exists($identifier, $permissions_code)) ? md5(serialize($permissions_code[$identifier])) : '';
      $md5_activestore = (is_array($permissions_code) && array_key_exists($identifier, $permissions_code)) ? md5(serialize($permissions[$identifier])) : '';
      
      // Configs in code are not the same as what was just saved in activestore.
      if ($from_activestore == TRUE && $md5_datastore != $md5_activestore) {
        $status = $status | CONFIGURATION_ACTIVESTORE_OVERRIDDEN;
        configuration_set_status($component, $identifier, $status);
      }
      // Menu in the activestore is the same as what is in code.
      if ($md5_activestore == $md5_datastore) {
        $status = CONFIGURATION_IN_SYNC;
        configuration_set_status($component, $identifier, $status);
        configuration_set_hash($component, $identifier, $md5_activestore);
      }
      if ($md5_activestore != $md5_datastore) {
        $status = $status | CONFIGURATION_DATASTORE_OVERRIDDEN;
        configuration_set_status($component, $identifier, $status);
      }
      // When checking for new configurations, check to see if configurations are
      // the same in datastore as last activestore. Remove the datastore overridden.
      if ($md5_datastore == $config[$component][$identifier]['hash']) {
        $status = $status & ~CONFIGURATION_DATASTORE_OVERRIDDEN;
        configuration_set_status($component, $identifier, $status);
      }

      // Store the config array in cache for easy access
      if ($status != CONFIGURATION_IN_SYNC) {
        $configuration[$component][$identifier]['activestore'] = (is_array($permissions_code) && array_key_exists($identifier, $permissions_code)) ? $permissions[$identifier] : '';
        $configuration[$component][$identifier]['datastore'] = (is_array($permissions_code) && array_key_exists($identifier, $permissions_code)) ? $permissions_code[$identifier] : '';
        cache_set("$component:$identifier", $configuration, 'cache_configuration');
      }
    } 
  }
}

function configuration_hash_user_permission($identifier) {
  // Export just the field we're tracking.
  module_load_include('inc', 'configuration', 'configuration.export');

  $data = user_permission_configuration_export_options();

  // Export the field we just saved and evaluate the export to hash
  eval(array_pop(user_permission_configuration_export_render('configuration', array($identifier))));

  return md5(serialize($permissions[$identifier]));
}
/**
 * User roles are unique in that the name of the config and the name of the role
 * are the same.  If you change the name of the role, you are effectively
 * creating a new configuration item.  For that reason, we have to check to
 * see what roles are no longer existent in the activestore, and mark those
 * as changed.
 */
function configuration_check_user_role($identifier) {
  // Get static variable that we can access across this request.
  $from_activestore = &drupal_static('configuration_from_activestore');
  $component = 'user_role';
  
  if (file_exists("config://configuration.user_role.inc")) {
    // Load the current configuration file on disk
    include_once "config://configuration.user_role.inc";

    $roles = user_roles();

    // Export just the field we're tracking.
    module_load_include('inc', 'configuration', 'configuration.export');

    $config = configuration_get_configuration('user_role');

    // Export the field we just saved and evaluate the export to $fields
    eval(array_pop(user_role_configuration_export_render('configuration', array($identifier))));

    // If the activestore doesn't exist it is most likely because this configuration
    // only exists in code.
    if (empty($roles)) {
      configuration_set_status($component, $identifier, CONFIGURATION_TRACKED_DATASTORE_ONLY);
    }

    // Get the permissions on disk.
    $roles_code = configuration_user_default_roles();

    $return = '';
    $component = 'user_role';
    $status = $config[$identifier]['status'];
    // Configs in code are not the same as what was just saved in activestore.
    if ($from_activestore == TRUE) {
      $roles = user_roles();
      foreach (array_keys($config) as $identifier) {
        if (!in_array($identifier, $roles)) {
          // dsm('configs in code are not the same as what was just saved in activestore.');
          $status = $status | CONFIGURATION_DELETE;
          configuration_set_status($component, $identifier, $status);
        }
      }
    }
    $md5_datastore = (is_array($roles_code) && array_key_exists($identifier, $roles_code)) ? md5(serialize($roles_code[$identifier])) : '';
    $md5_activestore = (is_array($roles_code) && array_key_exists($identifier, $roles_code)) ? md5(serialize($roles[$identifier])) : '';
    
    // When checking for new configurations, check to see if configurations are
    // the same in datastore as last activestore
    if (!$from_activestore && (isset($config[$identifier]) && isset($roles_code[$identifier])) && $md5_datastore == $config[$identifier]['hash']) {
      $status = $status & ~CONFIGURATION_DATASTORE_OVERRIDDEN;
      configuration_set_status($component, $identifier, $status);
    }
    // Menu in the activestore is the same as what is in code.
    if ((isset($roles[$identifier]) && isset($roles_code[$identifier])) && $md5_activestore == $md5_datastore) {
      $status = CONFIGURATION_IN_SYNC;
      configuration_set_status($component, $identifier, $status);
      configuration_set_hash($component, $identifier, $md5_activestore);
    }

    // Compare what was just exported to what is on the file system.
    if ((isset($roles[$identifier]) && isset($roles_code[$identifier])) && ($md5_activestore != $md5_datastore)) {
      $status = $settings['status'] | CONFIGURATION_DATASTORE_OVERRIDDEN;
      configuration_set_status($component, $identifier, $status);
    }
    
    // Store the config array in cache for easy access
    if ($status != CONFIGURATION_IN_SYNC) {
      // Supress error, there may not be anything in activestore
      $configuration[$component][$identifier]['activestore'] = (is_array($roles_code) && array_key_exists($identifier, $roles_code)) ? $roles[$identifier] : '';
      $configuration[$component][$identifier]['datastore'] = (is_array($roles_code) && array_key_exists($identifier, $roles_code)) ? $roles_code[$identifier] : '';
      cache_set("$component:$identifier", $configuration, 'cache_configuration');
    }

    // Look for new configurations that the system doesn't know about
    foreach (array_keys($roles_code) as $name) {
      if (!in_array($name, array_keys($config))) {
        configuration_add_status('user_role', $name, md5(serialize($roles_code[$name])));
      }
    }
  }
}

function configuration_hash_user_role($identifier) {
  // Export just the field we're tracking.
  module_load_include('inc', 'configuration', 'configuration.export');

  $data = user_role_configuration_export_options();

  // Export the field we just saved and evaluate the export to hash
  eval(array_pop(user_role_configuration_export_render('configuration', array($identifier))));

  return md5(serialize($roles[$identifier]));
}