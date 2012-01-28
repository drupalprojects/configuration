<?php

/**
 * Implements hook_configuration_api().
 */
function menu_configuration_api() {
  return array(
    'menu_custom' => array(
      'name' => t('Menus'),
      'default_hook' => 'menu_default_menu_custom',
      'feature_source' => TRUE,
      'default_file' => CONFIGURATION_DEFAULTS_INCLUDED,
    ),
    'menu_links' => array(
      'name' => t('Menu links'),
      'default_hook' => 'menu_default_menu_links',
      'feature_source' => TRUE,
      'default_file' => CONFIGURATION_DEFAULTS_INCLUDED,
    ),
    // DEPRECATED
    'menu' => array(
      'name' => t('Menu items'),
      'default_hook' => 'menu_default_items',
      'default_file' => CONFIGURATION_DEFAULTS_INCLUDED,
      'feature_source' => FALSE,
    ),
  );
}

/**
 * Implements hook_configuration_export().
 * DEPRECATED: This implementation simply migrates deprecated `menu` items
 * to the `menu_links` type.
 */
function menu_configuration_export($data, &$export, $module_name = '') {
  $pipe = array();
  foreach ($data as $path) {
    $pipe['menu_links'][] = "configuration:{$path}";
  }
  return $pipe;
}

/**
 * Implements hook_configuration_export_options().
 */
function menu_custom_configuration_export_options() {
  $options = array();
  $result = db_query("SELECT * FROM {menu_custom} ORDER BY title", array(), array('fetch' => PDO::FETCH_ASSOC));
  foreach ($result as $menu) {
    $options[$menu['menu_name']] = $menu['title'];
  }
  return $options;
}

/**
 * Implements hook_configuration_export().
 */
function menu_custom_configuration_export($data, &$export, $module_name = '') {
  // Default hooks are provided by the feature module so we need to add
  // it as a dependency.
  $export['dependencies']['configuration'] = 'configuration';
  $export['dependencies']['menu'] = 'menu';

  // Collect a menu to module map
  $pipe = array();
  $map = configuration_get_default_map('menu_custom', 'menu_name');
  foreach ($data as $menu_name) {
    // If this menu is provided by a different module, add it as a dependency.
    if (isset($map[$menu_name]) && $map[$menu_name] != $module_name) {
      $export['dependencies'][$map[$menu_name]] = $map[$menu_name];
    }
    $export['configuration']['menu_custom'][$menu_name] = $menu_name;
  }
  return $pipe;
}

/**
 * Implements hook_configuration_export_render()
 */
function menu_custom_configuration_export_render($module, $data) {
  $code = array();
  $code[] = '  $menus = array();';
  $code[] = '';

  $translatables = array();
  foreach ($data as $menu_name) {
    $row = db_select('menu_custom')
      ->fields('menu_custom')
      ->condition('menu_name', $menu_name)
      ->execute()
      ->fetchAssoc();
    if ($row) {
      $export = configuration_var_export($row, '  ');
      $code[] = "  // Exported menu: {$menu_name}";
      $code[] = "  \$menus['{$menu_name}'] = {$export};";
      $translatables[] = $row['title'];
      $translatables[] = $row['description'];
    }
  }
  if (!empty($translatables)) {
    $code[] = configuration_translatables_export($translatables, '  ');
  }

  $code[] = '';
  $code[] = '  return $menus;';
  $code = implode("\n", $code);
  return array('menu_default_menu_custom' => $code);
}

/**
 * Implements hook_configuration_export_revert().
 */
function menu_custom_configuration_revert($identifiers, $module_name = 'configuration') {
  menu_custom_configuration_rebuild($identifiers, $module_name);
}

/**
 * Implements hook_configuration_export_rebuild().
 */
function menu_custom_configuration_rebuild($identifiers, $module_name = 'configuration') {
  if ($defaults = configuration_get_default('menu_custom', $module_name)) {
    foreach ($defaults as $identifier => $menu) {
      if (in_array($identifier, $identifiers) || $identifiers[0] == '#import_all') {
        menu_save($menu);
      }
    }
  }
}

/**
 * Implements hook_configuration_export_options().
 */
function menu_links_configuration_export_options() {
  global $menu_admin;
  // Need to set this to TRUE in order to get menu links that the
  // current user may not have access to (i.e. user/login)
  $menu_admin = TRUE;
  $menu_links = menu_parent_options(menu_get_menus(), array('mlid' => 0));
  $options = array();
  foreach ($menu_links as $key => $name) {
    list($menu_name, $mlid) = explode(':', $key, 2);
    if ($mlid != 0) {
      $link = menu_link_load($mlid);
      $identifier = menu_links_configuration_identifier($link);
      $options[$identifier] = "{$menu_name}: {$name}";
    }
  }
  $menu_admin = FALSE;
  return $options;
}

/**
 * Callback for generating the menu link exportable identifier.
 */
function menu_links_configuration_identifier($link) {
  return isset($link['menu_name'], $link['link_path']) ? "{$link['menu_name']}:{$link['link_path']}" : FALSE;
}

