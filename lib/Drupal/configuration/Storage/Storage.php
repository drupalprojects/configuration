<?php

/**
 * @file
 * Definition of Drupal\configuration\Storage\Storage.
 */

namespace Drupal\configuration\Storage;
use Drupal\configuration\Config\ConfigurationManagement;

class Storage {

  protected $data;

  protected $dependencies;

  protected $optional_configurations;

  protected $required_modules;

  protected $api_version;

  protected $filename;

  protected $loaded;

  protected $hash;

  protected $keys_to_export = array();

  static public $file_extension = '';

  /**
   * Returns TRUE if the file for a configuration exists
   * in the config:// directory.
   */
  static public function configFileExists($component, $identifier) {
    return file_exists(ConfigurationManagement::getStream() . $component . '.' . $identifier . '.' . static::$file_extension);
  }

  /**
   * Returns TRUE if the current user has write permissions for a configuration
   * file in the config:// directory.
   */
  static public function checkFilePermissions($filename) {
    $dir_path = ConfigurationManagement::getStream();
    $full_path = $dir_path . $filename;
    if (is_writable($dir_path) || drupal_chmod($dir_path)) {
      if (file_exists($full_path)) {
        if (is_writable($full_path) || drupal_chmod($full_path)) {
          return TRUE;
        }
        else {
          drupal_set_message(t('The current user do not have permissions to edit the file %file.', array('%file' => $full_path)), 'error');
        }
      }
      else {
        return TRUE;
      }
    }
    else {
      watchdog('configuration', 'The current user do not have write permissions in the directory %dir.', array('%dir' => $dir_path), WATCHDOG_ERROR);
      drupal_set_message(t('The current user do not have write permissions in the directory %dir.', array('%dir' => $dir_path)), 'error', FALSE);
    }
    return FALSE;
  }

  public function __construct() {
    $this->reset();
  }

  public function reset() {
    $this->hash = '';
    $this->loaded = FALSE;
    $this->dependencies = array();
    $this->optional_configurations = array();
    $this->data = NULL;
    $this->api_version = '0.0.0';
  }

  public function export($var, $prefix = '') { }

  public function import($file_content) { }

  /**
   * Saves the configuration object into the DataStore.
   */
  public function save() { }

  /**
   * Loads the configuration object from the DataStore.
   *
   * @param $file_content
   *   Optional. The content to load directly.
   * @param $source
   *   Optional. An optional path to load the configuration.
   */
  public function load($file_content = NULL, $source = NULL) {
    return $this;
  }

  public function reLoad($file_content = NULL, $source = NULL) {
    $this->reset();
    return $this->load($file_content);
  }

  public function setFileName($filename) {
    $this->filename = $filename . static::$file_extension;
    return $this;
  }

  public function getFileName() {
    return $this->filename;
  }

  public function setData($data) {
    $this->data = $data;
    return $this;
  }

  /**
   * Set an array of keys names to export. If the array is empty,
   * all the keys of the configuration will be exported.
   */
  public function setKeysToExport($keys) {
    $this->keys_to_export = $keys;
    return $this;
  }

  public function withData() {
    return !empty($this->data);
  }

  public function getData() {
    return $this->data;
  }

  public function getDependencies() {
    return $this->dependencies;
  }

  public function setDependencies($dependencies) {
    $this->dependencies = $dependencies;
    return $this;
  }

  public function getOptionalConfigurations() {
    return $this->optional_configurations;
  }

  public function setOptionalConfigurations($optional_configurations) {
    $this->optional_configurations = $optional_configurations;
    return $this;
  }

  public function getModules() {
    return $this->required_modules;
  }

  public function setModules($modules) {
    $this->required_modules = $modules;
    return $this;
  }

  public function setApiVersion($api_version) {
    $this->api_version = $api_version;
    return $this;
  }

  public function getApiVersion() {
    return $this->api_version;
  }

  public function checkForChanges($object) {
    $new = $this->export($object);
    $original = $this->export($this->load()->data);
    return ($new != $original);
  }

  public function getHash() {
    return $this->hash;
  }

}
