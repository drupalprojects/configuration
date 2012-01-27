<?php

/**
 * @param $items
 * @param $module_name
 * @return
 */
function configuration_populate($items, $dependencies) {
  // Sanitize items.
  $items = array_filter($items);
  $items['dependencies'] = drupal_map_assoc(array_filter($dependencies));

  // Populate stub
  $stub = array('configuration' => array(), 'dependencies' => array(), 'conflicts' => array());
  $export = _configuration_populate($items, $stub);

  // Allow other modules to alter the export.
  drupal_alter('configuration_export', $export);

  // Clean up and standardize order
  foreach (array_keys($export['configuration']) as $k) {
    ksort($export['configuration'][$k]);
  }
  ksort($export['configuration']);
  ksort($export['dependencies']);

  return $export;
}

/**
 * Iterate and descend into a feature definition to extract module
 * dependencies and feature definition. Calls hook_configuration_export for modules
 * that implement it.
 *
 * @param $pipe
 *  Associative of array of module => info-for-module
 * @param $export
 *  Associative array of items, and module dependencies which define a feature.
 *  Passed by reference.
 *
 * @return fully populated $export array.
 */
function _configuration_populate($pipe, &$export) {
  configuration_include();
  foreach ($pipe as $component => $data) {
    if ($function = configuration_hook($component, 'configuration_export')) {
      // Pass module-specific data and export array.
      // We don't use configuration_invoke() here since we need to pass $export by reference.
      $more = $function($data, $export, $component);
      // Allow other modules to manipulate the pipe to add in additional modules.
      drupal_alter('configuration_pipe_' . $component, $more, $data, $export);
      // Allow for export functions to request additional exports.
      if (!empty($more)) {
        _configuration_populate($more, $export);
      }
    }
  }
  return $export;
}

/**
 * Iterates over a list of dependencies and kills modules that are
 * captured by other modules 'higher up'.
 */
function _configuration_export_minimize_dependencies($dependencies, $module_name = '') {
  // Ensure that the module doesn't depend upon itself
  if (!empty($module_name) && !empty($dependencies[$module_name])) {
    unset($dependencies[$module_name]);
  }

  // Do some cleanup:
  // - Remove modules required by Drupal core.
  // - Protect against direct circular dependencies.
  // - Remove "intermediate" dependencies.
  $required = drupal_required_modules();
  foreach ($dependencies as $k => $v) {
    if (empty($v) || in_array($v, $required)) {
      unset($dependencies[$k]);
    }
    else {
      $module = configuration_get_modules($v);
      if ($module && !empty($module->info['dependencies'])) {
        // If this dependency depends on the module itself, we have a circular dependency.
        // Don't let it happen. Only you can prevent forest fires.
        if (in_array($module_name, $module->info['dependencies'])) {
          unset($dependencies[$k]);
        }
        // Iterate through the dependency's dependencies and remove any dependencies
        // that are captured by it.
        else {
          foreach ($module->info['dependencies'] as $j => $dependency) {
            if (array_search($dependency, $dependencies) !== FALSE) {
              $position = array_search($dependency, $dependencies);
              unset($dependencies[$position]);
            }
          }
        }
      }
    }
  }
  return drupal_map_assoc(array_unique($dependencies));
}

/**
 * Iterates over a list of dependencies and maximize the list of modules.
 */
function _configuration_export_maximize_dependencies($dependencies, $module_name = '', $maximized = array(), $first = TRUE) {
  foreach ($dependencies as $k => $v) {
    $parsed_dependency = drupal_parse_dependency($v);
    $name = $parsed_dependency['name'];
    if (!in_array($name, $maximized)) {
      $maximized[] = $name;
      $module = configuration_get_modules($name);
      if ($module && !empty($module->info['dependencies'])) {
        $maximized = array_merge($maximized, _configuration_export_maximize_dependencies($module->info['dependencies'], $module_name, $maximized, FALSE));
      }
    }
  }
  return array_unique($maximized);
}

/**
 * Prepare a feature export array into a finalized info array.
 */
