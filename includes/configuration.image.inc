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
      'default_file' => CONFIGURATION_DEFAULTS_INCLUDED,
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
      if (in_array($identifier, $identifiers) || (!empty($identifiers) && $identifiers[0] == '#import_all')) {
        $style = image_style_load($identifier);
        image_default_style_revert($style);

        $cache = cache_get("image:$identifier", 'cache_configuration');
        $cache->data['image'][$identifier]['activestore'] = $cache->data['image'][$identifier]['datastore'];
        cache_set('image:' . $identifier, $cache->data['image'][$identifier], 'cache_configuration');
        configuration_set_status('image', $identifier, CONFIGURATION_IN_SYNC);
      }
    }
    configuration_check_configurations(TRUE);
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
      elseif (is_array($v)) {
        _image_configuration_style_sanitize($style[$k], TRUE);
      }
    }
  }
}

function configuration_check_image($identifier) {
  // Get static variable that we can access across this request.
  $from_activestore = &drupal_static('configuration_from_activestore');
  $component = 'image';

  if (file_exists("config://configuration.image.inc")) {
    // Load the current configuration file on disk
    include_once drupal_realpath("config://configuration.image.inc");
    if (function_exists('configuration_configuration_image_default_styles')) {
      // Clear all caches and flush.
      image_style_flush($identifier);

      // Export just the field we're tracking.
      module_load_include('inc', 'configuration', 'configuration.export');

      // Export the field we just saved and evaluate the export to $fields
      $code = image_configuration_export_render('configuration', array($identifier));
      eval(array_pop($code));

      $styles_code = configuration_configuration_image_default_styles();

      // If the activestore doesn't exist it is most likely because this configuration
      // only exists in code.
      if (empty($styles)) {
        configuration_set_status($component, $identifier, CONFIGURATION_TRACKED_DATASTORE_ONLY);
      }

      configuration_update_component_status($component, $identifier, $styles, $styles_code, $from_activestore);
    }
  }
}

function configuration_hash_image($identifier) {
  // Export just the field we're tracking.
  module_load_include('inc', 'configuration', 'configuration.export');

  $data = image_configuration_export_options();

  // Export the field we just saved and evaluate the export to hash
  $code = image_configuration_export_render('configuration', array($identifier));
  eval(array_pop($code));

  return md5(serialize($styles[$identifier]));
}
