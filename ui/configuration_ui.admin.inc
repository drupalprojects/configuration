<?php

/**
 * @file
 * User interface functions for Configuration Management.
 */

use Drupal\configuration\Config\Configuration;
use Drupal\configuration\Utils\ConfigIteratorSettings;

/**
 * Menu Callback Form.
 */
function configuration_ui_tracking_form($form, &$form_state) {
  $component_exists = FALSE;
  $handlers = configuration_configuration_handlers();

  $form['packages'] = array('#type' => 'vertical_tabs');
  $form['#attached']['css'] = array(
    drupal_get_path('module', 'configuration_ui') . '/theme/configuration.css'
  );
  $form['#attached']['js'] = array(
    drupal_get_path('module', 'configuration_ui') . '/js/configuration_ui.js'
  );

  $tracked = Configuration::trackedConfigurations();

  foreach ($tracked as $component => $list) {
    $options = array();
    foreach ($list as $identifier => $hash) {

      $id = $component . '.' . $identifier;
      $status = '<span class ="config-status" rel="' . $id . '">' . t('Processing...') . '</span>';

      $options[$id] = array(
        $identifier,
        $status,
        l(t('Stop Tracking'), 'admin/config/system/configuration/stop_tracking/' . $id) . ' | ' . l(t('View info'), 'admin/config/configuration/view/' . $id)
      );
    }

    if (!empty($options)) {
      $component_exists = TRUE;
      $component_name = check_plain($handlers[$component]['name']);
      $form[$component] = array(
        '#type' => 'fieldset',
        '#group' => 'packages',
        '#title' => check_plain($component_name),
        '#description' => t('Currenly tracked configurations for: @component', array('@component' => $component_name)),
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
        '#tree' => TRUE,
        '#attached' => array(
          'js' => array(
            'vertical-tabs' => drupal_get_path('module', 'configuration_ui') . '/theme/vertical-tabs.js',
          ),
        ),
      );
      $form[$component]['tracked'] = array(
        '#type' => 'tableselect',
        '#header' => array('Configuration', 'Status', 'Operations'),
        '#options' => $options,
        '#attributes' => array('class' => array('configuration'))
      );
      $form['dependencies_option'] = array(
        '#type' => 'fieldset',
        '#title' => t('Dependencies and Optionals'),
        '#description' => t('Choose if you want to process dependent and optional components.'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
      );
      $form['dependencies_option']['include_dependencies'] = array(
        '#type' => 'checkbox',
        '#title' => t('Process component dependencies.'),
        '#default_value' => TRUE,
      );
      $form['dependencies_option']['include_optionals'] = array(
        '#type' => 'checkbox',
        '#title' => t('Process component optionals.'),
        '#default_value' => TRUE,
      );
    }
  }
  $form['buttons'] = array('#theme' => 'configuration_ui_form_buttons', '#tree' => FALSE);
  $form['buttons']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Write Activestore to Datastore'),
    '#submit' => array('configuration_ui_export_to_datastore_form_submit'),
  );
  $form['buttons']['stop_tracking'] = array(
    '#type' => 'submit',
    '#value' => t('Stop Tracking'),
    '#submit' => array('configuration_ui_stop_tracking_form_submit'),
  );
  $form['buttons']['revert'] = array(
    '#type' => 'submit',
    '#value' => t('Import Datastore to Activestore'),
    '#submit' => array('configuration_ui_activate_form_submit'),
  );
  if (!$component_exists) {
    $form['no_configs'] = array(
      '#markup' => t('No Configurations were found. Please use the
      !export_link page to begin tracking new Configurations.',
          array('!export_link' => l(t('Not Tracking'), 'admin/config/system/configuration/notracking'))),
    );
    unset($form['buttons']);
  }
  $form['#validate'][] = 'configuration_ui_tracking_form_validate';
  return $form;
}

/**
 * Menu Callback Form.
 */
