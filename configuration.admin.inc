<?php

/**
 * Menu Callback Form.
 */
function configuration_tracking_form($form, &$form_state) {
  module_load_include('inc', 'configuration', 'configuration.export');
  configuration_include();

  $config = configuration_get_configuration();

  // Use multi-step forms to be able to stop tracking configurations
  // using a confirmation form.
  if (!empty($form_state['page_num']) && $form_state['page_num'] == 2) {
    return configuration_confirm_delete_multiple($form, $form_state);
  }
  $form_state['page_num'] = 1;

  // Unset the status variable that is in the config cache
  // @todo: Thinkin about moving this state out of the config cache array.
  unset($config['overridden']);
  if (empty($config)) {
    $form['no_configs'] = array(
      '#markup' => t('No Configurations were found. Please use the
      !export_link page to begin tracking new Configurations.',
      array('!export_link' => l(t('Not Tracking'), 'admin/config/system/configuration/notracking'))),
    );
    return $form;
  }
  $form['help_text'] = array(
    '#markup' => 'Choose the configurations to write or activate.  Writing to activestore requires writing the entire component to datastore. You cannot write individual configs to datastore.  You have to select all components and write the entire component to datastore. Individual components may be imported into activestore.',
    '#prefix' => '<div>',
    '#suffix' => '</div>',
  );

  $form['packages'] = array('#type' => 'vertical_tabs');
  $form['#attached']['css'] = array(
    drupal_get_path('module', 'configuration') . '/theme/configuration.css'
  );
  $components = configuration_get_components();
  ksort($components);

  $js_settings = array();
  $component_exists = FALSE;
  foreach ($components as $component => $component_info) {
    $overridden = '';

    if (array_key_exists($component, $config)) {
      $component_exists = TRUE;
      $form[$component] = array(
        '#type' => 'fieldset',
        '#group' => 'packages',
        '#title' => check_plain($component_info['name']),
        '#description' => t('Configurations you are currently tracking in %config_type.', array('%config_type' => $component_info['name'])),
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
        '#tree' => TRUE,
        '#attached' => array(
          'js' => array(
            'vertical-tabs' => drupal_get_path('module', 'configuration') . '/theme/vertical-tabs.js',
          ),
        ),
      );

      // Show checkboxes for out of sync configurations
      $items = $overridden_items = $delete_items = array();
      $checkbox = array();
      $header = array('name' => t('Configuration Name'), 'status' => t('Status'), 'action' => t('Action'));
      foreach ($config[$component] as $config_item => $fields) {
        $status = _configuration_get_status_link($fields['status'], $component, $config_item);
        $action = l(t('Stop tracking'), 'admin/config/system/configuration/config/' . $component . '/' . $config_item . '/delete');
        $class = '';
        // Dependents should have a css class and no Stop Tracking link
        if ($fields['parent']) {
          $action = t('Dependent Configuration');
          $class = 'config-dependent';
        }
        if ($fields['status'] & (CONFIGURATION_ACTIVESTORE_OVERRIDDEN | CONFIGURATION_DATASTORE_OVERRIDDEN | CONFIGURATION_DATASTORE_ONLY | CONFIGURATION_TRACKED_DATASTORE_ONLY)) {
          $overridden = TRUE;
          $overridden_items[$config_item] = array('name' => $config_item, 'status' => $status, 'action' => $action);
          // $overridden_items[$config_item] = array('class' => array($class), 'data' => array('name' => $config_item, 'status' => t('Overridden'), 'action' => $action));
        }
        elseif ($fields['status'] == CONFIGURATION_IN_SYNC) {
          // $items[$config_item] = array('class' => array($class), 'data' => array('name' => $config_item, 'status' => t('Default'), 'action' => $action));
          $items[$config_item] = array('name' => $config_item, 'status' => $status, 'action' => $action);
        }
        elseif ($fields['status'] == CONFIGURATION_DELETE) {
          // $delete_items[$config_item] = array('class' => array('config-delete'), 'data' => array('name' => $config_item, 'status' => t('No Longer in Activestore'), 'action' => $action));
          $delete_items[$config_item] = array('name' => $config_item, 'status' => $status, 'action' => $action);
        }
      }

      $all_items = $items + $delete_items;
      $form[$component]['items'] = array(
        '#type' => 'tableselect',
        '#header' => $header,
        '#options' => $overridden_items + $all_items,
        '#attributes' => array('id' => 'configuration-table-select-' . $component),
        '#overridden' => count($overridden_items),
      );
    }
    if ($overridden) {
      $form[$component]['#attributes']['class'] = array('overridden');
    }

    // Set variables in in js for setting the tab summaries
    $js_settings['configuration'][$component] = $component;
  }
  // If not tabs are shown, that means no configurations are being tracked.
  if (!$component_exists) {
    unset($form['help_text']);
    $form['no_configs'] = array(
      '#markup' => t('No Configurations were found. Please use the
      !export_link page to begin tracking new Configurations.',
      array('!export_link' => l(t('Not Tracking'), 'admin/config/system/configuration/notracking'))),
    );
    return $form;
  }

  drupal_add_js($js_settings, 'setting');

  $form['buttons'] = array('#theme' => 'configuration_form_buttons', '#tree' => FALSE);

  // Do not allow writing to datastore if on remote server.
  if (variable_get('remote_server', 0) < 1) {
    $form['buttons']['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Write Activestore to Datastore'),
      '#weight' => 9,
    );
  }

  $form['buttons']['stop_tracking'] = array(
    '#type' => 'submit',
    '#value' => t('Stop Tracking'),
    '#weight' => 20,
    '#submit' => array('configuration_stop_tracking_form_submit'),
  );

  $form['buttons']['revert'] = array(
    '#type' => 'submit',
    '#value' => t('Import Datastore to Activestore'),
    '#weight' => 10,
    '#submit' => array('configuration_activate_form_submit'),
  );
  // Hide the action buttons if there is nothing overridden
  if (!$component_exists) {
    unset($form['buttons']);
  }

  // Use the same handlers as the notracking form
  $form['#validate'][] = 'configuration_notracking_form_validate';
  $form['#validate'][] = 'configuration_tracking_form_validate';
  $form['#validate'][] = 'configuration_stop_tracking_form_validate';
  $form['#submit'][] = 'configuration_notracking_form_submit';

  return $form;
}

