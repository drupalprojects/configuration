<?php
/**
 * @file
 * I'll probably end up putting this code in configuration.filter.inc.  Just want
 * to keep it separate for now, while I get this working.
 */

/**
 * Implements hook_filter_format_update().
 */
function configuration_filter_format_update($format) {
  // Set a static variable that we can access across this request.
  $a = &drupal_static('configuration_from_activestore');
  $a = true;
  
  $identifier = $format->format;
  $config = configuration_get_configuration('filter');

  if (in_array($identifier, array_keys($config))) {
    module_load_include('inc', 'configuration', "includes/configuration.filter");
    
    configuration_check_filter($identifier);
    
    module_load_include('inc', 'configuration', "configuration.export");
    // Log the change in config_export
    configuration_write_export_file();
  }
}