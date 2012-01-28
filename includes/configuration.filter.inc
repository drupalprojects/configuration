<?php

/**
 * Implements hook_configuration_api().
 */
function filter_configuration_api() {
  return array(
    'filter' => array(
      'name' => t('Text formats'),
      'default_hook' => 'filter_default_formats',
      'default_file' => CONFIGURATION_DEFAULTS_INCLUDED,
      'feature_source' => TRUE
    ),
  );
}

/**
 * Implements hook_configuration_export_options().
 */
function filter_configuration_export_options() {
  $options = array();
  foreach (filter_formats() as $format => $info) {
    $options[$format] = $info->name;
  }
  return $options;
}

/**
 * Implements hook_configuration_export().
 */
function filter_configuration_export($data, &$export, $module_name = '') {
  // The filter_default_formats() hook integration is provided by the
  // configuration module so we need to add it as a dependency.
  $export['dependencies']['configuration'] = 'configuration';

  $filter_info = filter_get_filters();
  foreach ($data as $name) {
    if ($format = configuration_filter_format_load($name)) {
      // Add format to exports
      $export['configuration']['filter'][$format->format] = $format->format;

      // Iterate through filters and ensure each filter's module is included as a dependency
      foreach (array_keys($format->filters) as $name) {
        if (isset($filter_info[$name], $filter_info[$name]['module'])) {
          $module = $filter_info[$name]['module'];
          $export['dependencies'][$module] = $module;
        }
      }
    }
  }

  $pipe = array();
  return $pipe;
}

/**
 * Implements hook_configuration_export_render().
 */
function filter_configuration_export_render($module, $data, $export = NULL) {
  $code = array();
  $code[] = '  $formats = array();';
  $code[] = '';

  foreach ($data as $name) {
    if ($format = configuration_filter_format_load($name)) {
      $format_export = configuration_var_export($format, '  ');
      $format_identifier = configuration_var_export($format->format);
      $code[] = "  // Exported format: {$format->name}";
      $code[] = "  \$formats[{$format_identifier}] = {$format_export};";
      $code[] = "";
    }
  }

  $code[] = '  return $formats;';
  $code = implode("\n", $code);
  return array('filter_default_formats' => $code);
}

/**
 * Implements hook_configuration_revert().
 */
function filter_configuration_revert($identifiers, $module_name = 'configuration') {
  return filter_configuration_rebuild($identifiers, $module_name);
}

/**
 * Implements hook_configuration_rebuild().
 */
function filter_configuration_rebuild($identifiers, $module_name = 'configuration') {
  if ($defaults = configuration_get_default('filter', $module_name)) {
    foreach ($defaults as $identifier => $format) {
      if (in_array($identifier, $identifiers) || $identifiers[0] == '#import_all') {
        $format = (object) $format;
        filter_format_save($format);
      }
    }
  }
}

/**
 * Load a filter format by its name.
 */
function configuration_filter_format_load($name) {
  // Use machine name for retrieving the format if available.
  $query = db_select('filter_format');
  $query->fields('filter_format');
  $query->condition('format', $name);

  // Retrieve filters for the format and attach.
  if ($format = $query->execute()->fetchObject()) {
    $format->filters = array();
    foreach (filter_list_format($format->format) as $filter) {
      if (!empty($filter->status)) {
        $format->filters[$filter->name]['weight'] = $filter->weight;
        $format->filters[$filter->name]['status'] = $filter->status;
        $format->filters[$filter->name]['settings'] = $filter->settings;
      }
    }
    return $format;
  }
  return FALSE;
}


function configuration_check_filter($identifier) {
  // Get static variable that we can access across this request.
  $from_activestore = &drupal_static('configuration_from_activestore');
  $component = 'filter';
  
  if (file_exists("config://configuration.filter.inc")) {
    // Load the current configuration file on disk
    include_once "config://configuration.filter.inc";
    
    // Clear the filter cache to get a fresh export.
    cache_clear_all('filter_list_format', 'cache', TRUE);
    drupal_static_reset('filter_list_format');

    // Export just the field we're tracking.
    module_load_include('inc', 'configuration', 'configuration.export');

    // Export the field we just saved and evaluate the export to $fields
    eval(array_pop(filter_configuration_export_render('configuration', array($identifier))));

    $formats_code = configuration_filter_default_formats();

    // If the activestore doesn't exist it is most likely because this configuration
    // only exists in code.
    if (empty($formats)) {
      configuration_set_status($component, $identifier, CONFIGURATION_TRACKED_DATASTORE_ONLY);
    }

    // If this was the previous configuration in activestore don't mark this as changed.
    $config = configuration_get_configuration();
    /**
     * @todo This code is reused in all component files.
     */
    $return = '';
    $component = 'filter';
    $status = $config[$component][$identifier]['status'];
    $md5_datastore = (is_array($formats_code) && array_key_exists($identifier, $formats_code)) ? md5(serialize($formats_code[$identifier])) : '';
    $md5_activestore = (is_array($formats_code) && array_key_exists($identifier, $formats_code)) ? md5(serialize($formats[$identifier])) : '';
    
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
      $configuration[$component][$identifier]['activestore'] = (is_array($formats_code) && array_key_exists($identifier, $formats_code)) ? $formats[$identifier] : '';
      $configuration[$component][$identifier]['datastore'] = (is_array($formats_code) && array_key_exists($identifier, $formats_code)) ? $formats_code[$identifier] : '';
      cache_set("$component:$identifier", $configuration, 'cache_configuration');
    }
  }
}

function configuration_hash_filter($identifier) {
  // Export just the format we're tracking.
  module_load_include('inc', 'configuration', 'configuration.export');

  $data = filter_configuration_export_options();
  
  // Export the format we just saved and evaluate the export to hash
  eval(array_pop(filter_configuration_export_render('configuration', array($identifier))));

  return md5(serialize($formats[$identifier]));
}