/**
 * Menu Callback Form.
 */
function configuration_notracking_form($form, &$form_state) {
  if (variable_get('remote_server', 0) > 0) {
    drupal_set_message(t('This is a remote server. You are not able to track new configurations on a remote server. !link', array('!link' => l(t('Disable remote server setting.'), 'admin/config/system/configuration/settings'))), 'warning');
  }
  module_load_include('inc', 'configuration', 'configuration.export');
  configuration_include();

  $form['packages'] = array('#type' => 'vertical_tabs');
  $form['#attached']['css'] = array(
      drupal_get_path('module', 'configuration') . '/theme/configuration.css'
  );

  $config = configuration_get_configuration();
  $components = configuration_get_components();
  ksort($components);

  $js_settings = array();
  foreach ($components as $component => $component_info) {
    $options = configuration_invoke($component, 'configuration_export_options');

    if (is_array($options)) {
      $form[$component] = array(
        '#type' => 'fieldset',
        '#group' => 'packages',
        '#title' => check_plain($component_info['name']),
        '#description' => t('Choose the %config_type configurations that you would like to track.', array('%config_type' => $component_info['name'])),
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
        '#tree' => TRUE,
        '#attached' => array(
          'js' => array(
            'vertical-tabs' => drupal_get_path('module', 'configuration') . '/theme/vertical-tabs.js',
          ),
        ),
      );

      $has_items = FALSE;
      $items = $noactive_items = array();
      foreach ($options as $option => $label) {
        // Only show items that are not currently being tracked
        if (!isset($config[$component]) || !in_array($option, array_keys($config[$component]))) {
          // if (!configuration_in_activestore($component, $option)) {
          //   $noactive_items[$option] = array('name' => $option, 'status' => t('Not in Activestore'));
          // }
          // else {
            $items[$option] = array('name' => $label, 'status' => t('Not Being Tracked'));
          // }
          $has_items = TRUE;
        }
      }

      if (!$has_items) {
        $form[$component]['#description'] = t('You are tracking all exports for this component.');
      }

      $header = array('name' => t('Configuration Name'), 'status' => t('Status'));
      if ($has_items) {
        $form[$component]['items'] = array(
          '#type' => 'tableselect',
          '#header' => $header,
          '#options' => configuration_dom_encode_options($items + $noactive_items),
          '#attributes' => array('id' => 'configuration-table-select-' . $component),
        );
      }
    }
    // Set variables in in js for setting the tab summaries
    $js_settings['configuration'][$component] = $component;
  }
  drupal_add_js($js_settings, 'setting');

  $form['buttons'] = array('#theme' => 'configuration_form_buttons', '#tree' => FALSE);
  if (variable_get('remote_server', 0) < 1) {
    $form['buttons']['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Write to Datastore'),
      '#weight' => 10,
    );
  }

  return $form;
}

/**
 * Menu Callback Form.
 */
