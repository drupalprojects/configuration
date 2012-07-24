<?php

/**
 * Implements hook_configuration_api().
 */
function field_configuration_api() {
  return array(
    'field' => array(
      'name' => t('Fields'),
      'default_hook' => 'field_default_fields',
      'default_file' => CONFIGURATION_DEFAULTS_INCLUDED,
      'feature_source' => TRUE,
    )
  );
}


/**
 * Implements hook_configuration_export_options().
 */
function field_configuration_export_options() {
  $options = array();
  $instances = field_info_instances();
  foreach ($instances as $entity_type => $bundles) {
    foreach ($bundles as $bundle => $fields) {
      foreach ($fields as $field) {
        $identifier = "{$entity_type}-{$bundle}-{$field['field_name']}";
        $options[$identifier] = $identifier;
      }
    }
  }
  return $options;
}

/**
 * Implements hook_configuration_export().
 */
function field_configuration_export($data, &$export, $module_name = '') {
  $pipe = array();
  $map = configuration_get_default_map('field');

  // The field_default_fields() hook integration is provided by the
  // configuration module so we need to add it as a dependency.
  $export['dependencies']['configuration'] = 'configuration';

  foreach ($data as $identifier) {
    $new_dependencies = array();
    if ($field = configuration_field_load($identifier)) {
      // If this field is already provided by another module, remove the field
      // and add the other module as a dependency.
      if (isset($map[$identifier]) && $map[$identifier] != $module_name) {
        if (isset($export['configuration']['field'][$identifier])) {
          unset($export['configuration']['field'][$identifier]);
        }
        $module = $map[$identifier];
        $export['dependencies'][$module] = $module;
      }
      // If the field has not yet been exported, add it
      $export['configuration']['field'][$identifier] = $identifier;
      $new_dependencies[$field['field_config']['module']] = $field['field_config']['module'];
      $new_dependencies[$field['field_config']['storage']['module']] = $field['field_config']['storage']['module'];
      $new_dependencies[$field['field_instance']['widget']['module']] = $field['field_instance']['widget']['module'];
      foreach ($field['field_instance']['display'] as $key => $display) {
        if (isset($display['module'])) {
          $new_dependencies[$display['module']] = $display['module'];
          // @TODO: handle the pipe to image styles
        }
      }
      // Merge new dependencies with global.
      $export['dependencies'] = array_merge($export['dependencies'], $new_dependencies);
      // If taxonomy field, add in the vocabulary
      if ($field['field_config']['type'] == 'taxonomy_term_reference' && !empty($field['field_config']['settings']['allowed_values'])) {
        foreach ($field['field_config']['settings']['allowed_values'] as $allowed_values) {
          if (!empty($allowed_values['vocabulary'])) {
            $export['configuration_dependency']['configuration']['taxonomy'][$allowed_values['vocabulary']] = $identifier;
            $export['configuration_dependency']['modules']['taxonomy'][$allowed_values['vocabulary']] = serialize($new_dependencies);
            $pipe['taxonomy'][] = $allowed_values['vocabulary'];
          }
        }
      }
    }
  }
  return $pipe;
}

/**
 * Implements hook_configuration_export_render().
 */
function field_configuration_export_render($module, $data, $export = NULL) {
  $translatables = $code = array();

  $code[] = '  $fields = array();';
  $code[] = '';
  foreach ($data as $identifier) {
    if ($field = configuration_field_load($identifier)) {
      if (!empty($field['field_instance']['widget']) && !isset($field['field_instance']['widget']['active'])) {
        // The first time this setting is exported, the active value is not exported.
        // Set it explicitly to avoid differences when reverting fields
        $field['field_instance']['widget']['active'] = module_exists($field['field_instance']['widget']['module']);
      }
      unset($field['field_config']['columns']);
      unset($field['field_config']['locked']);
      // Only remove the 'storage' declaration if the field is using the default
      // storage type.
      if ($field['field_config']['storage']['type'] == variable_get('field_storage_default', 'field_sql_storage')) {
        unset($field['field_config']['storage']);
      }
      // If we still have a storage declaration here it means that a non-default
      // storage type was altered into to the field definition. And noone would
      // never need to change the 'details' key, so don't render it.
      if (isset($field['field_config']['storage']['details'])) {
        unset($field['field_config']['storage']['details']);
      }

      _field_configuration_export_sort($field);
      $field_export = configuration_var_export($field, '  ');
      $field_identifier = configuration_var_export($identifier);
      $code[] = "  // Exported field: {$field_identifier}";
      $code[] = "  \$fields[{$field_identifier}] = {$field_export};";
      $code[] = "";

      // Add label and description to translatables array.
      if (!empty($field['field_instance']['label'])) {
        $translatables[] = $field['field_instance']['label'];
      }
      if (!empty($field['field_instance']['description'])) {
        $translatables[] = $field['field_instance']['description'];
      }
    }
  }
  if (!empty($translatables)) {
    $code[] = configuration_translatables_export($translatables, '  ');
  }
  $code[] = '  return $fields;';

  $code = implode("\n", $code);
  return array('field_default_fields' => $code);
}

