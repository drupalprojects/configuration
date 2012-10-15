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
    case 'drush:cimp':
      return dt("Import configurations into the ActiveStore.");
    case 'drush:cexp':
      return dt("Export a configuration to the DataStore.");
    case 'drush:crev':
      return dt("Restore a configuration from the staging area.");
    case 'drush:csta':
      return dt("Start tracking a configuration changes.");
    case 'drush:csto':
      return dt("Stop tracking a configuration changes.");
    case 'drush:clist':
      return dt("Return the list of components and identifiers");
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
  $items['config-import'] = array(
    'callback' => '_configuration_import_configurations',
    'description' => 'Import a configuration to the active store.',
    'aliases' => array('cimp'),
    'examples' => array(
      'drush cimp' => 'Loads all the files that have configurations into the ActiveStore',
      'drush cimp content_type.article variable.theme_settings' => 'Loads the configurations for content type Article and the variable theme_settings into the ActiveStore',
    ),
    'options' => array(
      'all' => 'import all components',
      'exclude-dependencies' => 'Export a configuration without export its dependencies.',
      'exclude-optionals' => 'Export a configuration without export its optional configurations.',
      'start-tracking' => 'Export a configuration and automatically start to tracking it.',
    ),
    'arguments' => array(),
  );
  $items['config-export'] = array(
    'callback' => '_configuration_export_to_datastore',
    'description' => 'Export a configuration to the datastore.',
    'aliases' => array('cexp'),
    'examples' => array(
      'drush cexp --all' => 'Export all tracked components',
      'drush cexp content_type.article' => 'Export the content type article and all its dependencies to the datastore',
      'drush cexp content_type.article --exclude-dependencies --exclude-optionals' => 'Export the only the content type article to the datastore  without include its dependencies and optional configurations.',
    ),
    'options' => array(
      'all' => 'Export all tracked components',
      'exclude-dependencies' => 'Export a configuration without export its dependencies.',
      'exclude-optionals' => 'Export a configuration without export its optional configurations.',
      'start-tracking' => 'Export a configuration and automatically start to tracking it.',
    ),
    'arguments' => array(),
  );
  $items['config-revert'] = array(
    'callback' => '_configuration_revert_activestore',
    'description' => 'Revert a configuration from the staging area.',
    'aliases' => array('crev'),
    'examples' => array(
      'drush crev --all' => 'Revert all tracked components',
      'drush crev content_type.article' => 'Revert the content type article using the backup saved in the staging area and its dependencies.',
      'drush crev content_type.article --exclude-dependencies --exclude-optionals' => 'Revert the content type article using the backup saved in the staging area and its dependencies without include its dependencies and optional configurations.',
    ),
    'options' => array(
      'all' => 'revert all components',
      'exclude-dependencies' => 'Revert a configuration without export its dependencies.',
      'exclude-optionals' => 'Revert a configuration without export its optional configurations.',
    ),
    'arguments' => array(),
  );
  $items['config-start-tracking'] = array(
    'callback' => '_configuration_start_tracking',
    'description' => 'Start tracking configuration changes.',
    'aliases' => array('csta'),
    'examples' => array(
      'drush csta --all' => 'Start tracking changes in all identifiers',
      'drush csta content_type.article' => 'Start tracking changes in the content type article and in all its dependencies',
      'drush crev content_type.article --exclude-dependencies --exclude-optionals' => 'Revert the content type article using the backup saved in the staging area and its dependencies without include its dependencies and optional configurations.',
    ),
    'options' => array(
      'all' => 'Start tracking all components',
      'exclude-dependencies' => 'Export a configuration without export its dependencies.',
      'exclude-optionals' => 'Export a configuration without export its optional configurations.',
    ),
    'arguments' => array(),
  );
  $items['config-stop-tracking'] = array(
    'callback' => '_configuration_stop_tracking',
    'description' => 'Stop tracking configuration changes.',
    'aliases' => array('csto'),
    'examples' => array(
      'drush csto --all' => 'Stop tracking changes in all identifiers',
      'drush csto content_type.article' => 'Stop tracking changes in the content type article and in all its dependencies',
    ),
    'options' => array(
      'all' => 'Stop tracking all components',
      'exclude-dependencies' => 'Export a configuration without export its dependencies.',
      'exclude-optionals' => 'Export a configuration without export its optional configurations.',
    ),
    'arguments' => array(),
  );

  $items['config-list'] = array(
    'callback' => '_configuration_list',
    'description' => 'Return the list of components and identifiers',
    'aliases' => array('clist'),
    'examples' => array(
      'drush clist' => 'Return the list of components and identifiers',
    ),
    'arguments' => array(),
  );
  return $items;
}

/**
 * Name: _get_component_and_identifier()
 * Input : None
 * Output : List of all components and its identifier
 */
