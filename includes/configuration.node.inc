<?php

/**
 * Implements hook_configuration_api().
 */
function node_configuration_api() {
  return array(
    'node' => array(
      'name' => t('Content types'),
      'feature_source' => TRUE,
      'default_hook' => 'configuration_node_info',
    ),
  );
}

/**
 * Implements hook_configuration_export_options().
 */
function node_configuration_export_options() {
  return node_type_get_names();
}

/**
 * Implements hook_configuration_export.
 */
function node_configuration_export($data, &$export, $module_name = '') {
  $pipe = array();
  $map = configuration_get_default_map('node');

  foreach ($data as $type) {
    $new_dependencies = array();
    // Poll node module to determine who provides the node type.
    if ($info = node_type_get_type($type)) {
      // If this node type is provided by a different module, add it as a dependency
      if (isset($map[$type]) && $map[$type] != $module_name) {
        $new_dependencies[$map[$type]] = $map[$type];
      }
      // Otherwise export the node type.
      if (in_array($info->base, array('node_content', 'configuration'))) {
        $export['configuration']['node'][$type] = $type;
        $new_dependencies['node'] = 'node';
        $new_dependencies['configuration'] = 'configuration';
      }
      
      // Merge new dependencies with global.
      $export['dependencies'] = array_merge($export['dependencies'], $new_dependencies);
      
      $fields = field_info_instances('node', $type);
      foreach ($fields as $name => $field) {
        $export['configuration_dependency']['configuration']['field']["node-{$field['bundle']}-{$field['field_name']}"] = $type;
        $export['configuration_dependency']['modules']['field']["node-{$field['bundle']}-{$field['field_name']}"] = serialize($new_dependencies);
        $pipe['field'][] = "node-{$field['bundle']}-{$field['field_name']}";
      }
    }
  }

  return $pipe;
}

/**
 * Implements hook_configuration_export_render().
 */
function node_configuration_export_render($module, $data, $export = NULL) {
  $elements = array(
    'name' => TRUE,
    'base' => FALSE,
    'description' => TRUE,
    'has_title' => FALSE,
    'title_label' => TRUE,
    'help' => TRUE,
  );
  $output = array();
  $output[] = '  $items = array(';
  foreach ($data as $type) {
    if ($info = node_type_get_type($type)) {
      // Force module name to be 'configuration' if set to 'node. If we leave as
      // 'node' the content type will be assumed to be database-stored by
      // the node module.
      $info->base = ($info->base === 'node') ? 'configuration' : $info->base;
      $output[] = "    '{$type}' => array(";
      foreach ($elements as $key => $t) {
        if ($t) {
          $text = str_replace("'", "\'", $info->$key);
          $text = !empty($text) ? "t('{$text}')" : "''";
          $output[] = "      '{$key}' => {$text},";
        }
        else {
          $output[] = "      '{$key}' => '{$info->$key}',";
        }
      }
      $output[] = "    ),";
    }
  }
  $output[] = '  );';
  $output[] = '  return $items;';
  $output = implode("\n", $output);
  return array('configuration_node_info' => $output);
}

/**
 * Implements hook_configuration_revert().
 *
 * @param $module
 * name of module to revert content for
 */
function node_configuration_revert($identifiers, $module_name = 'configuration') {
  if ($default_types = configuration_get_default('node', $module_name)) {
    foreach ($default_types as $type_name => $type_info) {
      // Delete node types
      // We don't use node_type_delete() because we do not actually
      // want to delete the node type (and invoke hook_node_type()).
      // This can lead to bad consequences like CCK deleting field
      // storage in the DB.
      if (in_array($type_name, $identifiers)) {
        db_delete('node_type')
          ->condition('type', $type_name)
          ->execute();
      }
    }
    configuration_node_types_rebuild($identifiers, $module_name);
    menu_rebuild();
    
    foreach ($default_types as $type_name => $type_info) {
      configuration_check_node($type_name);
    }
    cache_clear_all('config_export', 'cache');
  }
}

/**
 * Builds and returns the list of node types activated.
 */