// Helper to enforce consistency in field export arrays.
function _field_configuration_export_sort(&$field, $sort = TRUE) {

  // Some arrays are not sorted to preserve order (for example allowed_values).
  static $sort_blacklist = array('allowed_values');

  if ($sort) {
    ksort($field);
  }
  foreach ($field as $k => $v) {
    if (is_array($v)) {
      _field_configuration_export_sort($field[$k], !in_array($k, $sort_blacklist));
    }
  }
}

/**
 * Implements hook_configuration_revert().
 */
function field_configuration_revert($identifiers, $module_name = 'configuration') {
  field_configuration_rebuild($identifiers, $module_name);
}

/**
 * Implements of hook_configuration_rebuild().
 * Rebuilds fields from code defaults.
 */
function field_configuration_rebuild($identifiers, $module_name = 'configuration') {
  if ($fields = configuration_get_default('field', $module_name)) {
    field_info_cache_clear();
    foreach ($fields as $identifier => $field) {
      if (in_array($identifier, $identifiers) || $identifiers[0] == '#import_all') {
        // Create or update field.
        $field_config = $field['field_config'];
        if ($existing_field = field_info_field($field_config['field_name'])) {
          field_update_field($field_config);
        }
        else {
          field_create_field($field_config);
        }

        // Create or update field instance.
        $field_instance = $field['field_instance'];
        $existing_instance = field_info_instance($field_instance['entity_type'], $field_instance['field_name'], $field_instance['bundle']);
        if ($existing_instance) {
          field_update_instance($field_instance);
        }
        else {
          field_create_instance($field_instance);
        }
        variable_set('menu_rebuild_needed', TRUE);
      }
    }
  }
}

/**
 * Load a field's configuration and instance configuration by an
 * entity_type-bundle-field_name identifier.
 */
function configuration_field_load($identifier) {
  list($entity_type, $bundle, $field_name) = explode('-', $identifier);
  $field_info = field_info_field($field_name);
  $instance_info = field_info_instance($entity_type, $field_name, $bundle);
  if ($field_info && $instance_info) {
    unset($field_info['id']);
    unset($field_info['bundles']);
    unset($instance_info['id']);
    unset($instance_info['field_id']);
    return array(
      'field_config' => $field_info,
      'field_instance' => $instance_info,
    );
  }
  return FALSE;
}

function configuration_check_field($identifier) {
  // Set a static variable that we can access across this request.
  $from_activestore = &drupal_static('configuration_from_activestore');
  $component = 'field';

  if (file_exists("config://configuration.field.inc")) {
    // Load the current configuration file on disk
    include_once drupal_realpath("config://configuration.field.inc");

    // Export just the field we're tracking.
    module_load_include('inc', 'configuration', 'configuration.export');

    // Export the field we just saved and evaluate the export to $fields
    $code = field_configuration_export_render('configuration', array($identifier));
    eval(array_pop($code));

    $fields_code = configuration_field_default_fields();

    // If the activestore doesn't exist it is most likely because this configuration
    // only exists in code.
    if (empty($fields)) {
      configuration_set_status($component, $identifier, CONFIGURATION_TRACKED_DATASTORE_ONLY);
    }

    configuration_update_component_status($component, $identifier, $fields, $fields_code, $from_activestore);
  }
}

function configuration_hash_field($identifier) {
  // Export just the field we're tracking.
  module_load_include('inc', 'configuration', 'configuration.export');

  $data = field_configuration_export_options();

  // Export the field we just saved and evaluate the export to hash
  $code = field_configuration_export_render('configuration', array($data[$identifier]));
  eval(array_pop($code));

  return md5(serialize($fields[$identifier]));
}
