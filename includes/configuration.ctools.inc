<?php

/**
 * This is a wild hack, but effective.
 * Dynamically declare functions under a ctools component's namespace if they are not already declared.
 */
foreach (_ctools_configuration_get_info() as $component => $info) {
  $code = '';
  if (!function_exists("{$info['module']}_configuration_api")) {
    $code .= 'function '. $info['module'] .'_configuration_api() { return ctools_component_configuration_api("'. $info['module'] .'"); }';
  }
  if (!function_exists("{$component}_configuration_export")) {
    $code .= 'function '. $component .'_configuration_export($data, &$export, $module_name = "") { return ctools_component_configuration_export("'. $component .'", $data, $export, $module_name); }';
  }
  if (!function_exists("{$component}_configuration_export_options")) {
    $code .= 'function '. $component .'_configuration_export_options() { return ctools_component_configuration_export_options("'. $component .'"); }';
  }
  if (!function_exists("{$component}_configuration_export_render")) {
    $code .= 'function '. $component .'_configuration_export_render($module, $data) { return ctools_component_configuration_export_render("'. $component .'", $module, $data); }';
  }
  if (!function_exists("{$component}_configuration_revert")) {
    $code .= 'function '. $component .'_configuration_revert($module, $module_name = "configuration") { return ctools_component_configuration_revert("'. $component .'", $module, $module_name); }';
  }
  if (!function_exists("configuration_check_{$component}")) {
    $code .= 'function configuration_check_'. $component . '($identifier, $from_activestore = false) { return ctools_configuration_check($identifier, $from_activestore); }';
  }
  if (!function_exists("configuration_hash_{$component}")) {
    $code .= 'function configuration_hash_'. $component . '($identifier) { return ctools_configuration_hash($identifier); }';
  }
  eval($code);
}

/**
 * Implements hook_configuration_api().
 */
function ctools_configuration_api() {
  return array(
    'ctools' => array(
      'name' => 'CTools export API',
      'feature_source' => TRUE,
      'duplicates' => CONFIGURATION_DUPLICATES_ALLOWED,
      // CTools API integration does not include a default hook declaration as
      // it is not a proper default hook.
      // 'default_hook' => 'ctools_plugin_api',
    ),
  );
}

/**
 * Implements hook_configuration_export().
 * Adds references to the ctools mothership hook, ctools_plugin_api().
 */
function ctools_configuration_export($data, &$export, $module_name = '') {
  // Add ctools dependency
  $export['dependencies']['ctools'] = 'ctools';

  # We don't need the mothership hook
  // // Add the actual ctools components which will need to be accounted for in
  // // hook_ctools_plugin_api(). The components are actually identified by a
  // // delimited list of values: `module_name:api:current_version`
  // foreach ($data as $component) {
  //   if ($info = _ctools_configuration_get_info($component)) {
  //     $identifier = "{$info['module']}:{$info['api']}:{$info['current_version']}";
  //     $export['configuration']['ctools'][$identifier] = $identifier;
  //   }
  // }

  return array();
}

/**
 * Implements hook_configuration_export_render().
 * Adds the ctools mothership hook, ctools_plugin_api().
 */
function ctools_configuration_export_render($module, $data) {
  $code = array();
  $code[] = '  list($module, $api) = func_get_args();';

  $component_exports =  array();
  foreach ($data as $component) {
    $code = array();
    $code[] = '  list($module, $api) = func_get_args();';

    if ($info = _ctools_configuration_get_info($component)) {

      $code[] = '  if ($module == "'. $info['module'] .'" && $api == "'. $info['api'] .'") {';
      $code[] = '    return array("version" => "'. $info['current_version'] .'");';
      $code[] = '  }';
    }
    ctools_include('plugins');
    $plugin_api_hook_name = ctools_plugin_api_get_hook($info['module'], $info['api']);

    if (key_exists($plugin_api_hook_name, $component_exports)) {
        $component_exports[$plugin_api_hook_name] .= "\n" . implode("\n", $code);
    }
    else {
        $component_exports[$plugin_api_hook_name] = implode("\n", $code);
    }
  }

  return $component_exports;

}

/**
 * Master implementation of hook_configuration_api() for all ctools components.
 *
 * Note that this master hook does not use $component like the others, but uses the
 * component module's namespace instead.
 */
function ctools_component_configuration_api($module_name) {
  $api = array();
  foreach (_ctools_configuration_get_info() as $component => $info) {
    $api[$component] = $info;
  }
  return $api;
}