function configuration_migrate_form($form, &$form_state) {
  module_load_include('inc', 'configuration', 'configuration.export');
  configuration_include();

  $form['header'] = array(
    '#markup' => t('Choose all the configurations you would like to package up to import into another site.'),
    '#prefix' => '<div>',
    '#suffix' => '</div>',
  );

  $form['packages'] = array('#type' => 'vertical_tabs');
  $form['#attached']['css'] = array(
      drupal_get_path('module', 'configuration') . '/theme/configuration.css'
  );

  $components = configuration_get_components();
  ksort($components);

  $js_settings = array();
  foreach ($components as $component => $component_info) {
    $options = configuration_invoke($component, 'configuration_export_options');

    if (is_array($options)) {
      $form[$component] = array(
        '#type' => 'fieldset',
        '#group' => 'packages',
        '#title' => check_plain($component_info['name']),
        '#description' => t('Choose the %config_type configurations that you would like to track.', array('%config_type' => $component_info['name'])),
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
        '#tree' => TRUE,
        '#attached' => array(
          'js' => array(
            'vertical-tabs' => drupal_get_path('module', 'configuration') . '/theme/vertical-tabs.js',
          ),
        ),
      );

      // Get the defaults not in activestore. Can't export these until they
      // are put into the activestore.
      $map = configuration_get_default_map($component);
      $has_items = FALSE;
      $items = $noactive_items = array();
      foreach ($options as $option => $label) {
        $items[$option] = array('name' => $label);
        $has_items = TRUE;
      }

      if (!$has_items) {
        $form[$component]['#description'] = t('No configurations.');
      }

      $header = array('name' => t('Configuration Name'));
      if ($has_items) {
        $form[$component]['items'] = array(
          '#type' => 'tableselect',
          '#header' => $header,
          '#options' => configuration_dom_encode_options($items),
          '#attributes' => array('id' => 'configuration-export-table-select-' . $component),
        );
      }
    }
    // Set variables in in js for setting the tab summaries
    $js_settings['configuration'][$component] = $component;
  }
  drupal_add_js($js_settings, 'setting');

  $form['buttons'] = array('#theme' => 'configuration_form_buttons', '#tree' => FALSE);
  $form['buttons']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Download Configurations'),
    '#weight' => 10,
  );
  $form['#validate'][] = 'configuration_notracking_form_validate';

  return $form;
}

/**
 * Menu Callback Form.
 */
function configuration_activate_form($form, &$form_state) {
  module_load_include('inc', 'configuration', 'configuration.export');
  configuration_include();

  $config = configuration_get_configuration();

  $form['packages'] = array('#type' => 'vertical_tabs');
  $form['#attached']['css'] = array(
    drupal_get_path('module', 'configuration') . '/theme/configuration.css'
  );

  $components = configuration_get_components();
  $empty_components = array();
  $show_form = FALSE;
  foreach ($components as $component => $component_info) {
    if (array_key_exists($component, $config)) {
      $empty_components[$component] = $component;
      $form[$component] = array(
        '#type' => 'fieldset',
        '#group' => 'packages',
        '#title' => check_plain($component_info['name']),
        '#description' => t('Configurations that need to be activated in %config_type.', array('%config_type' => $component_info['name'])),
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
        '#tree' => TRUE,
      );

      // Show checkboxes for out of sync configurations
      $items = $overridden_items = array();
      $checkbox = array();
      $header = array('name' => t('Configuration Name'), 'status' => t('Status'), 'action' => t('Action'));
      foreach ($config[$component] as $config_item => $fields) {
        if ($fields['status'] & (CONFIGURATION_DATASTORE_ONLY | CONFIGURATION_DATASTORE_OVERRIDDEN)) {
          $status = $fields['status'] == CONFIGURATION_DATASTORE_ONLY ? t('New Configuration') : l(t('Update Configuration'), 'admin/config/system/configuration/' . $component . '/' . $config_item . '/diff');
          $overridden_items[$config_item] = array('name' => $config_item, 'status' => $status, 'action' => l(t('Stop tracking'), 'admin/config/system/configuration/config/' . $component . '/' . $config_item . '/delete'));
          $empty_components[$component] = FALSE;
          $show_form = TRUE;
        }
      }
      if (!empty($overridden_items)) {
        $form[$component]['items'] = array(
          '#type' => 'tableselect',
          '#header' => $header,
          '#options' => $overridden_items,
          '#attributes' => array('id' => 'configuration-table-select-' . $component),
        );
      }
    }
  }
  // Unset any components that do not need to be activated.
  $empty_components = array_filter($empty_components);
  foreach ($empty_components as $component) {
    unset($form[$component]);
  }

  if (!$show_form) {
    $form['no_configs'] = array(
      '#markup' => t('No new configurations were found to activate.'),
    );
    return $form ;
  }


  $form['buttons'] = array('#theme' => 'configuration_form_buttons', '#tree' => FALSE);
  $form['buttons']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Activate in Activestore'),
    '#weight' => 9,
  );
  $form['buttons']['revert'] = array(
    '#type' => 'submit',
    '#value' => t('Update Datastore'),
    '#weight' => 10,
    '#submit' => array('configuration_notracking_form_submit'),
  );

  $form['#validate'][] = 'configuration_notracking_form_validate';
  $form['#submit'][] = 'configuration_activate_form_submit';

  return $form;
}