function configuration_export_prepare($export, $module_name, $reset = FALSE) {
  $existing = configuration_get_modules($module_name, $reset);

  // Prepare info string -- if module exists, merge into its existing info file
  $defaults = $existing ? $existing->info : array('core' => '7.x', 'package' => 'Features');
  $export = array_merge($defaults, $export);

  // Cleanup info array
  foreach ($export['configuration'] as $component => $data) {
    $export['configuration'][$component] = array_keys($data);
  }
  if (isset($export['dependencies'])) {
    $export['dependencies'] = array_values($export['dependencies']);
  }
  if (isset($export['conflicts'])) {
    unset($export['conflicts']);
  }
  ksort($export);
  return $export;
}

/**
 * Generate an array of hooks and their raw code.
 */
function configuration_export_render_hooks($export, $module_name, $reset = FALSE) {
  configuration_include();
  $code = array();
  $export['reset'] = $reset;
  
  // Sort components to keep exported code consistent
  ksort($export['configuration']);
  
  foreach ($export['configuration'] as $component => $data) {
    if (!empty($data)) {
      // Sort the items so that we don't generate different exports based on order
      asort($data);
      if (configuration_hook($component, 'configuration_export_render')) {
        $hooks = configuration_invoke($component, 'configuration_export_render', $module_name, $data, $export);
        $code[$component] = $hooks;
      }
    }
  }
  return $code;
}

/**
 * Render feature export into an array representing its files.
 *
 * @param $export
 *  An exported feature definition.
 * @param $reset
 *  Boolean flag for resetting the module cache. Only set to true when
 *  doing a final export for delivery.
 *
 * @return array of info file and module file contents.
 */
function configuration_export_render($export, $reset = FALSE) {
  $code = array();

  // Generate hook code
  $module_name = 'configuration';
  $component_hooks = configuration_export_render_hooks($export, $module_name, $reset);
  $components = configuration_get_components();

  // Group component code into their respective files
  foreach ($component_hooks as $component => $hooks) {
    $file = array('name' => 'configuration');
    if (isset($components[$component]['default_file'])) {
      switch ($components[$component]['default_file']) {
        case CONFIGURATION_DEFAULTS_INCLUDED:
          $file['name'] = "configuration.$component";
          break;
        case CONFIGURATION_DEFAULTS_CUSTOM:
          $file['name'] = $components[$component]['default_filename'];
          break;
      }
    }

    if (!isset($code[$file['name']])) {
      $code[$file['name']] = array();
    }

    foreach ($hooks as $hook_name => $hook_code) {
      $code[$file['name']][$hook_name] = configuration_export_render_defaults($module_name, $hook_name, $hook_code);
    }
  }

  // Finalize strings to be written to files
  foreach ($code as $filename => $contents) {
    $code[$filename] = "<?php\n/**\n * @file\n * {$filename}.inc\n */\n\n". implode("\n\n", $contents) ."\n";
  }

  return $code;
}

/**
 * Detect differences between DB and code components of a feature.
 */
function configuration_detect_overrides($component, $identifier) {
  if (!$cache = cache_get("$component:$identifier", 'cache_configuration')) {
    drupal_set_message("Could not find diff $component:$identifier", 'error');
    return false;
  }
  return $cache->data;
}

/**
 * Gets the available default hooks keyed by components.
 */
function configuration_get_default_hooks($component = NULL, $reset = FALSE) {
  static $hooks;
  if (!isset($hooks) || $reset) {
    $hooks = array();
    configuration_include();
    foreach (module_implements('configuration_api') as $module) {
      $info = module_invoke($module, 'configuration_api');
      foreach ($info as $k => $v) {
        if (isset($v['default_hook'])) {
          $hooks[$k] = $v['default_hook'];
        }
      }
    }
  }
  if (isset($component)) {
    return isset($hooks[$component]) ? $hooks[$component] : FALSE;
  }
  return $hooks;
}

/**
 * Return a code string representing an implementation of a defaults module hook.
 */
function configuration_export_render_defaults($module, $hook, $code) {
  $output = array();
  $output[] = "/**";
  $output[] = " * Implements hook_{$hook}().";
  $output[] = " */";
  $output[] = "function {$module}_{$hook}() {";
  $output[] = $code;
  $output[] = "}";
  return implode("\n", $output);
}

