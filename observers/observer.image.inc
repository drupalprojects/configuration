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
  $a = TRUE;

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

/**
 * Implements hook_form_FORM_ID_alter().
 */
function configuration_form_image_style_revert_form_alter(&$form, $form_state) {
  $form['#submit'][] = 'configuration_revert_image_style';
}

/**
 * Restore the values from the datastore when an image style is reverted.
 */
function configuration_revert_image_style($form, $form_state) {
  if (!empty($form_state['image_style']['name'])) {
    $identifier = $form_state['image_style']['name'];
    $cache = cache_get("image:$identifier", 'cache_configuration');
    $cache->data['image'][$identifier]['activestore'] = $cache->data['image'][$identifier]['datastore'];
    cache_set('image:' . $identifier, $cache->data['image'][$identifier], 'cache_configuration');
    configuration_set_status('image', $identifier, CONFIGURATION_IN_SYNC);
    configuration_check_configurations(TRUE);
  }
}
