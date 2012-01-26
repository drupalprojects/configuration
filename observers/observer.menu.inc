<?php
/**
 * @file
 * I'll probably end up putting this code in configuration.menu.inc.  Just want
 * to keep it separate for now, while I get this working.
 */

/**
 * Implements hook_menu_update().
 */
function configuration_menu_update($menu) {
  // Set a static variable that we can access across this request.
  $a = &drupal_static('configuration_from_activestore');
  $a = true;
  
  $identifier = $menu['menu_name'];
  $config = configuration_get_configuration('menu_custom');
  
  if (in_array($identifier, array_keys($config))) {
    module_load_include('inc', 'configuration', "includes/configuration.menu");
    
    configuration_check_menu_custom($identifier);
    
    module_load_include('inc', 'configuration', "configuration.export");
    // Log the change in config_export
    configuration_write_export_file();
  }
}

/**
 * Implements hook_menu_link_update().
 */
function configuration_menu_link_update($item) {
  // Set a static variable that we can access across this request.
  $a = &drupal_static('configuration_from_activestore');
  $a = true;
  
  $identifier = $item['menu_name'] .':'. $item['link_path'];
  $config = configuration_get_configuration('menu_links');

  if (in_array($identifier, array_keys($config))) {
    module_load_include('inc', 'configuration', "includes/configuration.menu");
    
    configuration_check_menu_links($identifier);
    
    module_load_include('inc', 'configuration', "configuration.export");
    // Log the change in config_export
    configuration_write_export_file();
    
    // @todo: Think about how I can handle this better.  Do I really want to
    // rebuild cache everytime I make a change?
    cache_clear_all('config_export', 'cache');
    // Prime the cache.
    $configs = configuration_get_configuration();
  }
}