/**
 * Generate code friendly to the Drupal .info format from a structured array.
 *
 * @param $info
 *   An array or single value to put in a module's .info file.
 * @param $parents
 *   Array of parent keys (internal use only).
 *
 * @return
 *   A code string ready to be written to a module's .info file.
 */
function configuration_export_info($info, $parents = array()) {
  $output = '';
  if (is_array($info)) {
    foreach ($info as $k => $v) {
      $child = $parents;
      $child[] = $k;
      $output .= configuration_export_info($v, $child);
    }
  }
  else if (!empty($info) && count($parents)) {
    $line = array_shift($parents);
    foreach ($parents as $key) {
      $line .= is_numeric($key) ? "[]" : "[{$key}]";
    }
    $line .=  " = \"{$info}\"\n";
    return $line;
  }
  return $output;
}

/**
 * Tar creation function. Written by dmitrig01.
 *
 * @param $name
 *   Filename of the file to be tarred.
 * @param $contents
 *   String contents of the file.
 *
 * @return
 *   A string of the tar file contents.
 */
function configuration_tar_create($name, $contents) {
  $tar = '';
  $binary_data_first = pack("a100a8a8a8a12A12",
    $name,
    '100644 ', // File permissions
    '   765 ', // UID,
    '   765 ', // GID,
    sprintf("%11s ", decoct(strlen($contents))), // Filesize,
    sprintf("%11s", decoct(REQUEST_TIME)) // Creation time
  );
  $binary_data_last = pack("a1a100a6a2a32a32a8a8a155a12", '', '', '', '', '', '', '', '', '', '');

  $checksum = 0;
  for ($i = 0; $i < 148; $i++) {
    $checksum += ord(substr($binary_data_first, $i, 1));
  }
  for ($i = 148; $i < 156; $i++) {
    $checksum += ord(' ');
  }
  for ($i = 156, $j = 0; $i < 512; $i++, $j++) {
    $checksum += ord(substr($binary_data_last, $j, 1));
  }

  $tar .= $binary_data_first;
  $tar .= pack("a8", sprintf("%6s ", decoct($checksum)));
  $tar .= $binary_data_last;

  $buffer = str_split($contents, 512);
  foreach ($buffer as $item) {
    $tar .= pack("a512", $item);
  }
  return $tar;
}

/**
 * Export var function -- from Views.
 */
function configuration_var_export($var, $prefix = '', $init = TRUE) {
  if (is_object($var)) {
    $output = method_exists($var, 'export') ? $var->export() : configuration_var_export((array) $var);
  }
  else if (is_array($var)) {
    if (empty($var)) {
      $output = 'array()';
    }
    else {
      $output = "array(\n";
      foreach ($var as $key => $value) {
        // Using normal var_export on the key to ensure correct quoting.
        $output .= "  " . var_export($key, TRUE) . " => " . configuration_var_export($value, '  ', FALSE) . ",\n";
      }
      $output .= ')';
    }
  }
  else if (is_bool($var)) {
    $output = $var ? 'TRUE' : 'FALSE';
  }
  else if (is_string($var) && strpos($var, "\n") !== FALSE) {
    // Replace line breaks in strings with a token for replacement
    // at the very end. This protects whitespace in strings from
    // unintentional indentation.
    $var = str_replace("\n", "***BREAK***", $var);
    $output = var_export($var, TRUE);
  }
  else if (is_numeric($var)) {
    $output = is_int($var) ? var_export((int) $var, TRUE) : var_export((float) $var, TRUE);
  }
  else {
    $output = var_export($var, TRUE);
  }

  if ($prefix) {
    $output = str_replace("\n", "\n$prefix", $output);
  }

  if ($init) {
    $output = str_replace("***BREAK***", "\n", $output);
  }

  return $output;
}

/**
 * Helper function to return an array of t()'d translatables strings.
 * Useful for providing a separate array of translatables with your
 * export so that string extractors like potx can detect them.
 */
function configuration_translatables_export($translatables, $indent = '') {
  $output = '';
  $translatables = array_filter(array_unique($translatables));
  if (!empty($translatables)) {
    $output .= "{$indent}// Translatables\n";
    $output .= "{$indent}// Included for use with string extractors like potx.\n";
    sort($translatables);
    foreach ($translatables as $string) {
      $output .= "{$indent}t(" . configuration_var_export($string) . ");\n";
    }
  }
  return $output;
}

