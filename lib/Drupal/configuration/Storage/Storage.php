<?php

/**
 * @file
 * Definition of Drupal\configuration\Storage\Storage.
 */

namespace Drupal\configuration\Storage;

class Storage {

  protected $data;

  protected $dependencies;

  protected $filename;

  protected $loaded;

  static public $file_extension = '';

  public function __construct() {
    $this->reset();
  }

  public function reset() {
    $this->loaded = FALSE;
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

  public function setDependencies($dependencies) {
    $this->dependencies = $dependencies;
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

  public function checkForChanges($object) {
    $new = $this->export($object);
    $original = $this->export($this->load()->data);
    return ($new != $original);
  }
}
