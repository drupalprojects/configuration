<?php

/**
 * @file
 * configuration.drush.inc
 * Let you perform configuration actions from the console.
 */

use Drupal\configuration\Config\Configuration;

/**
 * Implements of hook_drush_help()
 */
function configuration_drush_help($section) {
  switch ($section) {
    case 'drush:cgi':
      return dt("Return the list of identifiers for a given component.");
    case 'drush:cgc':
      return dt("Return the list of configurations components that can be exported.");
    case 'drush:cexp':
      return dt("Export a configuration to the DataStore.");
    case 'drush:cback':
      return dt("Backup a configuration to Staging Area.");
    case 'drush:cimp':
      return dt("Import configurations into the ActiveStore.");
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
      'component' => 'The name of the component to get the identifiers',
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
  $items['config-export'] = array(
    'callback' => '_configuration_export_to_datastore',
    'description' => 'Export a configuration to the datastore.',
    'aliases' => array('cexp'),
    'examples' => array(
      'drush cexp content_type.article' => 'Export the content type article and all its dependencies to the datastore',
    ),
    'arguments' => array(),
  );
  $items['config-backup'] = array(
    'callback' => '_configuration_backup_to_staging',
    'description' => 'Backup a configuration to the staging area.',
    'aliases' => array('cback'),
    'examples' => array(
      'drush cback content_type.article' => 'Backups the content type article and all its dependencies into the staging area',
    ),
    'arguments' => array(),
  );
  $items['config-import'] = array(
    'callback' => '_configuration_import_configurations',
    'description' => 'Import a configuration to the active store.',
    'aliases' => array('cimp'),
    'examples' => array(
      'drush cimp' => 'Loads all the files that have configurations into the ActiveStore',
      'drush cimp content_type.article variable.theme_settings' => 'Loads the configurations for content type Article and the variable theme_settings into the ActiveStore',
    ),
    'arguments' => array(),
  );
  $items['config-revert'] = array(
    'callback' => '_configuration_revert_configurations',
    'description' => 'Revert a configuration using using the Staging Data.',
    'aliases' => array('crev'),
    'examples' => array(
      'drush crev' => 'Loads all the files that have configurations into the ActiveStore',
      'drush crev content_type.article variable.theme_settings' => 'Revert the configurations for content type Article and the variable theme_settings into the ActiveStore',
    ),
    'arguments' => array(),
  );

  return $items;
}

function _configuration_get_identifiers() {
  if ($args = func_get_args()) {
    if (empty($args)) {
      return drush_set_error('', 'No components supplied.');
    }
  }

  $component = array_shift($args);
  $handlers = configurarion_get_handlers();
  if (empty($handlers[$component])) {
    return drush_set_error('', dt('The component @component is not valid or the module to export the configuration is not installed.', array('@component' => $component)));
  }

  $identifiers = configurarion_get_identifiers($component);
  $rows = array();
  foreach ($identifiers as $identifier) {
    $rows[]['identifier'] = $identifier;
  }
  drush_print_table($rows);
}

function _configuration_get_components() {
  $handlers = configurarion_get_handlers();
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

function _configuration_export_to_datastore() {
  $args = func_get_args();

  if (empty($args)) {
    return drush_set_error('', dt('No configurations were selected to export'));
  }

  $handlers = configurarion_get_handlers();
  foreach ($args as $arg) {

    list($component, $identifier) = explode('.', $arg, 2);

    if (empty($handlers[$component])) {
      return drush_set_error('', dt('The component @component is not valid or the module to export the configuration is not installed.', array('@component' => $component)));
    }

    if (empty($identifier)) {
      return drush_set_error('', dt('No identifier supplied for @arg.', array('@arg' => $arg)));
    }

    $rows = array();
    foreach (array_keys(configuration_export_to_datastore($component, $identifier)) as $exported) {
      $rows[]['exported'] = $exported;
    }
  }
  drush_log(dt('The configurations for the following items have been created/updated.'), 'ok');
  drush_print_table($rows);
}

function _configuration_backup_to_staging() {
  $args = func_get_args();

  if (empty($args)) {
    return drush_set_error('', dt('No configurations were selected to backup'));
  }

  $handlers = configurarion_get_handlers();
  foreach ($args as $arg) {

    list($component, $identifier) = explode('.', $arg, 2);

    if (empty($handlers[$component])) {
      return drush_set_error('', dt('The component @component is not valid or the module to backup the configuration is not installed.', array('@component' => $component)));
    }

    if (empty($identifier)) {
      return drush_set_error('', dt('No identifier supplied for @arg.', array('@arg' => $arg)));
    }

    $rows = array();
    foreach (array_keys(configuration_backup_to_staging($component, $identifier)) as $backuped) {
      $rows[]['backuped'] = $backuped;
    }
  }
  drush_log(dt('The configurations for the following items have been backuped.'), 'ok');
  drush_print_table($rows);
}

function _configuration_import_configurations() {

  if (!drush_confirm(dt('Are you sure you want to import this configurations? This will override the configurations of the ActiveStore'))) {
    drush_die('Aborting.');
  }

  $args = func_get_args();
  if (in_array('--skip-enable-modules', $args)) {
    array_shift($args);
    configuration_import_configurations($args);
  }
  else {
    $handlers = configurarion_get_handlers();
    $components = array();
    for ($x = 1; $x <= 3; $x++) {
      foreach ($handlers as $handler) {
        $class = '\\' . $handler['namespace'] . '\\' . $handler['handler'];
        $components = array_merge($components, $class::scanDataStore());
      }
    }
    $configurations = Configuration::importConfigurations($components, $args, TRUE, TRUE, FALSE);

    $modules = array();
    foreach ($configurations as $configuration) {
      $modules = array_merge($modules, $configuration->getRequiredModules());
    }

    $missing = array();
    $to_install = array();
    foreach ($modules as $module_name => $status) {
      if ($status == CONFIGURATION_MODULE_MISSING) {
        $missing[] = $module_name;
      }
      elseif ($status == CONFIGURATION_MODULE_TO_INSTALL) {
        $to_install[] = $module_name;
      }
    }

    $missing = array_filter(array_unique($missing));
    if (count($missing)) {
      drush_set_error('', dt('Missing required modules. None configuration was imported.'));
      drush_set_error('', dt('The following modules are required to import the configurations but are not available to install: !modules', array('!modules' => implode(', ', $missing))));
      drush_die('Aborting.');
    }
    else {
      if (count($to_install)) {
        drush_print('The following modules will be installed in order to enable the configurations:');
        drush_print(implode(', ', $to_install));
        if (!drush_confirm(dt('Do you really want to continue?'))) {
          drush_die('Aborting.');
        }

        module_enable($to_install);
        drupal_flush_all_caches();
      }
      $rows = array();
      foreach ($configurations as $configuration) {
        $configuration
          ->revert()
          ->setStatus(CONFIGURATION_NEEDS_REBUILD)
          ->saveToStaging();

        $rows[]['identifier'] = $configuration->getUniqueId();
      }

      drush_log(dt('The configurations for the following items have been imported.'), 'ok');
      drush_print_table($rows);
      drupal_flush_all_caches();
    }
  }
}

function _configuration_revert_configurations() {
  if (!drush_confirm(dt('Are you sure you want to revert this configurations? This will override the configurations of the ActiveStore'))) {
    drush_die('Aborting.');
  }

  $args = func_get_args();
  $reverted = configuration_revert_configurations($args);
  drush_log(dt('The configurations for the following items have been reverted.'), 'ok');
  $rows = array();
  foreach ($reverted as $config) {
    $rows[]['identifier'] = $config;
  }
  drush_print_table($rows);
}
