<?php

/**
 * Implements hook_configuration_export().
 */
function context_configuration_export($data, &$export, $module_name = '') {
  $pipe = ctools_component_configuration_export('context', $data, $export, $module_name);

  $contexts = context_load();
  foreach ($data as $identifier) {
    if (isset($contexts[$identifier])) {
      $context = $contexts[$identifier];
      // Conditions.
      // Currently only node and views conditions are supported.
      // @TODO: Should this be delegated to a method on the plugin?
      foreach (array('node', 'views') as $key) {
        if (!empty($context->conditions{$key}['values'])) {
          foreach ($context->conditions{$key}['values'] as $item) {
            // Special pipe for views
            if ($key === 'views') {
              $split = explode(':', $item);
              $view_name = array_shift($split);
              $pipe[$key][$view_name] = $view_name;
              $export['configuration_dependency']['configuration'][$key][$view_name] = $identifier;
              $export['configuration_dependency']['modules'][$key][$view_name] = '';
            }
            else {
              $pipe[$key][$item] = $item;
              $export['configuration_dependency']['configuration'][$key][$item] = $identifier;
              $export['configuration_dependency']['modules'][$key][$item] = '';
            }
          }
        }
      }
      // Reactions.
      if (!empty($context->reactions['block']['blocks'])) {
        foreach ($context->reactions['block']['blocks'] as $block) {
          $block = (array) $block;
          $bid = "{$block['module']}-{$block['delta']}";
          $pipe['block'][$bid] = $bid;
          $export['configuration_dependency']['configuration']['block'][$bid] = $identifier;
          $export['configuration_dependency']['modules']['block'][$bid] = '';
        }
      }
    }
  }
  return $pipe;
}

/**
 * Implements hook_configuration_revert().
 *
 * @param $module
 * name of module to revert content for
 */
function context_configuration_revert($module = NULL, $module_name = 'configuration') {
  $return = ctools_component_configuration_revert('context', $module, $module_name);
  context_invalidate_cache();
  return $return;
}