/**
 * Implements hook_configuration_export().
 */
function menu_links_configuration_export($data, &$export, $module_name = '') {
  // Default hooks are provided by the feature module so we need to add
  // it as a dependency.
  $export['dependencies']['configuration'] = 'configuration';
  $export['dependencies']['menu'] = 'menu';

  // Collect a link to module map
  $pipe = array();
  $map = configuration_get_default_map('menu_links', 'menu_links_configuration_identifier');
  foreach ($data as $identifier) {
    if ($link = configuration_menu_link_load($identifier)) {
      // If this link is provided by a different module, add it as a dependency.
      if (isset($map[$identifier]) && $map[$identifier] != $module_name) {
        $export['dependencies'][$map[$identifier]] = $map[$identifier];
      }

      $export['configuration']['menu_links'][$identifier] = $identifier;
      // For now, exclude a variety of common menus from automatic export.
      // They may still be explicitly included in a Feature if the builder
      // chooses to do so.
      if (!in_array($link['menu_name'], array('configuration', 'primary-links', 'secondary-links', 'navigation', 'admin', 'devel'))) {
        $pipe['menu_custom'][] = $link['menu_name'];
      }
    }
  }
  return $pipe;
}

/**
 * Implements hook_configuration_export_render()
 */
function menu_links_configuration_export_render($module, $data) {
  $code = array();
  $code[] = '  $menu_links = array();';
  $code[] = '';

  $translatables = array();
  foreach ($data as $identifier) {
    if ($link = configuration_menu_link_load($identifier)) {
      // Replace plid with a parent path.
      if (!empty($link['plid']) && $parent = menu_link_load($link['plid'])) {
        $link['parent_path'] = $parent['link_path'];
      }
      unset($link['plid']);
      unset($link['mlid']);

      $code[] = "  // Exported menu link: {$identifier}";
      $code[] = "  \$menu_links['{$identifier}'] = ". configuration_var_export($link, '  ') .";";
      $translatables[] = $link['link_title'];
    }
  }
  if (!empty($translatables)) {
    $code[] = configuration_translatables_export($translatables, '  ');
  }

  $code[] = '';
  $code[] = '  return $menu_links;';
  $code = implode("\n", $code);
  return array('menu_default_menu_links' => $code);
}

/**
 * Implements hook_configuration_export_revert().
 */
function menu_links_configuration_revert($module, $module_name = 'configuration') {
  menu_links_configuration_rebuild($module, $module_name);
}

/**
 * Implements hook_configuration_export_rebuild().
 */
function menu_links_configuration_rebuild($identifiers, $module_name = 'configuration') {
  if ($menu_links = configuration_get_default('menu_links', $module_name)) {
    $links = array();
    foreach ($identifiers as $identifier) {
      if (array_key_exists($identifier, $menu_links) || $identifiers[0] == '#import_all') {
        $links[$identifier] = $menu_links[$identifier];
      }
    }
    menu_links_configuration_rebuild_ordered($links);
  }
}

/**
 * Generate a depth tree of all menu links.
 */
function menu_links_configuration_rebuild_ordered($menu_links, $reset = FALSE) {
  static $ordered;
  static $all_links;
  if (!isset($ordered) || $reset) {
    $ordered = array();
    $unordered = configuration_get_default('menu_links');

    // Order all links by depth.
    if ($unordered) {
      do {
        $current = count($unordered);
        foreach ($unordered as $key => $link) {
          $identifier = menu_links_configuration_identifier($link);
          $parent = isset($link['parent_path']) ? "{$link['menu_name']}:{$link['parent_path']}" : '';
          if (empty($parent)) {
            $ordered[$identifier] = 0;
            $all_links[$identifier] = $link;
            unset($unordered[$key]);
          }
          elseif (isset($ordered[$parent])) {
            $ordered[$identifier] = $ordered[$parent] + 1;
            $all_links[$identifier] = $link;
            unset($unordered[$key]);
          }
        }
      } while (count($unordered) < $current);
    }
    asort($ordered);
  }

  // Ensure any default menu items that do not exist are created.
  foreach (array_keys($ordered) as $identifier) {
    $link = $all_links[$identifier];
    $existing = configuration_menu_link_load($identifier);
    if (!$existing || in_array($link, $menu_links)) {
      // Retrieve the mlid if this is an existing item.
      if ($existing) {
        $link['mlid'] = $existing['mlid'];
      }
      // Retrieve the plid for a parent link.
      if (!empty($link['parent_path']) && $parent = configuration_menu_link_load("{$link['menu_name']}:{$link['parent_path']}")) {
        $link['plid'] = $parent['mlid'];
      }
      else {
        $link['plid'] = 0;
      }
      menu_link_save($link);
    }
  }
}

/**
 * Load a menu link by its menu_name:link_path identifier.
 */
