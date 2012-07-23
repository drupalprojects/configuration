<?php

/**
 * Implements hook_configuration_api().
 */
function taxonomy_configuration_api() {
  return array(
    'taxonomy' => array(
      'name' => t('Taxonomy'),
      'feature_source' => TRUE,
      'default_hook' => 'taxonomy_default_vocabularies',
      'default_file' => CONFIGURATION_DEFAULTS_INCLUDED,
    ),
  );
}

/**
 * Implements hook_configuration_export_options().
 */
function taxonomy_configuration_export_options() {
  $vocabularies = array();
  foreach (taxonomy_get_vocabularies() as $vocabulary) {
    $vocabularies[$vocabulary->machine_name] = $vocabulary->name;
  }
  return $vocabularies;
}

/**
 * Implements hook_configuration_export().
 *
 * @todo Test adding existing dependencies.
 */
function taxonomy_configuration_export($data, &$export, $module_name = '') {
  $pipe = array();

  // taxonomy_default_vocabularies integration is provided by configuration.
  $export['dependencies']['configuration'] = 'configuration';
  $export['dependencies']['taxonomy'] = 'taxonomy';

  // Add dependencies for each vocabulary.
  $map = configuration_get_default_map('taxonomy');
  foreach ($data as $machine_name) {
    if (isset($map[$machine_name]) && $map[$machine_name] != $module_name) {
      $export['dependencies'][$map[$machine_name]] = $map[$machine_name];
    }

    $export['configuration']['taxonomy'][$machine_name] = $machine_name;

    $fields = field_info_instances('taxonomy_term', $machine_name);
    foreach ($fields as $name => $field) {
      $pipe['field'][] = "taxonomy_term-{$field['bundle']}-{$field['field_name']}";
    }

  }
  return $pipe;
}

/**
 * Implements hook_configuration_export_render().
 */
function taxonomy_configuration_export_render($module, $data) {
  $vocabularies = taxonomy_get_vocabularies();
  $code = array();
  foreach ($data as $machine_name) {
    foreach ($vocabularies as $vocabulary) {
      if ($vocabulary->machine_name == $machine_name) {
        // We don't want to break the entity cache, so we need to clone the
        // vocabulary before unsetting the id.
        $vocabulary = clone $vocabulary;
        unset($vocabulary->vid);
        $code[$machine_name] = $vocabulary;
      }
    }
  }
  $code = "  return " . configuration_var_export($code, '  ') . ";";
  return array('taxonomy_default_vocabularies' => $code);
}

/**
 * Implements hook_configuration_revert().
 */
function taxonomy_configuration_revert($identifiers, $module_name = 'configuration') {
  taxonomy_configuration_rebuild($identifiers, $module_name);
}

/**
 * Implements hook_configuration_rebuild().
 *
 * Rebuilds Taxonomy vocabularies from code defaults.
 */
function taxonomy_configuration_rebuild($identifiers, $module_name = 'configuration') {
  if ($vocabularies = configuration_get_default('taxonomy', $module_name)) {
    $existing = taxonomy_get_vocabularies();
    foreach ($vocabularies as $vocabulary) {
      if (in_array($vocabulary['machine_name'], $identifiers) || $identifiers[0] == '#import_all') {
        $vocabulary = (object) $vocabulary;
        foreach ($existing as $existing_vocab) {
          if ($existing_vocab->machine_name === $vocabulary->machine_name) {
            $vocabulary->vid = $existing_vocab->vid;
          }
        }
        taxonomy_vocabulary_save($vocabulary);
      }
    }
  }
}

function configuration_check_taxonomy($identifier) {
  // Get static variable that we can access across this request.
  $from_activestore = &drupal_static('configuration_from_activestore');
  $component = 'taxonomy';

  if (file_exists("config://configuration.taxonomy.inc")) {
    // Load the current configuration file on disk
    include_once drupal_realpath("config://configuration.taxonomy.inc");

    // Export just the field we're tracking.
    module_load_include('inc', 'configuration', 'configuration.export');

    // Export the field we just saved and evaluate the export to $fields
    $code = taxonomy_configuration_export_render('configuration', array($identifier));
    eval('$vocab = ' . substr(array_pop($code), 8));

    $vocab_code = configuration_taxonomy_default_vocabularies();

    // If the activestore doesn't exist it is most likely because this configuration
    // only exists in code.
    if (empty($vocab)) {
      configuration_set_status($component, $identifier, CONFIGURATION_TRACKED_DATASTORE_ONLY);
    }

    configuration_update_component_status($component, $identifier, $vocab, $vocab_code, $from_activestore);
  }
}

function configuration_hash_taxonomy($identifier) {
  // Export just the field we're tracking.
  module_load_include('inc', 'configuration', 'configuration.export');

  $data = taxonomy_configuration_export_options();

  // Export the field we just saved and evaluate the export to hash
  $code = taxonomy_configuration_export_render('configuration', array($identifier));
  eval('$vocab = ' . substr(array_pop($code), 8));

  return md5(serialize($vocab[$identifier]));
}