/**
 * Get a summary storage state for a feature.
 */
function configuration_get_storage($module_name) {
  // Get component states, and array_diff against array(CONFIGURATION_DEFAULT).
  // If the returned array has any states that don't match CONFIGURATION_DEFAULT,
  // return the highest state.
  $states = configuration_get_component_states(array($module_name), FALSE);
  $states = array_diff($states[$module_name], array(CONFIGURATION_DEFAULT));
  $storage = !empty($states) ? max($states) : CONFIGURATION_DEFAULT;
  return $storage;
}

/**
 * Wrapper around configuration_get_[storage] to return an md5hash of a normalized
 * defaults/normal object array. Can be used to compare normal/default states
 * of a module's component.
 */
function configuration_get_signature($state = 'default', $module_name, $component, $reset = FALSE) {
  switch ($state) {
    case 'cache':
      $codecache = variable_get('configuration_codecache', array());
      return isset($codecache[$module_name][$component]) ? $codecache[$module_name][$component] : FALSE;
    case 'default':
      $objects = configuration_get_default($component, $module_name, TRUE, $reset);
      break;
    case 'normal':
      $objects = configuration_get_normal($component, $module_name, $reset);
      break;
  }
  if (!empty($objects)) {
    $objects = (array) $objects;
    _configuration_sanitize($objects);
    return md5(_configuration_linetrim(configuration_var_export($objects)));
  }
  return FALSE;
}

/**
 * Set the signature of a module/component pair in the codecache.
 */
function configuration_set_signature($module, $component, $signature = NULL) {
  $var_codecache = variable_get('configuration_codecache', array());
  $signature = isset($signature) ? $signature : configuration_get_signature('default', $module, $component, TRUE);
  $var_codecache[$module][$component] = $signature;
  variable_set('configuration_codecache', $var_codecache);
}

/**
 * Processing semaphore operations.
 */
function configuration_semaphore($op, $component) {
  // Note: we don't use variable_get() here as the inited variable
  // static cache may be stale. Retrieving directly from the DB narrows
  // the possibility of collision.
  $semaphore = db_query("SELECT value FROM {variable} WHERE name = :name", array(':name' => 'configuration_semaphore'))->fetchField();
  $semaphore = !empty($semaphore) ? unserialize($semaphore) : array();

  switch ($op) {
    case 'get':
      return isset($semaphore[$component]) ? $semaphore[$component] : FALSE;
    case 'set':
      $semaphore[$component] = REQUEST_TIME;
      variable_set('configuration_semaphore', $semaphore);
      break;
    case 'del':
      if (isset($semaphore[$component])) {
        unset($semaphore[$component]);
        variable_set('configuration_semaphore', $semaphore);
      }
      break;
  }
}

/**
 * Get normal objects for a given module/component pair.
 */
function configuration_get_normal($component, $module_name, $reset = FALSE) {
  static $cache;
  if (!isset($cache) || $reset) {
    $cache = array();
  }
  if (!isset($cache[$module_name][$component])) {
    configuration_include();
    $code = NULL;
    $module = configuration_get_configuration($module_name);

    // Special handling for dependencies component.
    if ($component === 'dependencies') {
      $cache[$module_name][$component] = isset($module->info['dependencies']) ? array_filter($module->info['dependencies'], 'module_exists') : array();
    }
    // All other components.
    else {
      $default_hook = configuration_get_default_hooks($component);
      if ($module && $default_hook && isset($module->info['configuration'][$component]) && configuration_hook($component, 'configuration_export_render')) {
        $code = configuration_invoke($component, 'configuration_export_render', $module_name, $module->info['configuration'][$component], NULL);
        $cache[$module_name][$component] = isset($code[$default_hook]) ? eval($code[$default_hook]) : FALSE;
      }
    }

    // Clear out vars for memory's sake.
    unset($code);
    unset($module);
  }
  return isset($cache[$module_name][$component]) ? $cache[$module_name][$component] : FALSE;
}

/**
 * Get defaults for a given module/component pair.
 */