/**
 * Master implementation of hook_configuration_export_options() for all ctools components.
 */
function ctools_component_configuration_export_options($component) {
  $options = array();

  ctools_include('export');
  $schema = ctools_export_get_schema($component);
  if ($schema && $schema['export']['bulk export']) {
    if (!empty($schema['export']['list callback']) && function_exists($schema['export']['list callback'])) {
      $options = $schema['export']['list callback']();
    }
    else {
      $options = _ctools_configuration_export_default_list($component, $schema);
    }
  }
  asort($options);
  return $options;
}

/**
 * Master implementation of hook_configuration_export() for all ctools components.
 */
function ctools_component_configuration_export($component, $data, &$export, $module_name = '') {
  // Add the actual implementing module as a dependency
  $info = _ctools_configuration_get_info();
  $new_dependencies = array();
  if ($module_name !== $info[$component]['module']) {
    $new_dependencies[$info[$component]['module']] = $info[$component]['module'];
  }

  // Add the components
  foreach ($data as $object_name) {
    if ($object = _ctools_configuration_export_crud_load($component, $object_name)) {
      // If this object is provided as a default by a different module, add 
      // that module as a dependency.
      if (!empty($object->export_module) && $object->export_module !== $module_name) {
        $new_dependencies[$object->export_module] = $object->export_module;
      }
      $export['dependencies'] = array_merge($export['dependencies'], $new_dependencies);
      $export['configuration'][$component][$object_name] = $object_name;
    }
  }

  // Let CTools handle API integration for this component.
  return array('ctools' => array($component));
}

/**
 * Master implementation of hook_configuration_export_render() for all ctools components.
 */
function ctools_component_configuration_export_render($component, $module, $data) {
  ctools_include('export');
  $schema = ctools_export_get_schema($component);
  
  if (function_exists($schema['export']['to hook code callback'])) {
    $export = $schema['export']['to hook code callback']($data, $module);
    $code = explode("{\n", $export);
    array_shift($code);
    $code = explode('}', implode($code, "{\n"));
    array_pop($code);
    $code = implode('}', $code);
  }
  else {
    $code = '  $export = array();'."\n\n";
    foreach ($data as $object_name) {
      if ($object = _ctools_configuration_export_crud_load($component, $object_name)) {
        $identifier = $schema['export']['identifier'];
        $code .= _ctools_configuration_export_crud_export($component, $object, '  ');
        $code .= "  \$export[" . ctools_var_export($object_name) . "] = \${$identifier};\n\n";
      }
    }
    $code .= '  return $export;';
  }
  return array('configuration_'. $schema['export']['default hook'] => $code);
}

/**
 * Master implementation of hook_configuration_revert() for all ctools components.
 */
function ctools_component_configuration_revert($component, $module, $module_name) {
  if ($objects = configuration_get_default($component, $module_name)) {
    foreach ($objects as $name => $object) {
      // Some things (like views) do not use the machine name as key
      // and need to be loaded explicitly in order to be deleted.
      _ctools_configuration_export_crud_save($component, $name, $object);
      configuration_set_status($component, $name, CONFIGURATION_IN_SYNC);
    }
    configuration_write_export_file();
  }
}

/**
 * Helper function to return various ctools information for components.
 */
function _ctools_configuration_get_info($identifier = NULL, $reset = FALSE) {
  static $components;
  if (!isset($components) || $reset) {
    $components = array();
    $modules = configuration_get_info();
    ctools_include('export');
    foreach (ctools_export_get_schemas_by_module() as $module => $schemas) {
      foreach ($schemas as $table => $schema) {
        if ($schema['export']['bulk export']) {
          // Let the API owner take precedence as the owning module.
          $api_module = isset($schema['export']['api']['owner']) ? $schema['export']['api']['owner'] : $module;
          $components[$table] = array(
            'name' => isset($modules[$api_module]->info['name']) ? $modules[$api_module]->info['name'] : $api_module,
            'default_hook' => 'configuration_'. $schema['export']['default hook'],
            'default_file' => CONFIGURATION_DEFAULTS_CUSTOM,
            'module' => $api_module,
            'feature_source' => TRUE,
          );
          if (isset($schema['export']['api'])) {
            $components[$table] += array(
              'api' => $schema['export']['api']['api'],
              'default_filename' => $schema['export']['api']['api'],
              'current_version' => $schema['export']['api']['current_version'],
            );
          }
        }
      }
    }
  }

  // Return information specific to a particular component.
  if (isset($identifier)) {
    // Identified by the table name.
    if (isset($components[$identifier])) {
      return $components[$identifier];
    }
    // New API identifier. Allows non-exportables related CTools APIs to be
    // supported by an explicit `module:api:current_version` key. 
    else if (substr_count($identifier, ':') === 2) {
      list($module, $api, $current_version) = explode(':', $identifier);
      // If a schema component matches the provided identifier, provide that
      // information. This also ensures that the version number is up to date.
      foreach ($components as $table => $info) {
        if ($info['module'] == $module && $info['api'] == $api && $info['current_version'] >= $current_version) {
          return $info;
        }
      }
      // Fallback to just giving back what was provided to us.
      return array('module' => $module, 'api' => $api, 'current_version' => $current_version);
    }
    return FALSE;
  }

  return $components;
}