function configuration_node_types_rebuild($identifiers, $module_name = 'configuration') {
  $_node_types = (object) array(
    'types' => array(),
    'names' => array(),
  );

  $info_array = call_user_func($module_name .'_configuration_node_info');
  foreach ($info_array as $type => $info) {
    if (in_array($type, $identifiers) || $identifiers[0] == '#import_all') {
      $info['type'] = $type;
      $_node_types->types[$type] = node_type_set_defaults($info);
      $_node_types->types[$type]->module = 'configuration';
      $_node_types->names[$type] = $info['name'];
    }
  }

  foreach ($_node_types->types as $type => $type_object) {
    // if (!empty($type_object->is_new) || !empty($type_object->disabled_changed)) {
      node_type_save($type_object);
    // }
  }
  
  node_types_rebuild();
}

/**
 * Implements hook_configuration_disable().
 *
 * When a configuration module is disabled, modify any node types it provides so
 * they can be deleted manually through the content types UI.
 *
 * @param $module
 *   Name of module that has been disabled.
 */
function node_configuration_disable($module) {
  if ($default_types = configuration_get_default('node', $module)) {
    foreach ($default_types as $type_name => $type_info) {
      $type_info = node_type_load($type_name);
      $type_info->module = 'node';
      $type_info->custom = 1;
      $type_info->modified = 1;
      $type_info->locked = 0;
      node_type_save($type_info);
    }
  }
}

/**
 * Implements hook_configuration_enable().
 *
 * When a configuration module is enabled, modify any node types it provides so
 * they can no longer be deleted manually through the content types UI.
 *
 * @param $module
 *   Name of module that has been enabled.
 */
function node_configuration_enable($module) {
  if ($default_types = configuration_get_default('node', $module)) {
    foreach ($default_types as $type_name => $type_info) {
      // Ensure the type exists.
      if ($type_info = node_type_load($type_name)) {
        $type_info->module = $module;
        $type_info->custom = 0;
        $type_info->modified = 0;
        $type_info->locked = 1;
        node_type_save($type_info);
      }
    }
  }
}

function configuration_check_node($identifier) {
  // Get static variable that we can access across this request.
  $from_activestore = &drupal_static('configuration_from_activestore');
  $component = 'node';
  
  if (file_exists("config://configuration.inc")) {
    // Load the current configuration file on disk
    include_once "config://configuration.inc";
    if (function_exists('configuration_configuration_node_info')) {
      // Clear the node type cache.
      node_type_cache_reset();

      // Export just the field we're tracking.
      module_load_include('inc', 'configuration', 'configuration.export');

      // Export the field we just saved and evaluate the export to $fields
      eval(array_pop(node_configuration_export_render('configuration', array($identifier))));  

      $items_code = configuration_configuration_node_info();

      // If the activestore doesn't exist it is most likely because this configuration
      // only exists in code.
      if (empty($items)) {
        configuration_set_status($component, $identifier, CONFIGURATION_TRACKED_DATASTORE_ONLY);
      }

      // If this was the previous configuration in activestore don't mark this as changed.
      $config = configuration_get_configuration();
      /**
       * @todo This code is reused in all component files.
       */
      $return = '';
      $component = 'node';
      $status = $config[$component][$identifier]['status'];
      $md5_datastore = (is_array($items_code) && array_key_exists($identifier, $items_code)) ? md5(serialize($items_code[$identifier])) : '';
      $md5_activestore = (is_array($items_code) && array_key_exists($identifier, $items_code)) ? md5(serialize($items[$identifier])) : '';

      // Configs in code are not the same as what was just saved in activestore.
      if ($from_activestore == TRUE && $md5_datastore != $md5_activestore) {
        // dsm('configs in code are not the same as what was just saved in activestore.');
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
        $configuration[$component][$identifier]['activestore'] = (is_array($items_code) && array_key_exists($identifier, $items_code)) ? $items[$identifier] : '';
        $configuration[$component][$identifier]['datastore'] = (is_array($items_code) && array_key_exists($identifier, $items_code)) ? $items_code[$identifier] : '';
        cache_set("$component:$identifier", $configuration, 'cache_configuration');
      }
    }
  }
}

function configuration_hash_node($identifier) {
  // Export just the field we're tracking.
  module_load_include('inc', 'configuration', 'configuration.export');

  $data = node_configuration_export_options();

  // Export the field we just saved and evaluate the export to hash
  eval(array_pop(node_configuration_export_render('configuration', array($identifier))));

  return md5(serialize($items[$identifier]));
}