function configuration_menu_link_load($identifier) {
  list($menu_name, $link_path) = explode(':', $identifier, 2);
  $link = db_select('menu_links')
    ->fields('menu_links', array('menu_name', 'mlid', 'plid', 'link_path', 'router_path', 'link_title', 'options', 'module', 'hidden', 'external', 'has_children', 'expanded', 'weight'))
    ->condition('menu_name', $menu_name)
    ->condition('link_path', $link_path)
    ->execute()
    ->fetchAssoc();
  if ($link) {
    $link['options'] = unserialize($link['options']);
    return $link;
  }
  return FALSE;
}

function configuration_check_menu_links($identifier) {
  // Get static variable that we can access across this request.
  $from_activestore = &drupal_static('configuration_from_activestore');
  $component = 'menu_links';
  
  if (file_exists("config://configuration.menu_links.inc")) {
    // Load the current configuration file on disk
    include_once "config://configuration.menu_links.inc";

    // Export just the field we're tracking.
    module_load_include('inc', 'configuration', "configuration.export");

    // Export the field we just saved and evaluate the export to $fields
    eval(array_pop(menu_links_configuration_export_render('configuration', array($identifier))));

    $menu_links_code = configuration_menu_default_menu_links();
    
    // If the activestore doesn't exist it is most likely because this configuration
    // only exists in code.
    if (empty($menu_links)) {
      configuration_set_status($component, $identifier, CONFIGURATION_TRACKED_DATASTORE_ONLY);
    }

    // If this was the previous configuration in activestore don't mark this as changed.
    $config = configuration_get_configuration();
    /**
     * @todo This code is reused in all component files.
     */
    $return = '';
    $component = 'menu_links';
    $status = $config[$component][$identifier]['status'];
    $md5_datastore = (is_array($menu_links_code) && array_key_exists($identifier, $menu_links_code)) ? md5(serialize($menu_links_code[$identifier])) : '';
    $md5_activestore = (is_array($menu_links_code) && array_key_exists($identifier, $menu_links_code)) ? md5(serialize($menu_links[$identifier])) : '';
    
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
      $configuration[$component][$identifier]['activestore'] = (is_array($menu_links_code) && array_key_exists($identifier, $menu_links_code)) ? $menu_links[$identifier] : '';
      $configuration[$component][$identifier]['datastore'] = (is_array($menu_links_code) && array_key_exists($identifier, $menu_links_code)) ? $menu_links_code[$identifier] : '';
      cache_set("$component:$identifier", $configuration, 'cache_configuration');
    }
  }
}

function configuration_hash_menu_links($identifier) {
  // Export just the field we're tracking.
  module_load_include('inc', 'configuration', 'configuration.export');

  $data = menu_links_configuration_export_options();
  
  // Export the field we just saved and evaluate the export to hash
  eval(array_pop(menu_links_configuration_export_render('configuration', array($identifier))));

  return md5(serialize($menu_links[$identifier]));
}

function configuration_check_menu_custom($identifier, $from_activestore = false) {
  // Get static variable that we can access across this request.
  $from_activestore = &drupal_static('configuration_from_activestore');
  $component = 'menu_custom';
  
  if (file_exists("config://configuration.menu_custom.inc")) {
    // Load the current configuration file on disk
    include_once "config://configuration.menu_custom.inc";

    // Export just the field we're tracking.
    module_load_include('inc', 'configuration', 'configuration.export');

    // Export the field we just saved and evaluate the export to $fields
    eval(array_pop(menu_custom_configuration_export_render('configuration', array($identifier))));

    $menus_code = configuration_menu_default_menu_custom();
    
    // If the activestore doesn't exist it is most likely because this configuration
    // only exists in code.
    if (empty($menus)) {
      configuration_set_status($component, $identifier, CONFIGURATION_TRACKED_DATASTORE_ONLY);
    }

    // If this was the previous configuration in activestore don't mark this as changed.
    $config = configuration_get_configuration();

    /**
     * @todo This code is reused in all component files.
     */
    $return = '';
    $component = 'menu_custom';
    $status = $config[$component][$identifier]['status'];
    $md5_datastore = (is_array($menus_code) && array_key_exists($identifier, $menus_code)) ? md5(serialize($menus_code[$identifier])) : '';
    $md5_activestore = (is_array($menus_code) && array_key_exists($identifier, $menus_code)) ? md5(serialize($menus[$identifier])) : '';
    
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
      $configuration[$component][$identifier]['activestore'] = (is_array($menus_code) && array_key_exists($identifier, $menus_code)) ? $menus[$identifier] : '';
      $configuration[$component][$identifier]['datastore'] = (is_array($menus_code) && array_key_exists($identifier, $menus_code)) ? $menus_code[$identifier] : '';
      cache_set("$component:$identifier", $configuration, 'cache_configuration');
    }
  }
}

function configuration_hash_menu_custom($identifier) {
  // Export just the field we're tracking.
  module_load_include('inc', 'configuration', 'configuration.export');

  $data = menu_custom_configuration_export_options();

  // Export the field we just saved and evaluate the export to hash
  eval(array_pop(menu_custom_configuration_export_render('configuration', array($identifier))));

  return md5(serialize($menus[$identifier]));
}