function _get_component_and_identifier() {
  //get all of the components
  $components = configuration_get_handlers();
  foreach (array_keys($components) as $component) {
    $handler = Configuration::getConfigurationHandler($component);
    //get all identifiers from a component
    $identifiers = $handler::getAllIdentifiers($component);
    foreach ($identifiers as $identifier) {
      $rows[] = array(
        'handler' => $handler::getComponentHumanName($component),
        'machine_name' => $component,
        'identifier' => $identifier,
      );
    }
  }
  return $rows;
}

/**
 * Name: _components_indentifier_formater()
 * Input : None
 * Output : List of all components and its identifier in the following format
 *          components.identifier
 */

function _components_indentifier_formater() {
  $formated = array();
  $components = _get_component_and_identifier();
  foreach ($components as $component) {
    if (!empty($component['identifier'])) {
      $formated[] =  $component['machine_name'] . "." . $component['identifier'];
    }
  }
  return $formated;
}

/*
 * Configuration list drush command
 */
function _configuration_list() {
  $header = array();
  $header[] = array(
    'Component::Name',
    'Component::Machine Name',
    'Identifier',
  );
  $content = _get_component_and_identifier();
  $table = array_merge($header, $content);
  drush_print_table($table, TRUE);
}


function _configuration_get_identifiers() {
  if ($args = func_get_args()) {
    if (empty($args)) {
      return drush_set_error('', 'No components supplied.');
    }
  }
  $component = array_shift($args);
  $handlers = configuration_get_handlers();
  if (empty($handlers[$component])) {
    return drush_set_error('', dt('The component @component is not valid or the module to export the configuration is not installed.', array('@component' => $component)));
  }
  $handler = Configuration::getConfigurationHandler($component);
  $identifiers = $handler::getAllIdentifiers($component);
  $rows = array();
  foreach ($identifiers as $identifier) {
    $rows[]['identifier'] = $identifier;
  }
  drush_print_table($rows);
}

function _configuration_get_components() {
  $handlers = configuration_get_handlers();
  $rows = array();
  $rows[] = array(
    'Name',
    'Machine Name'
  );
  foreach (array_keys($handlers) as $component) {
    $handler = Configuration::getConfigurationHandler($component);
    $rows[] = array(
      'handler' => $handler::getComponentHumanName($component),
      'machine_name' => $component,
    );
  }
  drush_print_table($rows, TRUE);
}

function _configuration_export_to_datastore() {
  $export_all = drush_get_option('all');
  if (!empty($export_all)) {
    $args = _components_indentifier_formater();
  }
  else {
    $args = func_get_args();
  }

  if (empty($args)) {
    return drush_set_error('', dt('No configurations were selected to export'));
  }
  $handlers = configuration_get_handlers();
  foreach ($args as $arg) {

    list($component, $identifier) = explode('.', $arg, 2);

    if (empty($handlers[$component])) {
      return drush_set_error('', dt('The component @component is not valid or the module to export the configuration is not installed.', array('@component' => $component)));
    }

    if (empty($identifier)) {
      return drush_set_error('', dt('No identifier supplied for @arg.', array('@arg' => $arg)));
    }
  }
  $rows = array();
  $export_dependencies = !drush_get_option('exclude-dependencies');
  $export_optionals = !drush_get_option('exclude-optionals');
  $start_tracking = drush_get_option('start-tracking');
  $result = Configuration::exportToDataStore($args, $export_dependencies, $export_optionals, $start_tracking);
  $hashes = $result->getInfo('hash');
  foreach ($result->getInfo('exported') as $id => $exported) {
    $rows[] = array(
      'exported' => $exported,
      'hash' => $hashes[$id],
    );
  }

  drush_log(dt('The configurations for the following items have been created/updated.'), 'ok');
  drush_print_table($rows);
}

function _configuration_revert_activestore() {
  $revert_all = drush_get_option('all');
  if (!empty($revert_all)) {
    $args = _components_indentifier_formater();
  }
  else {
    $args = func_get_args();
  }

  if (empty($args)) {
    return drush_set_error('', dt('No configurations were selected to revert'));
  }

  $handlers = configuration_get_handlers();
  foreach ($args as $arg) {

    list($component, $identifier) = explode('.', $arg, 2);

    if (empty($handlers[$component])) {
      return drush_set_error('', dt('The component @component is not valid or the module to revert the configuration is not installed.', array('@component' => $component)));
    }

    if (empty($identifier)) {
      return drush_set_error('', dt('No identifier supplied for @arg.', array('@arg' => $arg)));
    }
  }
  $rows = array();
  $revert_dependencies = !drush_get_option('exclude-dependencies');
  $revert_optionals = !drush_get_option('exclude-optionals');
  $result = Configuration::revertActiveStore($args, $revert_dependencies, $revert_optionals);
  foreach ($result->getInfo('imported') as $exported) {
    $rows[]['imported'] = $exported;
  }

  drush_log(dt('The configurations for the following items have been reverted.'), 'ok');
  drush_print_table($rows);
}

