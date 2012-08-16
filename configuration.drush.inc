<?php

/**
 * @file
 * configuration.drush.inc
 * Let you perform configuration actions from the console.
 */

/**
 * Implements of hook_drush_help()
 */
function configuration_drush_help($section) {
  switch ($section) {
    case 'drush:cgi':
      return dt("Return the list of identifiers for a given component.");
    case 'drush:cgc':
      return dt("Return the list of configurations components that can be exported.");
    case 'drush:ced':
      return dt("Export a configuration to the datastore.");
    case 'drush:cinc':
      return dt("Import new configurations.");
  }
}

/**
 * Implements of hook_drush_command().
 */
function configuration_drush_command() {
  $items = array();
  $items['config-get-identifiers'] = array(
    'callback' => '_configuration_get_identifiers',
    'description' => 'Return the list of identifiers for a given component.',
    'aliases' => array('cgi'),
    'examples' => array(
      'drush cgi content_type' => 'Return the list of all available content types to export',
    ),
    'arguments' => array(
      'componenet' => 'The name of the component to get the identifiers',
    ),
  );
  $items['config-get-components'] = array(
    'callback' => '_configuration_get_components',
    'description' => 'Return the list of configurations components that can be exported.',
    'aliases' => array('cgc'),
    'examples' => array(
      'drush cgc' => 'Return the list of all available configuration components that can be exported',
    ),
    'arguments' => array(),
  );
  $items['config-export-to-datastore'] = array(
    'callback' => '_configuration_export_to_datastore',
    'description' => 'Export a configuration to the datastore.',
    'aliases' => array('ced'),
    'examples' => array(
      'drush ced content_type article' => 'Export the content type article and all its dependencies to the datastore',
    ),
    'arguments' => array(),
  );
  $items['config-import-new-configurations'] = array(
    'callback' => '_configuration_import_new_configurations',
    'description' => 'Export a configuration to the datastore.',
    'aliases' => array('cinc'),
    'examples' => array(
      'drush cinc' => 'Loads all the files that have configurations into the ActiveStore',
    ),
    'arguments' => array(),
  );
  return $items;
}

function _configuration_get_identifiers($component) {
  $identifiers = configurarion_get_identifiers($component);
  $rows = array();
  foreach ($identifiers as $identifier) {
    $rows[]['identifier'] = $identifier;
  }
  drush_print_table($rows);
}

function _configuration_get_components() {
  $handlers = configurarion_get_components();
  $rows = array();
  $rows[] = array(
    'Name',
    'Machine Name'
  );
  foreach ($handlers as $machine_name => $handler) {
    $rows[] = array(
      'handler' => $handler['name'],
      'machine_name' => $machine_name,
    );
  }
  drush_print_table($rows, TRUE);
}

function _configuration_export_to_datastore($component, $identifier) {
  configuration_export_to_datastore($component, $identifier);
}

function _configuration_import_new_configurations() {
  configuration_import_new_configurations();
}
