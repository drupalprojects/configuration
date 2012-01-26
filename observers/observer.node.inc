<?php
/**
 * @file
 * I'll probably end up putting this code in configuration.node.inc.  Just want
 * to keep it separate for now, while I get this working.
 */

/**
 * Implements hook_node_type_update().
 */
function configuration_node_type_update($info) {
  // Set a static variable that we can access across this request.
  $a = &drupal_static('configuration_from_activestore');
  $a = true;
  
  $identifier = $info->type;
  $config = configuration_get_configuration('node');
  
  if (in_array($identifier, array_keys($config))) {
    module_load_include('inc', 'configuration', "includes/configuration.node");
      
    configuration_check_node($identifier);
    
    module_load_include('inc', 'configuration', "configuration.export");
    // Log the change in config_export
    configuration_write_export_file();
  }
}