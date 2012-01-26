<?php

/**
 * Implements hook_configuration_api().
 */
function block_configuration_api() {
  return array();
}

/**
 * Implements hook_configuration_export().
 */
function block_configuration_export($data, &$export) {
  $pipe = array();
  foreach ($data as $bid) {
    $split = explode('-', $bid);
    $module = array_shift($split);
    $delta = implode('-', $split);

    $export['dependencies'][$module] = $module;

    switch ($module) {
      case 'views':
        if (strlen($delta) == 32) {
          $hashes = variable_get('views_block_hashes', array());
          if (!empty($hashes[$delta])) {
            $delta = $hashes[$delta];
          }
        }

        $delta_split = explode('-', $delta);
        $view_name = $delta_split[0];
        if (!empty($view_name)) {
          $pipe['views'][] = $view_name;
        }
        break;
    }
  }
  return $pipe;
}

/**
 * We have to define these functions because block defined a
 * hook_configuration_export() hook above.
 */
function configuration_hash_block($identifier) {
  // Empty function because we need this function.
}
function configuration_check_block($identifier) {
  // Empty function because we need this function.
}