function configuration_ui_notracking_form($form, &$form_state) {
  $component_exists = FALSE;
  $handlers = configuration_configuration_handlers();

  $form['packages'] = array('#type' => 'vertical_tabs');
  $form['#attached']['css'] = array(
    drupal_get_path('module', 'configuration_ui') . '/theme/configuration.css'
  );
  $non_tracked = Configuration::nonTrackedConfigurations();

  foreach ($non_tracked as $component => $list) {
    $options = array();
    foreach ($list as $identifier => $id) {
      $options[$id] = array(
        $identifier,
        l(t('Start Tracking'), 'admin/config/system/configuration/start_tracking/' . $id) . ' | ' . l(t('View info'), 'admin/config/configuration/view/' . $id)
      );
    }

    if (!empty($options)) {
      $component_exists = TRUE;
      $form[$component] = array(
        '#type' => 'fieldset',
        '#group' => 'packages',
        '#title' => check_plain($handlers[$component]['name']),
        '#description' => t('Available configurations for: @component', array('@component' => $handlers[$component]['name'])),
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
        '#tree' => TRUE,
        '#attached' => array(
          'js' => array(
            'vertical-tabs' => drupal_get_path('module', 'configuration_ui') . '/theme/vertical-tabs.js',
          ),
        ),
      );
      $form[$component]['tracked'] = array(
        '#type' => 'tableselect',
        '#header' => array('Configuration', 'Operations'),
        '#options' => $options,
      );
      $form['dependencies_option'] = array(
        '#type' => 'fieldset',
        '#title' => t('Dependencies and Optionals'),
        '#description' => t('Choose if you want to process dependent and optional components.'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
      );
      $form['dependencies_option']['include_dependencies'] = array(
        '#type' => 'checkbox',
        '#title' => t('Process component dependencies.'),
        '#default_value' => TRUE,
      );
      $form['dependencies_option']['include_optionals'] = array(
        '#type' => 'checkbox',
        '#title' => t('Process component optionals.'),
        '#default_value' => TRUE,
      );
    }
  }
  $form['buttons'] = array('#theme' => 'configuration_ui_form_buttons', '#tree' => FALSE);
  $form['buttons']['start_tracking'] = array(
    '#type' => 'submit',
    '#value' => t('Start Tracking'),
    '#submit' => array('configuration_ui_start_tracking_form_submit'),
  );
  if (!$component_exists) {
    $form['no_configs'] = array(
      '#markup' => t('No Configurations were found. Please use the
      !export_link page to begin tracking new Configurations.',
          array('!export_link' => l(t('Not Tracking'), 'admin/config/system/configuration/notracking'))),
    );
    unset($form['buttons']);
  }
  $form['#validate'][] = 'configuration_ui_tracking_form_validate';
  return $form;
}

/**
 * Menu Callback Form.
 */
function configuration_ui_config_info($config_id) {
  list($component_name, $identifier) = explode('.', $config_id, 2);
  $handler = Configuration::getConfigurationHandler($component_name);
  $config = new $handler($identifier);

  $config->loadFromActiveStore();

  if (arg(5) == 'status') {
    print $config->getStatus();
    exit();
  }

  $page = '';

  $page .= '<h2>' . t('Configuration for: @config', array('@config' => $config->getUniqueID())) . '</h2>';
  $page .= '<p>' . t('Hash: @hash', array('@hash' => $config->getHash())) . '</p>';
  $page .= '<p>' . t('Status: <strong>@status</strong>', array('@status' => $config->getStatus())) . '</p>';

  $page .= '<p>' . t('Dependencies:') . '</p>';
  $dependencies = array('items' => array_keys($config->getDependencies()));
  $page .= theme('item_list', $dependencies);

  $page .= '<p>' . t('Optional Configurations:') . '</p>';
  $optionals = array('items' => array_keys($config->getOptionalConfigurations()));
  $page .= theme('item_list', $optionals);

  return $page;
}

/**
 * Submit handler for reverting configs.
 */
function configuration_ui_activate_form_submit($form, &$form_state) {
  $args = configuration_ui_get_form_components($form, $form_state);
  $revert_dependencies = $form_state['values']['include_dependencies'];
  $revert_optionals = $form_state['values']['include_optionals'];
  $result = Configuration::revertActiveStore($args, $revert_dependencies, $revert_optionals);
}

/**
 * Submit handler for stop tracking configs.
 */
function configuration_ui_stop_tracking_form_submit($form, &$form_state) {
  $args = configuration_ui_get_form_components($form, $form_state);
  $stop_tracking_dependencies = $form_state['values']['include_dependencies'];
  $stop_tracking_optionals = $form_state['values']['include_optionals'];
  $result = Configuration::stopTracking($args, $stop_tracking_dependencies, $stop_tracking_optionals);
}

/**
 * Confirm form for stop tracking configs.
 */
function configuration_ui_stop_tracking_single_confirm($form, &$form_state, $component) {
  $form['component_id'] = array('#type' => 'value', '#value' => $component);
  return confirm_form($form,
      t('Are you sure you want to stop tracking %component?', array('%component' => $component)),
      'admin/config/system/configuration',
      t('You can start tracking it again later.'),
      t('Stop Tracking'),
      t('Cancel')
  );
}

/**
 * Submit handler for stop tracking a single config.
 */
function configuration_ui_stop_tracking_single_confirm_submit($form, &$form_state) {
  if ($form_state['values']['confirm']) {
    $args = array($form_state['values']['component_id']);
    $stop_tracking_dependencies = FALSE;
    $stop_tracking_optionals = FALSE;
    $result = Configuration::stopTracking($args, $stop_tracking_dependencies, $stop_tracking_optionals);
  }
  $form_state['redirect'] = 'admin/config/system/configuration';
}

/**
 * Confirm form for start tracking configs.
 */
function configuration_ui_start_tracking_single_confirm($form, &$form_state, $component) {
  $form['component_id'] = array('#type' => 'value', '#value' => $component);
  return confirm_form($form,
      t('Are you sure you want to start tracking %component?', array('%component' => $component)),
      'admin/config/system/configuration/notracking',
      t('You can stop tracking it later.'),
      t('Start Tracking'),
      t('Cancel')
  );
}

/**
 * Submit handler for start tracking a single config.
 */
function configuration_ui_start_tracking_single_confirm_submit($form, &$form_state) {
  if ($form_state['values']['confirm']) {
    $args = array($form_state['values']['component_id']);
    $start_tracking_dependencies = FALSE;
    $start_tracking_optionals = FALSE;
    $result = Configuration::startTracking($args, $start_tracking_dependencies, $start_tracking_optionals);
  }
  $form_state['redirect'] = 'admin/config/system/configuration/notracking';
}

/**
 * Submit handler for export to datastore.
 */
function configuration_ui_export_to_datastore_form_submit($form, &$form_state) {
  $args = configuration_ui_get_form_components($form, $form_state);
  $export_dependencies = $form_state['values']['include_dependencies'];
  $export_optionals = $form_state['values']['include_optionals'];
  $result = Configuration::exportToDataStore($args, $export_dependencies, $export_optionals);
}

/**
 * Submit handler for stop tracking configs.
 */
function configuration_ui_start_tracking_form_submit($form, &$form_state) {
  $args = configuration_ui_get_form_components($form, $form_state);
  $start_tracking_dependencies = $form_state['values']['include_dependencies'];
  $start_tracking_optionals = $form_state['values']['include_optionals'];
  $result = Configuration::startTracking($args, $start_tracking_dependencies, $start_tracking_optionals);
}

function configuration_ui_tracking_form_validate() {
  return TRUE;
}

/**
 * Implements hook_theme().
 */
function configuration_ui_theme($existing, $type, $theme, $path) {
  $base = array(
    'path' => drupal_get_path('module', 'configuration_ui') . '/theme',
    'file' => 'theme.inc',
  );
  $items = array();
  $items['configuration_ui_form_buttons'] = array(
    'render element' => 'element',
      ) + $base;

  return $items;
}


/**
 * Menu Callback Form.
 */
function configuration_ui_import_form($form, &$form_state) {
  $form['header'] = array(
    '#markup' => t('Importing configurations will write your configurations directly to the activestore. DO NOT USE THIS ON A PRODUCTION SITE!'),
    '#prefix' => '<div>',
    '#suffix' => '</div>',
  );

  $form['import_configurations'] = array(
    '#type' => 'file',
    '#title' => t('Import configurations'),
    '#description' => t('Upload the file you exported from another site.'),
    '#size' => 40,
  );

  $form['import'] = array(
    '#type' => 'submit',
    '#value' => t('Import'),
  );

  return $form;
}

/**
 * Menu Callback Form.
 */
function configuration_ui_export_form($form, &$form_state) {
  $component_exists = FALSE;
  $handlers = configuration_configuration_handlers();

  $form['packages'] = array('#type' => 'vertical_tabs');
  $form['#attached']['css'] = array(
    drupal_get_path('module', 'configuration_ui') . '/theme/configuration.css'
  );

  $tracked = Configuration::allConfigurations();

  foreach ($tracked as $component => $list) {
    $options = array();
    foreach ($list as $identifier => $tracked) {
      $id = $component . '.' . $identifier;
      $options[$id] = array(
        $identifier,
        ($tracked) ? t('Tracked') : t('Not tracked'),
        l(t('View info'), 'admin/config/configuration/view/' . $id)
      );
    }

    if (!empty($options)) {
      $component_exists = TRUE;
      $component_name = check_plain($handlers[$component]['name']);
      $form[$component] = array(
        '#type' => 'fieldset',
        '#group' => 'packages',
        '#title' => check_plain($component_name),
        '#description' => t('Available configurations for: @component', array('@component' => $component_name)),
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
        '#tree' => TRUE,
        '#attached' => array(
          'js' => array(
            'vertical-tabs' => drupal_get_path('module', 'configuration_ui') . '/theme/vertical-tabs.js',
          ),
        ),
      );
      $form[$component]['tracked'] = array(
        '#type' => 'tableselect',
        '#header' => array(t('Configuration'), t('Tracking') , t('Operations')),
        '#options' => $options,
      );
      $form['dependencies_option'] = array(
        '#type' => 'fieldset',
        '#title' => t('Dependencies and Optionals'),
        '#description' => t('Choose if you want to process dependent and optional components.'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
      );
      $form['dependencies_option']['include_dependencies'] = array(
        '#type' => 'checkbox',
        '#title' => t('Process component dependencies.'),
        '#default_value' => TRUE,
      );
      $form['dependencies_option']['include_optionals'] = array(
        '#type' => 'checkbox',
        '#title' => t('Process component optionals.'),
        '#default_value' => TRUE,
      );
    }
  }
  $form['buttons'] = array('#theme' => 'configuration_ui_form_buttons', '#tree' => FALSE);
  $form['buttons']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Export to Tar File'),
    '#submit' => array('configuration_ui_export_to_tar_file_form_submit'),
  );
  if (!$component_exists) {
    $form['no_configs'] = array(
      '#markup' => t('No Configurations were found. Please use the
      !export_link page to begin tracking new Configurations.',
          array('!export_link' => l(t('Not Tracking'), 'admin/config/system/configuration/notracking'))),
    );
    unset($form['buttons']);
  }
  $form['#validate'][] = 'configuration_ui_tracking_form_validate';
  return $form;
}

/**
 * Submit handler for export to datastore.
 */
function configuration_ui_export_to_tar_file_form_submit($form, &$form_state) {
  $args = configuration_ui_get_form_components($form, $form_state);
  $export_dependencies = $form_state['values']['include_dependencies'];
  $export_optionals = $form_state['values']['include_optionals'];
  Configuration::exportAsTar($args, $export_dependencies, $export_optionals);
}

/**
 * Submit handler for importing configs.
 */
function configuration_ui_import_form_submit($form, &$form_state) {

  $validators = array('file_validate_extensions' => array('tar'));
  if ($file = file_save_upload('import_configurations', $validators)) {
    $settings = Configuration::importToActiveStoreFromTar($file->uri);

    file_delete($file);
    drupal_set_message(t('The following configurations have been migrated into the ActiveStore:'));
    $imported = $optionals = array('items' => $settings->getInfo('imported'));
    drupal_set_message(filter_xss(theme('item_list', $imported), array('ul', 'li')));
  }
}