/**
 * Menu Callback Form.
 */
function configuration_import_form($form, &$form_state) {
  $form['header'] = array(
    '#markup' => 'Importing configurations will write your configurations directly to the activestore. DO NOT USE THIS ON A PRODUCTION SITE!',
    '#prefix' => '<div>',
    '#suffix' => '</div>',
  );

  $form['import_configurations'] = array(
    '#type' => 'file',
    '#title' => t('Import configurations'),
    '#description' => t('Select the file you exported from another site.'),
    '#size' => 40,
  );

  $form['import'] = array(
    '#type' => 'submit',
    '#value' => t('Import'),
  );

  return $form;
}

/**
 * Submit handler for importing configs.
 */
function configuration_import_form_submit($form, &$form_state) {
  module_load_include('inc', 'configuration', 'configuration.export');

  $path = 'public://configuration_migrate';
  file_prepare_directory($path, FILE_CREATE_DIRECTORY);
  drupal_chmod($path, 0777);

  $validators = array('file_validate_extensions' => array('tar'));
  if ($file = file_save_upload('import_configurations', $validators, $path)) {
    // dpm($file);
    $archive = archiver_get_archiver($file->uri);
    $files = $archive->listContents();
    foreach ($files as $filename) {
      if (is_file($path . '/' . $filename)) {
        file_unmanaged_delete($path . '/' . $filename);
      }
    }

    $archive->extract($path);

    if (!is_file($path . "/configuration/config.export")) {
      drupal_set_message(t('Could not find config.export file in Import'), 'error');
      return;
    }

    $config_migrate = drupal_parse_info_file($path . "/configuration/config.export");
    $components = configuration_get_components();
    foreach ($config_migrate['config'] as $component => $info) {
      if (!array_key_exists($component, $components)) continue;

      if (!array_key_exists('default_file', $components[$component])) {
        $config_info['configuration.inc'][$component] = $components[$component];
      }
      elseif ($components[$component]['default_file'] == CONFIGURATION_DEFAULTS_INCLUDED) {
        $config_info['configuration.' . $component . '.inc'][$component] = $components[$component];
      }
      elseif ($components[$component]['default_file'] == CONFIGURATION_DEFAULTS_CUSTOM) {
        $config_info[$components[$component]['default_filename'] . '.inc'][$component] = $components[$component];
      }
    }

    $revert = array();
    foreach (file_scan_directory($path, '/.*inc$/') as $config_file) {
      if (in_array($config_file->filename, array_keys($config_info))) {
        // Rewrite function signatures in the migrate files to allow the
        // ability to load migrate files and restore
        $content = file_get_contents($path . '/configuration/' . $config_file->filename);
        $content = preg_replace('/function configuration_/', 'function migrate_configuration_', $content);
        file_put_contents($path . '/configuration/' . $config_file->filename, $content);

        // Load the migrate file
        include_once $path . '/configuration/' . $config_file->filename;
        $revert_files[] = $config_file->filename;
      }
    }
    foreach ($revert_files as $migrate_file) {
      foreach ($config_info[$migrate_file] as $component => $info) {
        $revert[$component] = array('#import_all' => TRUE);
      }
    }

    configuration_revert($revert, 'migrate_configuration');

    // Delete the uploaded file
    file_delete($file);
    drupal_set_message(t('Migrated %component configurations to the activestore', array('%component' => join(', ', array_keys($revert)))));
  }
}

/**
 * Submit handler for downloading configs.
 */
function configuration_migrate_form_submit($form, &$form_state)  {
  configuration_download_config($form_state['configuration_export']);
}
/**
 * Submit handler for reverting configs.
 */
function configuration_activate_form_submit($form, &$form_state) {
  configuration_revert($form_state['configuration_export']);
}

/**
 * Submit handler for stop tracking configs.
 */
function configuration_stop_tracking_form_submit($form, &$form_state) {
  // Values are saved for each page.
  // to carry forward to subsequent pages in the form.
  // and we tell FAPI to rebuild the form.
  $form_state['page_values'][1] = $form_state['values'];

  if (!empty($form_state['page_values'][2])) {
    $form_state['values'] = $form_state['page_values'][2];
  }

  // Only when click the "Stop Tracking" button, we move to the step two.
  $form_state['page_num'] = 2;
  $form_state['rebuild'] = TRUE;
}

/**
 * Submit handler for reverting configs.
 */
function configuration_revert_config($form, &$form_state) {
  drupal_set_message('Reverting is not implemented yet!');
}

