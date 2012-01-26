<?php
/**
 * @file
 * I'll probably end up putting this code in configuration.field.inc.  Just want
 * to keep it separate for now, while I get this working.
 */

/**
 * Implements hook_field_update_instance().
 */
function configuration_field_update_instance($instance, $prior_instance) {
  // Set a static variable that we can access across this request.
  $a = &drupal_static('configuration_from_activestore');
  $a = true;
  
  // Pulled from line 27 configuration.field.inc 
  // @todo: Should use a function to get format of the identifier to be used
  //   in both places that this is being defined in.
  // $identifier = "{$entity_type}-{$bundle}-{$field['field_name']}";
  $identifier = $instance['entity_type'] .'-'. $instance['bundle'] .'-'. $instance['field_name'];
  $config = configuration_get_configuration('field');
  
  if (in_array($identifier, array_keys($config))) {
    module_load_include('inc', 'configuration', "includes/configuration.field");
    
    configuration_check_field($identifier);
    
    module_load_include('inc', 'configuration', "configuration.export");
    // Log the change in config_export
    configuration_write_export_file();
  }
}