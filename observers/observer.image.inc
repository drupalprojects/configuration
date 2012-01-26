<?php
/**
 * @file
 * I'll probably end up putting this code in configuration.image.inc.  Just want
 * to keep it separate for now, while I get this working.
 */

/**
 * Implements hook_image_style_save().
 */
function configuration_image_style_save($style) {
  // Set a static variable that we can access across this request.
  $a = &drupal_static('configuration_from_activestore');
  $a = true;
  
  $identifier = $style['name'];
  $config = configuration_get_configuration('image');

  if (in_array($identifier, array_keys($config))) {
    module_load_include('inc', 'configuration', "configuration.export");
    module_load_include('inc', 'configuration', "includes/configuration.image");
    
    configuration_check_image($identifier);
    
    module_load_include('inc', 'configuration', "configuration.export");
    // Log the change in config_export
    configuration_write_export_file();
  }
}