/**
 * Submit handler for downloading diff.
 */
function configuration_download_config_submit($form, &$form_state) {
  configuration_download_config();
}

/**
 * Page callback for deleting forms.
 */
function configuration_confirm_delete_page($component, $name) {
  if (configuration_is_tracked($component, $name)) {
    $config = configuration_get_configuration();
    if (!empty($config[$component][$name]['parent'])) {
      return MENU_NOT_FOUND;
    }
    else {
      return drupal_get_form('configuration_confirm_delete', $component, $name);
    }

  }
  return MENU_NOT_FOUND;
}

/**
 * Form for deleting configs.
 */
function configuration_confirm_delete($form, &$form_state, $component, $name) {
  module_load_include('inc', 'configuration', 'configuration.export');
  configuration_include();

  // Pass the component as an array to re-use this function
  // with the multiple stop tracking feature.
  $form['config_name']['#tree'] = TRUE;
  $form['config_name'][$component . ':' . $name] = array(
    '#type' => 'value',
    '#value' => $component . ':' . $name,
  );
  return confirm_form(
    $form,
    t('Are you sure you want to stop tracking configuration %name?', array('%name' => $name)),
    'admin/config/system/configuration',
    t('This action cannot be undone.'),
    t('Stop Tracking'),
    t('Cancel'),
    'confirm'
  );
}

/**
 * Form for deleting configs.
 */
function configuration_confirm_delete_multiple($form, &$form_state) {
  module_load_include('inc', 'configuration', 'configuration.export');
  configuration_include();

  $list_to_delete = array();

  $form['config_name']['#tree'] = TRUE;

  foreach (element_children($form_state['values']) as $component) {
    if (is_array($form_state['values'][$component])) {
      foreach (array_keys(array_filter($form_state['values'][$component]['items'])) as $name) {
        $form['config_name'][$component . ':' . $name] = array(
          '#type' => 'value',
          '#value' => $component . ':' . $name,
        );

        if (!isset($list_to_delete[$component])) {
          $list_to_delete[$component] = array('data' => $component, 'children' => array());
        }
        $list_to_delete[$component]['children'][$name] = array('data' => $name);
      }
    }
  }

  $message = t('Are you sure you want to stop tracking configuration for the following configs?');
  $message .= '<p>' . theme('item_list', array('items' => $list_to_delete)) . '</p>';
  $message .= t('This action cannot be undone.');

  $form = confirm_form(
    $form,
    t('Are you sure you want to stop tracking configuration for the following configs?'),
    'admin/config/system/configuration',
    $message,
    t('Stop Tracking'),
    t('Cancel'),
    'confirm'
  );
  $form['#submit'][] = 'configuration_confirm_delete_submit';
  return $form;
}

/**
 * Submit handler for deleting configs.
 */
function configuration_confirm_delete_submit($form, &$form_state) {
  $a = &drupal_static('configuration_from_activestore');
  $a = TRUE;

  $to_delete = $form_state['values']['config_name'];
  $last_count = 0;

  while (count($to_delete) > 0 && $last_count != count($to_delete)) {
    $last_count = count($to_delete);
    foreach ($to_delete as $index => $name) {
      $parts = explode(':', $name);
      $configs = db_query("SELECT cid, parent FROM {config_export} WHERE owner = :owner AND name = :name", array(":owner" => $parts[0], ":name" => $parts[1]))->fetchAll();

      // Only remove the config is it doesn't have parents that depends of this config
      foreach ($configs as $config) {
        if (empty($config->parent)) {
          $delete[] = $config->cid;
        }
      }
      if (!empty($delete)) {
        if (configuration_delete_multiple($delete)) {
          drupal_set_message(t('The configuration %config_name was deleted.', array('%config_name' => $name)));
          watchdog('content', 'Deleted configuration @config_name and its replies.', array('@config_name' => $name));

          // Remove the item of the list to process
          unset($to_delete[$index]);
        }
      }
    }
  }

  if (count($to_delete) > 0) {
    drupal_set_message(t('Something wrong occurred.'), 'error');
  }

  $form_state['redirect'] = "admin/config/system/configuration";
}

function configuration_stop_tracking_form_validate($form, &$form_state) {
  if ($form_state['values']['op'] == t('Stop Tracking')) {
    $configuration_export = $form_state['configuration_export'];
    $current_config = configuration_get_configuration();
    foreach ($configuration_export as $component => $config) {
      foreach (array_keys(array_filter($config)) as $identifier) {
        // User is trying to Stop Tracking a component that is a dependency of another component
        if (!empty($current_config[$component][$identifier]['parent'])) {
          $parent_id = $current_config[$component][$identifier]['parent'];
          // See if the parent is also being UnTracking.
          $parent_untracked_too = FALSE;
          $dependencies = unserialize($current_config[$component][$identifier]['dependencies']);
          if (!empty($dependencies)) {
            foreach (array_unique($dependencies) as $dependency) {
              if (!empty($configuration_export[$dependency][$parent_id])) {
                $parent_untracked_too = TRUE;
              }
            }
          }
          if (!$parent_untracked_too) {
            form_set_error('packages', t('You cannot stop tracking configurations of a component that is a dependency of another component.'));
          }
        }
      }
    }
  }
}