function configuration_get_default($component, $module_name = NULL, $alter = TRUE, $reset = FALSE) {
  static $cache = array();
  configuration_include();
  configuration_include_defaults($component);
  $default_hook = configuration_get_default_hooks($component);
  $components = configuration_get_components();

  // Collect defaults for all modules if no module name was specified.
  if (isset($module_name)) {
    $modules = array($module_name);
  }
  else {
    if ($component === 'dependencies') {
      $modules = array_keys(configuration_get_configurations());
    }
    else {
      $modules = array('configuration');
    }
  }
  
  // Collect and cache information for each specified module.
  foreach ($modules as $m) {
    if (!isset($cache[$component][$m]) || $reset) {
      // Special handling for dependencies component.
      if ($component === 'dependencies') {
        $module = configuration_get_configuration($m);
        $cache[$component][$m] = isset($module->info['dependencies']) ? $module->info['dependencies'] : array();
        unset($module);
      }
      // All other components
      else {
        if ($default_hook && module_hook($m, $default_hook)) {
          $cache[$component][$m] = call_user_func("{$m}_{$default_hook}");
          if ($alter) {
            drupal_alter($default_hook, $cache[$component][$m]);
          }
        }
        else if ($default_hook && module_exists('ctools') && _ctools_configuration_get_info()) {
          // If this is a ctools export, the file containing the hook may not be
          // loaded.  Load the file here.
          $info = _ctools_configuration_get_info();
          if (isset($info[$component])) {
            $function = $module_name .'_configuration_'. $info[$component]['default_hook'];
            if (function_exists($function)) {
              $cache[$component][$m] = call_user_func($function);
            }
            if ($alter) {
              drupal_alter($default_hook, $cache[$component][$m]);
            }
          }
        }
        else {
          $cache[$component][$m] = FALSE;
        }
      }
    }
  }
  // A specific module was specified. Retrieve only its components.
  if (isset($module_name)) {
    return isset($cache[$component][$module_name]) ? $cache[$component][$module_name] : FALSE;
  }
  // No module was specified. Retrieve all components.
  $all_defaults = array();
  if (isset($cache[$component])) {
    foreach (array_filter($cache[$component]) as $module_components) {
      $all_defaults = array_merge($all_defaults, $module_components);
    }
  }
  return $all_defaults;
}

/**
 * Get a map of components to their providing modules.
 */
function configuration_get_default_map($component, $attribute = NULL, $callback = NULL, $reset = FALSE) {
  static $map = array();
  configuration_include();
  configuration_include_defaults($component);
  if ((!isset($map[$component]) || $reset) && $default_hook = configuration_get_default_hooks($component)) {
    $map[$component] = array();
    foreach (module_implements($default_hook) as $module) {
      if ($defaults = configuration_get_default($component, $module)) {
        foreach ($defaults as $key => $object) {
          if (isset($callback)) {
            if ($object_key = $callback($object)) {
              $map[$component][$object_key] = $module;
            }
          }
          elseif (isset($attribute)) {
            if (is_object($object) && isset($object->{$attribute})) {
              $map[$component][$object->{$attribute}] = $module;
            }
            elseif (is_array($object) && isset($object[$attribute])) {
              $map[$component][$object[$attribute]] = $module;
            }
          }
          elseif (!isset($attribute) && !isset($callback)) {
            if (!is_numeric($key)) {
              $map[$component][$key] = $module;
            }
          }
          else {
            return FALSE;
          }
        }
      }
    }
  }
  return isset($map[$component]) ? $map[$component] : FALSE;
}

/**
 * Retrieve an array of configuration/components and their current states.
 */
