<?php

/**
 * @file
 * Definition of Drupal\configuration\Storage\Storage.
 */

namespace Drupal\configuration\Storage;

class Storage {

  protected $data;

  protected $dependencies;

  protected $optional_configurations;

  protected $required_modules;

  protected $filename;

  protected $loaded;

  static public $file_extension = '';

  protected $keys_to_export = array();

  /**
   * Returns TRUE if the file for a configuration exists
   * in the config:// directory.
   */
  static public function configFileExists($component, $identifier) {
    return file_exists('config://' . $component . '.' . $identifier . '.' . static::$file_extension);
  }

  public function __construct() {
    $this->reset();
  }

  public function reset() {
    $this->loaded = FALSE;
    $this->dependencies = array();
    $this->optional_configurations = array();
    $this->data = NULL;
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
   */
  public function load($file_content = NULL) {
    return $this;
  }

  public function reLoad($file_content = NULL) {
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

  public function checkForChanges($object) {
    $new = $this->export($object);
    $original = $this->export($this->load()->data);
    return ($new != $original);
  }
}