function _configuration_start_tracking() {
  $start_tracking_all = drush_get_option('all');
  if (!empty($start_tracking_all)) {
    $args = _components_indentifier_formater();
  }
  else {
    $args = func_get_args();
  }

  if (empty($args)) {
    return drush_set_error('', dt('No configurations were selected to track'));
  }

  $handlers = configuration_get_handlers();
  foreach ($args as $arg) {

    list($component, $identifier) = explode('.', $arg, 2);

    if (empty($handlers[$component])) {
      return drush_set_error('', dt('The component @component is not valid or the module to track the configuration is not installed.', array('@component' => $component)));
    }

    if (empty($identifier)) {
      return drush_set_error('', dt('No identifier supplied for @arg.', array('@arg' => $arg)));
    }
  }
  $rows = array();
  $start_tracking_dependencies = !drush_get_option('exclude-dependencies');
  $start_tracking_optionals = !drush_get_option('exclude-optionals');
  $result = Configuration::startTracking($args, $start_tracking_dependencies, $start_tracking_optionals);
  foreach ($result->getInfo('exported') as $tracking) {
    $rows[]['exported'] = $tracking;
  }

  drush_log(dt('The configurations changes for for the following items are now being tracked.'), 'ok');
  drush_print_table($rows);
}

function _configuration_stop_tracking() {
  $stop_tracking_all = drush_get_option('all');
  if (!empty($stop_tracking_all)) {
    $args = _components_indentifier_formater();
  }
  else {
    $args = func_get_args();
  }
  if (empty($args)) {
    return drush_set_error('', dt('No configurations were selected to export'));
  }

  $handlers = configuration_get_handlers();
  foreach ($args as $arg) {

    list($component, $identifier) = explode('.', $arg, 2);

    if (empty($handlers[$component])) {
      return drush_set_error('', dt('The component @component is not valid or the module to export the configuration is not installed.', array('@component' => $component)));
    }

    if (empty($identifier)) {
      return drush_set_error('', dt('No identifier supplied for @arg.', array('@arg' => $arg)));
    }
  }
  $rows = array();
  $stop_tracking_dependencies = !drush_get_option('exclude-dependencies');
  $stop_tracking_optionals = !drush_get_option('exclude-optionals');
  $result = Configuration::stopTracking($args, $stop_tracking_dependencies, $stop_tracking_optionals);
  foreach ($result->getInfo('untracked') as $untracked) {
    $rows[]['untracked'] = $untracked;
  }

  drush_log(dt('The configurations changes for for the following items are not being tracked anymore.'), 'ok');
  drush_print_table($rows);
}

function _configuration_import_configurations() {
  $import_all = drush_get_option('all');
  if (!empty($import_all)) {
    $args = _components_indentifier_formater();
  }
  else {
    $args = func_get_args();
  }
  _configuration_import_load_configurations('import', $args);
}

function _configuration_import_load_configurations($action, $args) {
  if ($action == 'import') {
    if (!drush_confirm(dt('Are you sure you want to import this configurations? This will override the configurations of the ActiveStore'))) {
      drush_die('Aborting.');
    }
  }

  if (drush_get_option(array('skip-enable-modules'), FALSE)) {
    if ($action == 'import') {
      Configuration::importToActiveStore($args);
    }
    else {
      configuration_load_configurations($args);
    }
  }
  else {
    $import_dependencies = !drush_get_option('exclude-dependencies');
    $import_optionals = !drush_get_option('exclude-optionals');
    $start_tracking = drush_get_option('start-tracking');
    $settings = Configuration::discoverRequiredModules($args, $import_dependencies, $import_optionals);

    $missing = $settings->getInfo('modules_missing');
    $to_install = $settings->getInfo('modules_to_install');

    if (count($missing)) {
      drush_set_error('', dt('Missing required modules. None configuration was processed.'));
      drush_set_error('', dt('The following modules are required to @action the configurations but are not available to install: !modules', array('@action' => $action, '!modules' => implode(', ', $missing))));
      drush_die('Aborting.');
    }
    else {
      if (count($to_install)) {
        drush_print(dt('The following modules will be installed in order to @action the configurations:', array('@action' => $action)));
        drush_print(implode(', ', $to_install));
        if (!drush_confirm(dt('Do you really want to continue?'))) {
          drush_die('Aborting.');
        }

        module_enable($to_install);
        foreach ($to_install as $module) {
          drush_log(dt('Installed @module.', array('@module' => $module)), 'ok');
        }
        drupal_flush_all_caches();
      }

      $import_results = Configuration::importToActiveStore($args, $import_dependencies, $import_optionals, $start_tracking);
      $rows = array();
      foreach ($import_results->getInfo('imported') as $imported) {
        $rows[]['identifier'] = $imported;
      }

      if ($action == 'import') {
        drush_log(dt('The configurations for the following items have been imported.'), 'ok');
      }
      else {
        drush_log(dt('The configurations for the following items have been loaded.'), 'ok');
      }
      drush_print_table($rows);
      if ($action == 'import') {
        drupal_flush_all_caches();
      }
    }
  }
}