function configuration_get_component_states($configuration = array(), $rebuild_only = TRUE, $reset = FALSE) {
  static $cache;
  if (!isset($cache) || $reset) {
    $cache = array();
  }

  $configuration = !empty($configuration) ? $configuration : array_keys(configuration_get_configuration());

  // Retrieve only rebuildable components if requested.
  configuration_include();
  $components = array_keys(configuration_get_components());
  if ($rebuild_only) {
    foreach ($components as $k => $component) {
      if (!configuration_hook($component, 'configuration_rebuild')) {
        unset($components[$k]);
      }
    }
  }

  foreach ($configuration as $feature) {
    $cache[$feature] = isset($cache[$feature]) ? $cache[$feature] : array();
    if (module_exists($feature)) {
      foreach ($components as $component) {
        if (!isset($cache[$feature][$component])) {
          $normal = configuration_get_signature('normal', $feature, $component, $reset);
          $default = configuration_get_signature('default', $feature, $component, $reset);
          $codecache = configuration_get_signature('cache', $feature, $component, $reset);
          $semaphore = configuration_semaphore('get', $component);

          // DB and code states match, there is nothing more to check.
          if ($normal == $default) {
            $cache[$feature][$component] = CONFIGURATION_DEFAULT;

            // Stale semaphores can be deleted.
            configuration_semaphore('del', $component);

            // Update code cache if it is stale, clear out semaphore if it stale.
            if ($default != $codecache) {
              configuration_set_signature($feature, $component, $default);
            }
          }
          // Component properly implements exportables.
          else if (!configuration_hook($component, 'configuration_rebuild')) {
            $cache[$feature][$component] = CONFIGURATION_OVERRIDDEN;
          }
          // Component does not implement exportables.
          else {
            if (empty($semaphore)) {
              // Exception for dependencies. Dependencies are always rebuildable.
              if ($component === 'dependencies') {
                $cache[$feature][$component] = CONFIGURATION_REBUILDABLE;
              }
              // All other rebuildable components require comparison.
              else {
                // Code has not changed, but DB does not match. User has DB overrides.
                if ($codecache == $default) {
                  $cache[$feature][$component] = CONFIGURATION_OVERRIDDEN;
                }
                // DB has no modifications to prior code state (or this is initial install).
                else if ($codecache == $normal || empty($codecache)) {
                  $cache[$feature][$component] = CONFIGURATION_REBUILDABLE;
                }
                // None of the states match. Requires user intervention.
                else if ($codecache != $default) {
                  $cache[$feature][$component] = CONFIGURATION_NEEDS_REVIEW;
                }
              }
            }
            else {
              // Semaphore is still within processing horizon. Do nothing.
              if ((REQUEST_TIME - $semaphore) < CONFIGURATION_SEMAPHORE_TIMEOUT) {
                $cache[$feature][$component] = CONFIGURATION_REBUILDING;
              }
              // A stale semaphore means a previous rebuild attempt did not complete.
              // Attempt to complete the rebuild.
              else {
                $cache[$feature][$component] = CONFIGURATION_REBUILDABLE;
              }
            }
          }
        }
      }
    }
  }

  // Filter cached components on the way out to ensure that even if we have
  // cached more data than has been requested, the return value only reflects
  // the requested configuration/components.
  $return = $cache;
  $return = array_intersect_key($return, array_flip($configuration));
  foreach ($return as $k => $v) {
    $return[$k] = array_intersect_key($return[$k], array_flip($components));
  }
  return $return;
}

/**
 * Helper function to eliminate whitespace differences in code.
 */
function _configuration_linetrim($code) {
  $code = explode("\n", $code);
  foreach ($code as $k => $line) {
    $code[$k] = trim($line);
  }
  return implode("\n", $code);
}

/**
 * "Sanitizes" an array recursively, performing two key operations:
 * - Sort an array by its keys (assoc) or values (non-assoc)
 * - Remove any null or empty values for associative arrays (array_filter()).
 */
function _configuration_sanitize(&$array) {
  if (is_array($array)) {
    if (_configuration_is_assoc($array)) {
      ksort($array);
      $array = array_filter($array);
    }
    else {
      sort($array);
    }
    foreach ($array as $k => $v) {
      if (is_array($v)) {
        _configuration_sanitize($array[$k]);
      }
    }
  }
}

/**
 * Is the given array an associative array. This basically extracts the keys twice to get the
 * numerically ordered keys. It then does a diff with the original array and if there is no
 * key diff then the original array is not associative.
 *
 * NOTE: If you have non-sequential numerical keys, this will identify the array as assoc.
 *
 * Borrowed from: http://www.php.net/manual/en/function.is-array.php#96724
 *
 * @return True is the array is an associative array, false otherwise
 */
function _configuration_is_assoc($array) {
  return (is_array($array) && (0 !== count(array_diff_key($array, array_keys(array_keys($array)))) || count($array)==0));
}