/**
 * Wrapper around ctools_export_crud_export() for < 1.7 compatibility.
 */
function _ctools_configuration_export_crud_export($table, $object, $indent = '') {
  return ctools_api_version('1.7') ? ctools_export_crud_export($table, $object, $indent) : ctools_export_object($table, $object, $indent);
}

/**
 * Wrapper around ctools_export_crud_load() for < 1.7 compatibility.
 */
function _ctools_configuration_export_crud_load($table, $name) {
  if (ctools_api_version('1.7')) {
    return ctools_export_crud_load($table, $name);
  }
  elseif ($objects = ctools_export_load_object($table, 'names', array($name))) {
    return array_shift($objects);
  }
  return FALSE;
}

/**
 * Wrapper around ctools_export_default_list() for < 1.7 compatibility.
 */
function _ctools_configuration_export_default_list($table, $schema) {
  if (ctools_api_version('1.7')) {
    return ctools_export_default_list($table, $schema);
  }
  elseif ($objects = ctools_export_load_object($table, 'all')) {
    return drupal_map_assoc(array_keys($objects));
  }
  return array();
}

/**
 * Wrapper around ctools_export_crud_delete() for < 1.7 compatibility.
 */
function _ctools_configuration_export_crud_delete($table, $object) {
  if (ctools_api_version('1.7')) {
    ctools_export_crud_delete($table, $object);
  }
  else {
    $schema = ctools_export_get_schema($table);
    $export = $schema['export'];
    db_query("DELETE FROM {{$table}} WHERE {$export['key']} = '%s'", $object->{$export['key']});
  }
}

/**
 * Wrapper around ctools_export_crud_save() for < 1.7 compatibility.
 */
function _ctools_configuration_export_crud_save($table, $name, $object) {
  if (ctools_api_version('1.7')) {
    $schema = ctools_export_get_schema($table);
    $in_activestore = ctools_export_crud_load($table, $name);

    if (is_object($in_activestore)) {
      $object->{$schema['export']['primary key']} = $in_activestore->{$schema['export']['primary key']};
      $object->export_type = $in_activestore->export_type;
    }
    
    // @todo: Panel pages can't import from datastore this way.  Have to figure
    // out how to get panel pages imported.
    // page_manager_get_tasks();
    // module_load_include('inc', 'panels', 'plugins/task_handlers/panel_context');
    //     foreach ($object->default_handlers as &$handler){
    //       panels_panel_context_save($handler, '');
    //     }
    ctools_export_crud_save($table, $object);
  }
}

/**
 * Implements hook_configuration_export_render() for page_manager.
 */
function page_manager_pages_configuration_export_render($module, $data) {
  // Reset the static counter for pids
  $pid_counter = &drupal_static('panels_export_display');
  $pid_counter = 0;
  
  // Ensure that handlers have their code included before exporting.
  page_manager_get_tasks();
  return ctools_component_configuration_export_render('page_manager_pages', $module, $data);
}

function configuration_hash_page_manager_pages($identifier) {
  // Ensure that handlers have their code included before exporting.
  page_manager_get_tasks();
  return ctools_configuration_hash($identifier);
}