/**
 * Validation and formatting the values submitted in the form.
 */
function configuration_notracking_form_validate($form, &$form_state) {
  static $has_checked;
  // Create one array from all the saved values
  $configuration_export = array();
  foreach ($form_state['values'] as $component => $value) {
    // Only grab array values since we set #tree above
    if (is_array($value)) {
      $value['items'] = configuration_dom_decode_options($value['items']);
      foreach ($value['items'] as $config_name => $checked) {
        $configuration_export[$component][$config_name] = $checked ? 1 : 0;
        if (!$has_checked && array_search($config_name, $value['items'])) {
          $has_checked = TRUE;
        }
      }
    }
  }

  if (!$has_checked) {
    form_set_error('packages', t('Please choose at least one configuration.'));
  }
  $form_state['configuration_export'] = $configuration_export;
}

function configuration_tracking_form_validate($form, &$form_state) {
  if ($form_state['values']['op'] == t('Write Activestore to Datastore')) {
    $configuration_export = $form_state['configuration_export'];
    foreach ($configuration_export as $component => $config) {
      $num_on = count(array_filter($config));
      if ($num_on != 0 && $num_on != $form[$component]['items']['#overridden']) {
        form_set_error('packages', t('Writing to activestore requires writing the entire component to datastore. Select all checkboxes on the %component tab to write to datastore.', array('%component' => $component)));
      }
    }
  }
}

function configuration_notracking_form_submit($form, &$form_state) {
  // Set a static variable that we can access across this request.  Setting this
  // to TRUE ensures that configuration module does not see the change in
  // configuration as being changed from the filesystem.
  $a = &drupal_static('configuration_from_activestore');
  $a = TRUE;

  configuration_save($form_state['configuration_export']);
}

/**
 * Menu Callback Form.
 */
function configuration_settings_form($form, &$form_state) {
  $form['configuration_ops'] = array(
    '#type' => 'fieldset',
    '#title' => t('Configuration Operations'),
    '#collapsible' => TRUE,
    '#collapsed' => FALSE,
    '#description' => t('Scan all configurations being tracked for differences'),
  );
  $form['configuration_ops']['check_for_new_configurations'] = array(
    '#type' => 'submit',
    '#value' => t('Check for new configurations'),
    '#submit' => array('configuration_check_configurations_submit'),
  );

  $form['general_settings'] = array(
    '#type' => 'fieldset',
    '#title' => t('General Settings'),
    '#collapsible' => TRUE,
    '#collapsed' => FALSE,
  );

  $form['general_settings']['configuration_display_messages'] = array(
    '#type' => 'checkbox',
    '#title' => t('Display a message warning that there is changes to write into the datastore'),
    '#description' => t('If checked, every time that configurations from datastore be overriden, a message will be displayed informing users to write the changes into the datastore.'),
    '#default_value' => variable_get('configuration_display_messages', TRUE),
  );

  $form['general_settings']['configuration_config_path'] = array(
    '#type' => 'textfield',
    '#title' => t('Configuration directory configs are stored in.'),
    '#description' => t('Configuration directory to store config files. This defaults to inside the files directory for easy write access. Since most users ignore the files directory in their VCS, you may want to set this outside of your files directory so that configs are included in version control.'),
    '#size' => 40,
    '#maxlength' => 255,
    '#default_value' => variable_get('configuration_config_path', conf_path() . '/files/config'),
    '#after_build' => array('system_check_directory'),
  );

  $form['general_settings']['remote_server'] = array(
    '#type' => 'checkbox',
    '#title' => t('This is a remote server'),
    '#description' => t('If this is a remote server and you do not have access to the filesystem of this server, the ability to write files to datastore should not be allowed.'),
    '#default_value' => variable_get('remote_server', -1),
  );

   if (variable_get('remote_server', 0)) {
    if (module_exists('diff')) {
      $form['general_settings']['submit'] = array(
        '#type' => 'submit',
        '#value' => t('Download Activestore Configuration'),
        '#weight' => 9,
        '#submit' => array('configuration_download_config_submit'),
        '#states' => array(
          'invisible' => array(
            'input[name="remote_server"]' => array('checked' => FALSE),
          ),
        ),
      );
    }
  }

  return system_settings_form($form);
}