function _configuration_track_dependencies($export) {
  // Check if there are any configurations in this export that isn't being tracked.
  $save_also = array();
  if (isset ($export['configuration_dependency']) && !empty($export['configuration_dependency'])) {
    foreach ($export['configuration_dependency']['configuration'] as $component => $info) {
      foreach ($info as $name => $parent) {
        if (!configuration_is_tracked($component, $name)) {
          $save_also[$component][$name] = array(
            'parent' => $parent,
            'modules' => $export['configuration_dependency']['modules'][$component][$name],
          );
        }
        else {
          db_update('config_export')
            ->fields(array('parent' => $parent))
            ->condition('name', $name)
            ->execute();
        }
      }
    }
  }
  
  if (!empty($save_also)) {
    configuration_save($save_also);
  }
}

/**
 * Writes configurations file that mirrors the data in {config_export} table.
 */
function configuration_write_export_file($config = NULL, $dest = NULL) {
  if (!$config) {
    cache_clear_all('config_export', 'cache');
  }
  $config = $config ? $config : configuration_get_configuration();
  $dest = $dest ? $dest : 'config://config.export';

  $h = fopen($dest, 'w');
  foreach($config as $owner => $component) {
    if (is_array($component)) {
      foreach($component as $name => $settings) {
        fwrite($h, 'config['. $owner .']['. $name .'][status] = '. $settings['status'] ."\n");
        fwrite($h, 'config['. $owner .']['. $name .'][hash] = \''. $settings['hash'] ."'\n");
        fwrite($h, 'config['. $owner .']['. $name .'][parent] = \''. $settings['parent'] ."'\n");
        fwrite($h, 'config['. $owner .']['. $name .'][dependencies] = \''. $settings['dependencies'] ."'\n\n");
      }
    }
  } 
  fclose($h);  
}
 
/**
 * Writes configurations to disk.
 * 
 * @param $components An array of components to write to file.
 * @param $exclude An array $component => $identifier of what to exclude from export.
 * 
 * @return Returns true if write was sucessful
 */
function configuration_write_exports($components = NULL, $exclude = array()) {
  $config = configuration_get_configuration();
  try {
    // Check files in the config directory and ensure we won't overwrite
    // new configs before writing to file.
    if (configuration_check_changed((CONFIGURATION_DATASTORE_ONLY), $components)) {
      $config_populate = configuration_populate_sanitize($config);
      $export = configuration_populate($config_populate, array());

      // Track dependencies on config_export table
      _configuration_track_dependencies($export);
      
      // @see configuration_delete_multiple().
      foreach ($exclude as $identifier => $component) {
        unset($export['configuration'][$component][$identifier]);
      }
      // Remove any components that were not checked from being exported
      foreach (array_keys($export['configuration']) as $export_component) {
        if (!in_array($export_component, $components)) {
          unset($export['configuration'][$export_component]);
        }
      }
      
      // Write files to filesystem
      if ($files = configuration_export_render($export, TRUE)) {
        $filenames = array();
        foreach ($files as $filename => $file_contents) {
          if (!in_array($filename, array('module', 'info'))) {
            $filename .= '.inc';
          }
          file_put_contents('config://'. $filename, $file_contents);
          drupal_set_message(t('Wrote %file to filesystem', array('%file' => $filename)));
        }
      }
      configuration_write_export_file();
    }
    else {
      // Can't write to datastore.
      return false;
    }
  }
  catch (Exception $e) {
    watchdog_exception('configuration', $e);
    throw $e;
  }
  return true;
}

/**
 * Check components on datastore to see if there are any changes that would be
 * lost as a result of rewriting them.
 * 
 * @param $changed A Bitmask to check config status against
 * @param $components An array of components to check
 * @param $config An array of configurations to check components in
 */
function configuration_check_changed($changed, $components, $config = NULL) {
  $config = is_null($config) ? configuration_get_configuration() : $config;
  // Do not write the export file if the datastore is different
  foreach ($components as $component) {
    if (array_key_exists($component, $config)) {
      foreach ($config[$component] as $info) {
        if ($info['status'] & $changed) {
          drupal_set_message(t('Unable to write exports. Your datastore has configs that would be lost in %component.', array('%component' => $component)), 'error');
          return false;
        }
      }
    }
  }
  return true;
}