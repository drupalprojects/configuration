<?php
/**
 * @file
 * I'll probably end up putting this code in configuration.user.inc.  Just want
 * to keep it separate for now, while I get this working.
 */
 
/**
 * The permission system doesn't have an API for hooking into on save.  Need to
 * hook in on form submits, and also on cron to detect differences.
 */ 

/**
 * Implements hook_form_alter().
 */
function configuration_form_user_admin_permissions_alter(&$form, &$form_state, $form_id) {
  $form['#submit'][] = 'configuration_user_permissions_update';
}

/**
 * Submit handler for checking a diff on user permissions.
 **/
function configuration_user_permissions_update(&$form, &$form_state) {
  module_load_include('inc', 'configuration', "configuration.export");
  
  // Set a static variable that we can access across this request.
  $a = &drupal_static('configuration_from_activestore');
  $a = true;
  $config = configuration_get_configuration();
  
  module_load_include('inc', 'configuration', "includes/configuration.user");
  configuration_check_user_permission(array_keys($config['user_permission']));
  configuration_write_export_file();
}

/**
 * Implements hook_user_role_update().
 * 
 * We need to check all roles to see if something changed.  If any names to
 * roles changed or a tracked role was deleted, notify admin.  The weight of a
 * role is insignificant, if one role is missing or re-export everything.
 */
function configuration_user_role_update($role) {
  module_load_include('inc', 'configuration', "configuration.export");
  
  // Set a static variable that we can access across this request.
  $a = &drupal_static('configuration_from_activestore');
  $a = true;
  
  module_load_include('inc', 'configuration', "includes/configuration.user");
  configuration_check_user_role($role->name);
  configuration_write_export_file();
  
  // @todo: Think about how I can handle this better.  Do I really want to
  // rebuild cache everytime I make a change?
  cache_clear_all('config_export', 'cache');
  // Prime the cache.
  $configs = configuration_get_configuration();
}