function configuration_check_configurations_submit($form, &$form_state) {
  configuration_check_configurations(TRUE);
}

/**
 * Make a Drupal options array safe for usage with jQuery DOM selectors.
 * Encodes known bad characters into __[ordinal]__ so that they may be
 * safely referenced by JS behaviors.
 */
function configuration_dom_encode_options($options = array(), $keys_only = TRUE) {
  $replacements = array(
    ':' => '__' . ord(':') . '__',
    '/' => '__' . ord('/') . '__',
    ',' => '__' . ord(',') . '__',
    '.' => '__' . ord(',') . '__',
    '<' => '__' . ord('<') . '__',
    '>' => '__' . ord('>') . '__',
  );
  $encoded = array();
  foreach ($options as $key => $value) {
    $encoded[strtr($key, $replacements)] = $keys_only ? $value : strtr($value, $replacements);
  }
  return $encoded;
}

/**
 * Decode an array of option values that have been encoded by
 * configuration_dom_encode_options().
 */
function configuration_dom_decode_options($options, $keys_only = FALSE) {
  $replacements = array_flip(array(
    ':' => '__' . ord(':') . '__',
    '/' => '__' . ord('/') . '__',
    ',' => '__' . ord(',') . '__',
    '.' => '__' . ord(',') . '__',
    '<' => '__' . ord('<') . '__',
    '>' => '__' . ord('>') . '__',
  ));
  $encoded = array();
  foreach ($options as $key => $value) {
    $encoded[strtr($key, $replacements)] = $keys_only ? $value : strtr($value, $replacements);
  }
  return $encoded;
}

/**
 * Page callback to display the differences between what's in code and
 * what is in the db.
 *
 * @param $configuration
 *   A loaded configuration object to display differences for.
 * @param $component
 *   Optional: specific component to display differences for. If excluded, all components are used.
 *
 * @return Themed display of what is different.
 */
function configuration_diff($component, $identifier) {
  drupal_add_css(drupal_get_path('module', 'configuration') . '/configuration.css');
  module_load_include('inc', 'configuration', 'configuration.export');

  $overrides = configuration_detect_overrides($component, $identifier);
  $config_in_sync = FALSE;
  $output = '';
  if (!empty($overrides)) {
    module_load_include('inc', 'diff', 'diff.engine');
    $formatter = new DrupalDiffFormatter(); //- temporarily broken
    //$formatter = new DiffFormatter();
    $formatter->leading_context_lines = 2;
    $formatter->trailing_context_lines = 2;
    //$formatter->show_header = $show_header

    $rows = array();
    foreach ($overrides as $component => $items) {
      foreach ($items as $identifier => $item) {
        $rows[] = array(array(array('data' => $component, 'colspan' => 4, 'header' => TRUE)));
        $diff = new Diff(explode("\n", configuration_var_export($item['datastore'])), explode("\n", configuration_var_export($item['activestore'])));
        $rows = array_merge($rows, $formatter->format($diff));
        $d = $formatter->format($diff);
        if (empty($d)) {
          // The initial method of checking differences is by comparing arrays.
          // For some reason, even if the arrays are different, running the
          // arrays through the Diff constructor above may not find difference.
          // This is creating FALSE positives and marking configs as not in
          // sync. If the diff system doesn't see a difference, set the status
          // to CONFIGURATION_IN_SYNC.
          drupal_set_message(t('Configuration is in sync.'));
          configuration_set_status($component, $identifier, CONFIGURATION_IN_SYNC);
          cache_clear_all('config_export', 'cache');
          $config_in_sync = TRUE;
        }
      }
    }
    if ($config_in_sync) {
      $output = "<div class='configuration-empty'>" . t('No changes have been made to this configuration.') . "</div>";
    }
    else {
      $header = array(
        array('data' => t('Datastore'), 'colspan' => 2),
        array('data' => t('Activestore'), 'colspan' => 2),
      );
      $output .= theme('table', array('header' => $header, 'rows' => $rows, 'attributes' => array('class' => array('diff', 'configuration-diff'))));
    }
  }
  else {
    $output = "<div class='configuration-empty'>" . t('No changes have been made to this configuration.') . "</div>";
  }
  $output = array('page' => array('#markup' => "<div class='configuration-comparison'>{$output}</div>"));
  return $output;
}

/**
 * Download the entire configuration packaged up into zip file
 */
