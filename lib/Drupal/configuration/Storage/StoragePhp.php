<?php

/**
 * @file
 * Definition of Drupal\configuration\Storage\StoragePhp.
 */

namespace Drupal\configuration\Storage;

use Drupal\configuration\Storage\Storage;

class StoragePhp extends Storage {

  static public $file_extension = '.inc';

  /**
   * Adapted from CTools ctools_var_export().
   *
   * This is a replacement for var_export(), allowing us to more nicely
   * format exports. It will recurse down into arrays and will try to
   * properly export bools when it can, though PHP has a hard time with
   * this since they often end up as strings or ints.
   */
  public function export($var, $prefix = '') {
    if (is_array($var)) {
      if (empty($var)) {
        $output = 'array()';
      }
      else {
        $output = "array(\n";
        foreach ($var as $key => $value) {
          $output .= $prefix . "  " . $this->export($key) . " => " . $this->export($value, $prefix . '  ') . ",\n";
        }
        $output .= $prefix . ')';
      }
    }
    else if (is_object($var) && get_class($var) === 'stdClass') {
      // var_export() will export stdClass objects using an undefined
      // magic method __set_state() leaving the export broken. This
      // workaround avoids this by casting the object as an array for
      // export and casting it back to an object when evaluated.
      $output = '(object) ' . $this->export((array) $var, $prefix);
    }
    else if (is_bool($var)) {
      $output = $var ? 'TRUE' : 'FALSE';
    }
    else {
      $output = var_export($var, TRUE);
    }

    return $output;
  }

  public function import($file_content) {
    eval($file_content);
    $this->data = $object;
    $this->dependencies = $dependencies;
    return $this;
  }

  /**
   * Saves the configuration object into the DataStore.
   */
  public function save() {
    $filename = $this->filename;
    $export = '$object = ' . $this->export($this->data) . ";\n\n\$dependencies = " . $this->export($this->dependencies) . ';';
    $file_contents = "<?php\n/**\n * @file\n * {$filename}\n */\n\n" . $export;
    file_put_contents('config://' . $filename, $file_contents);
    return $this;
  }

  /**
   * Loads the configuration object from the DataStore.
   *
   * @param $file_content
   *   Optional. The content to load directly.
   */
  public function load($file_content = NULL) {
    if (empty($this->loaded)) {
      $this->loaded = TRUE;
      if (empty($file_content)) {
        if (!file_exists('config://' . $this->filename)) {
          $this->data = NULL;
        }
        else {
          $file_content = substr(file_get_contents('config://' . $this->filename), 6);
        }
      }
      if (!empty($file_content)) {
        $this->import($file_content);
      }
    }
    return $this;
  }
}
