<?php

/**
 * Implements hook_configuration_api().
 */
function image_configuration_api() {
  return array(
    'image' => array(
      'name' => t('Image styles'),
      'feature_source' => TRUE,
      'default_hook' => 'configuration_image_default_styles',
    )
  );
}

/**
 * Implements hook_configuration_export_options().
 */
function image_configuration_export_options() {
  $options = array();
  foreach (image_styles() as $name => $style) {
    $options[$name] = $style['name'];
  }
  return $options;
}

/**
 * Implements hook_configuration_export().
 */
function image_configuration_export($data, &$export, $module_name = '') {
  $pipe = array();
  $map = configuration_get_default_map('image');
  foreach ($data as $style) {
    $export['dependencies']['image'] = 'image';
    // If another module provides this style, add it as a dependency
    if (isset($map[$style]) && $map[$style] != $module_name) {
      $module = $map[$style];
      $export['dependencies'][$module] = $module;
    }
    // Otherwise, export the style
    if (image_style_load($style)) {
      $export['configuration']['image'][$style] = $style;
    }
  }
  return $pipe;
}

/**
 * Implements hook_configuration_export_render().
 */
function image_configuration_export_render($module_name, $data, $export = NULL) {
  $code = array();
  $code[] = '  $styles = array();';
  $code[] = '';
  foreach ($data as $name) {
    if ($style = image_style_load($name)) {
      _image_configuration_style_sanitize($style);
      $style_export = configuration_var_export($style, '  ');
      $style_identifier = configuration_var_export($name);
      $code[] = "  // Exported image style: {$name}";
      $code[] = "  \$styles[{$style_identifier}] = {$style_export};";
      $code[] = "";
    }
  }
  $code[] = '  return $styles;';
  $code = implode("\n", $code);
  return array('configuration_image_default_styles' => $code);
}

/**
 * Implements hook_configuration_revert().
 */
function image_configuration_revert($identifiers, $module_name = 'configuration') {
  if ($default_styles = configuration_get_default('image', $module_name)) {
    foreach ($default_styles as $identifier => $default_style) {
      if (in_array($identifier, $identifiers) || $identifiers[0] == '#import_all') {
        $style = image_style_load($identifier);
        $default_style['isid'] = $style['isid'];
        foreach ($default_style['effects'] as $ieid => $info) {
          // $info['ieid'] = $ieid; // @todo: This may create a ton of orphaned records or duplicates
          $info['isid'] = $style['isid'];
          image_effect_save($info);
        }
        image_style_save($default_style);
      }
    }
  }
}

/**
 * Remove unnecessary keys for export.
 */
function _image_configuration_style_sanitize(&$style, $child = FALSE) {
  $omit = $child ? array('isid', 'ieid', 'storage') : array('isid', 'ieid', 'storage', 'module');
  if (is_array($style)) {
    foreach ($style as $k => $v) {
      if (in_array($k, $omit, TRUE)) {
        unset($style[$k]);
      }
      else if (is_array($v)) {
        _image_configuration_style_sanitize($style[$k], TRUE);
      }
    }
  }
}

function configuration_check_image($identifier) {
  // Get static variable that we can access across this request.
  $from_activestore = &drupal_static('configuration_from_activestore');
  $component = 'image';
  
  if (file_exists("config://configuration.inc")) {
    // Load the current configuration file on disk
    include_once "config://configuration.inc";
    if (function_exists('configuration_configuration_image_default_styles')) {
      // Clear all caches and flush.
      image_style_flush($identifier);

      // Export just the field we're tracking.
      module_load_include('inc', 'configuration', 'configuration.export');

      // Export the field we just saved and evaluate the export to $fields
      eval(array_pop(image_configuration_export_render('configuration', array($identifier))));

      $styles_code = configuration_configuration_image_default_styles();
      
      // If the activestore doesn't exist it is most likely because this configuration
      // only exists in code.
      if (empty($styles)) {
        configuration_set_status($component, $identifier, CONFIGURATION_TRACKED_DATASTORE_ONLY);
      }

      // If this was the previous configuration in activestore don't mark this as changed.
      $config = configuration_get_configuration();
      /**
       * @todo This code is reused in all component files.
       */
      $return = '';
      $component = 'image';
      $status = $config[$component][$identifier]['status'];
      $md5_datastore = (is_array($styles_code) && array_key_exists($identifier, $styles_code)) ? md5(serialize($styles_code[$identifier])) : '';
      $md5_activestore = (is_array($styles_code) && array_key_exists($identifier, $styles_code)) ? md5(serialize($styles[$identifier])) : '';
      
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
        $configuration[$component][$identifier]['activestore'] = (is_array($styles_code) && array_key_exists($identifier, $styles_code)) ? $styles[$identifier] : '';
        $configuration[$component][$identifier]['datastore'] = (is_array($styles_code) && array_key_exists($identifier, $styles_code)) ? $styles_code[$identifier] : '';
        cache_set("$component:$identifier", $configuration, 'cache_configuration');
      }
    }
  }
}

function configuration_hash_image($identifier) {
  // Export just the field we're tracking.
  module_load_include('inc', 'configuration', 'configuration.export');

  $data = image_configuration_export_options();
  
  // Export the field we just saved and evaluate the export to hash
  eval(array_pop(image_configuration_export_render('configuration', array($identifier))));

  return md5(serialize($styles[$identifier]));
}