function configuration_download_config($configs = NULL) {
  module_load_include('inc', 'configuration', 'configuration.export');

  if ($configs) {
    configuration_include();

    // Only set the configurations that were checked to be passed to the
    // configuration_populate function.
    foreach ($configs as $component => $config) {
      foreach ($config as $name => $on) {
        if ($on) {
          if (!empty($all_config[$component][$name])) {
            $c[$component][$name] = $all_config[$component][$name];
          }
          else {
            $function = "configuration_hash_{$component}";
            $hash =  $function($name);
            $c[$component][$name]['hash'] = $hash;
            $c[$component][$name]['status'] = CONFIGURATION_IN_SYNC;
            $c[$component][$name]['parent'] = '';
          }
        }
      }
    }
    $config = $c;
  }
  else {
    $config = configuration_get_configuration();
  }
  $config_populate = configuration_populate_sanitize($config);

  // Generate populated feature
  $module_name = 'configuration';
  $export = configuration_populate($config_populate, array());

  // Configs passed in by migrate function
  if ($configs) {
    // Check if there is dependencies to export.
    if (!empty($export['configuration_dependency'])) {
      foreach ($export['configuration_dependency']['configuration'] as $component => $config) {
        foreach ($config as $name => $parent) {
          $c[$component][$name]['status'] = CONFIGURATION_DATASTORE_ONLY;
          $c[$component][$name]['parent'] = $parent;
        }
      }
    }
    configuration_write_export_file($c, "temporary://config.export");
  }

  // Generate download
  if ($files = configuration_export_render($export, $module_name, TRUE)) {
    $filename = (!empty($export['version']) ? "{$module_name}-{$export['version']}" : $module_name) . '.tar';

    // Clear out output buffer to remove any garbage from tar output.
    if (ob_get_level()) {
      ob_end_clean();
    }

    drupal_add_http_header('Content-type', 'application/x-tar');
    drupal_add_http_header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    drupal_send_headers();

    $tar = array();
    $filenames = array();
    foreach ($files as $extension => $file_contents) {
      if (!in_array($extension, array('module', 'info'))) {
        $extension .= '.inc';
      }
      $filenames[] = "{$module_name}.$extension";
      print configuration_tar_create("{$module_name}/$extension", $file_contents);
    }
    $contents = file_get_contents("temporary://config.export");
    print configuration_tar_create("{$module_name}/config.export", $contents);
    unset($contents);

    print pack("a1024", "");
    exit;
  }
}

/**
 * Download a unified diff.
 *
 * @todo Not working, will set the files up to just download for now.
 */
function configuration_download_diff($config) {
  module_load_include('inc', 'configuration', 'configuration.export');
  module_load_include('inc', 'configuration', 'includes/diff.engine');
  $code = array();
  $config_populate = configuration_populate_sanitize($config);
  $export = configuration_populate($config_populate, array());

  // Track dependencies on config_export table
  _configuration_track_dependencies($export);
  $files = configuration_export_render($export, TRUE);

  foreach ($files as $component => $activestore) {
    $datastore = file_get_contents("config://" . $component . '.inc');

    $formatter = new UnifiedDiffFormatter();
    //$formatter = new DiffFormatter();
    $formatter->leading_context_lines = 2;
    $formatter->trailing_context_lines = 2;
    //$formatter->show_header = $show_header

    $diff = new Diff(explode("\n", $datastore), explode("\n", $activestore));
    print '--- ' . $component . ".inc\n";
    print '+++ ' . $component . ".inc\n";
    print $formatter->format($diff);
  }
  exit;
}

/**
 * Return the status name as string based on the status of passed in.
 */
function _configuration_get_status_link($status, $component = NULL, $config_item = NULL) {
  $diff = module_exists('diff');
  switch ($status) {
    case CONFIGURATION_ACTIVESTORE_OVERRIDDEN :
      if ($diff) {
        return l(t('Activestore Changed'), 'admin/config/system/configuration/' . $component . '/' . $config_item . '/diff');
      }
      return t('Activestore Changed');

    case CONFIGURATION_DATASTORE_OVERRIDDEN :
      if ($diff) {
        return l(t('Datastore Changed'), 'admin/config/system/configuration/' . $component . '/' . $config_item . '/diff');
      }
      return t('Datastore Changed');
    case CONFIGURATION_TRACKED_DATASTORE_ONLY :
    case CONFIGURATION_DATASTORE_ONLY : return t('New Configuration');
    case CONFIGURATION_IN_SYNC : return t('In Sync');
    case CONFIGURATION_DELETE : return t('No longer in Activestore');

    default :
      if ($diff) {
        return l(t('Both Changed'), 'admin/config/system/configuration/' . $component . '/' . $config_item . '/diff');
      }
      return t('Both Changed');
  }
}

function configuration_in_activestore($component, $identifier) {
  // $function = $component ."_configuration_export_options";
  //   if (function_exists($function)) {
  //     $data = $function();
  //     dpm($data);
  //     // // Export the field we just saved and evaluate the export to $fields
  //     // $activestore = call_user_func_array($component . '_configuration_export_render', array('configuration', array($data)));
  //     // dpm($activestore);
  //   }
  return TRUE;
}