function ctools_configuration_check($identifier) {
  // Set a static variable that we can access across this request.
  $from_activestore = &drupal_static('configuration_from_activestore');
  
  // The component name is the name of the function that called this function
  // after the string configuration_check_<component>
  $backtrace = @debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
  $component = substr($backtrace[1]['function'], strlen('configuration_check_'));
  
  // Load the current configuration file on disk
  $info = _ctools_configuration_get_info();
  
  if (file_exists('config://'. $info[$component]['default_filename'] .'.inc')) {
    // Page Manager has it's export callback in a file that is not autoloaded.
    // PM also doesn not use the export variable, we have to set it here.
    // @todo: Come up with a cleaner way to include dependent files.
    if ($component == 'page_manager_pages') {
      ctools_include('page', 'page_manager', 'plugins/tasks');
      $export = &$pages;
    }    
    
    include_once 'config://'. $info[$component]['default_filename'] .'.inc';
    // Export just the field we're tracking.
    module_load_include('inc', 'configuration', 'configuration.export');

    $data = ctools_component_configuration_export_options($component);
    // Export the field we just saved and evaluate the export to $export
    eval(array_pop(ctools_component_configuration_export_render($component, 'configuration', array($identifier))));

    $export_code = call_user_func('configuration_'. $info[$component]['default_hook']);

    // If this was the previous configuration in activestore don't mark this as changed.
    $config = configuration_get_configuration();
    
    // If the activestore doesn't exist it is most likely because this configuration
    // only exists in code.
    if (empty($export)) {
      configuration_set_status($component, $identifier, CONFIGURATION_TRACKED_DATASTORE_ONLY);
    }
    
    /**
     * @todo: There is a bug with an object being attached to views when 
     * exporting. Haven't been able to track down where this is coming from so
     * I'm just going to unset it for now.
     */
    if ($component == 'views_view') {
      foreach ($export as &$view) {
        if (property_exists($view, 'localization_plugin')) {
          unset($view->localization_plugin);
        }
      }
    }
    
    
    /**
     * @todo This code is reused in all component files.
     */
    $return = '';
    $status = $config[$component][$identifier]['status'];
    $md5_datastore = (is_array($export_code) && array_key_exists($identifier, $export_code)) ? md5(serialize($export_code[$identifier])) : ''; 
    $md5_activestore = (is_array($export_code) && array_key_exists($identifier, $export_code)) ? md5(serialize($export[$identifier])) : '';
    
    // Configs in code are not the same as what was just saved in activestore.
    // There is no way of telling if the change was in the activestore or the
    // datastore. Mark them changed in both.
    if ($md5_datastore == $config[$component][$identifier]['hash'] && $md5_datastore != $md5_activestore) {
      // dsm('configs in code are not the same as what was just saved in activestore.');
      $status = $status | CONFIGURATION_ACTIVESTORE_OVERRIDDEN;
      configuration_set_status($component, $identifier, $status);
    }
    // Menu in the activestore is the same as what is in code.
    if ($md5_activestore == $md5_datastore) {
      // dsm('config in activestore are same as what is in code');
      $status = CONFIGURATION_IN_SYNC;
      configuration_set_status($component, $identifier,  $status);
      configuration_set_hash($component, $identifier, $md5_activestore);
    }
    if ($md5_activestore != $md5_datastore) {
      $status = $status | CONFIGURATION_DATASTORE_OVERRIDDEN;
      configuration_set_status($component, $identifier, $status);
    }
    // When checking for new configurations, check to see if configurations are
    // the same in datastore as last activestore
    if (!$from_activestore && $md5_datastore == $config[$component][$identifier]['hash']) {
      // dsm('config in code are same as last in activestore');
      $status = $status  & ~CONFIGURATION_DATASTORE_OVERRIDDEN;
      configuration_set_status($component, $identifier, $status);
    }
    
    // Store the config array in cache for easy access
    if ($status != CONFIGURATION_IN_SYNC) {
      $configuration[$component][$identifier]['activestore'] = (is_array($export_code) && array_key_exists($identifier, $export_code)) ? $export[$identifier] : '';
      $configuration[$component][$identifier]['datastore'] = (is_array($export_code) && array_key_exists($identifier, $export_code)) ? $export_code[$identifier] : '';
      cache_set("$component:$identifier", $configuration, 'cache_configuration');
    }  
  }
}

function ctools_configuration_hash($identifier) {
  // Export just the field we're tracking.
  module_load_include('inc', 'configuration', 'configuration.export');

  // PHP 5.2 throws warnings when the 5.3 only constant is passed in.
  $backtrace = @debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
  $component = substr($backtrace[1]['function'], strlen('configuration_hash_'));
  $data = ctools_component_configuration_export_options($component);
  // Export the field we just saved and evaluate the export to $export
  eval(array_pop(ctools_component_configuration_export_render($component, 'configuration', array($identifier))));
  
  // Page Manager does not use the export variable, we have to set it here.
  // @todo: Come up with a cleaner way to do this.
  if ($component == 'page_manager_pages') {
    $export = $pages;
  }

  return md5(serialize($export[$identifier])); 
}