<?php
/**
 * @file
 * I'll probably end up putting this code in configuration.taxonomy.inc.  Just want
 * to keep it separate for now, while I get this working.
 */

/**
 * Implements hook_taxonomy_vocabulary_update().
 */
function configuration_taxonomy_vocabulary_update($vocabulary) {
  // Set a static variable that we can access across this request.
  $a = &drupal_static('configuration_from_activestore');
  $a = true;
  
  $identifier = $vocabulary->machine_name;
  $config = configuration_get_configuration('taxonomy');
  
  if (in_array($identifier, array_keys($config))) {
    module_load_include('inc', 'configuration', "includes/configuration.taxonomy");
    
    configuration_check_taxonomy($identifier);
    
    module_load_include('inc', 'configuration', "configuration.export");
    // Log the change in config_export
    configuration_write_export_file();
  }
}