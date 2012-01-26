<?php

/**
 * Implements hook_configuration_api().
 */
function configuration_configuration_api() {
  return array(
    'dependencies' => array(
      'name' => 'Dependencies',
      'feature_source' => TRUE,
      'duplicates' => configuration_DUPLICATES_ALLOWED,
    ),
  );
}

/**
 * Implements hook_configuration_export_options().
 */
function dependencies_configuration_export_options() {
  // Excluded modules.
  $excluded = drupal_required_modules();
  $options = array();
  foreach (configuration_get_modules() as $module_name => $info) {
    if (!in_array($module_name, $excluded) && $info->status && !empty($info->info)) {
      $options[$module_name] = $info->info['name'];
    }
  }
  return $options;
}

/**
 * Implements hook_configuration_export().
 */
function dependencies_configuration_export($data, &$export, $module_name = '') {
  // Don't allow a module to depend upon itself.
  if (!empty($data[$module_name])) {
    unset($data[$module_name]);
  }

  // Clean up existing dependencies and merge.
  $export['dependencies'] = _configuration_export_minimize_dependencies($export['dependencies'], $module_name);
  $export['dependencies'] = array_merge($data, $export['dependencies']);
  $export['dependencies'] = array_unique($export['dependencies']);
}

/**
 * Implements hook_configuration_revert().
 */
function dependencies_configuration_revert($module) {
  dependencies_configuration_rebuild($module);
}

/**
 * Implements hook_configuration_rebuild().
 * Ensure that all of a feature's dependencies are enabled.
 */
function dependencies_configuration_rebuild($module) {
  $feature = configuration_get_configuration($module);
  if (!empty($feature->info['dependencies'])) {
    $install = array();
    foreach ($feature->info['dependencies'] as $dependency) {
      // Parse the dependency string into the module name and version information.
      $parsed_dependency = drupal_parse_dependency($dependency);
      $dependency = $parsed_dependency['name'];
      if (!module_exists($dependency)) {
        $install[] = $dependency;
      }
    }
    if (!empty($install)) {
      configuration_install_modules($install);
    }
  }
}
