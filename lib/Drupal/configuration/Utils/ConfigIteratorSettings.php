<?php

namespace Drupal\configuration\Utils;

class ConfigIteratorSettings {

  /**
   * The Configuration::callback to call on every iteration.
   */
  protected $callback;

  /**
   * A boolean flag to indicate that the $callback must be called
   * for the dependendencies of the current processed configuration.
   *
   * @var boolean
   */
  protected $process_dependencies = TRUE;

  /**
   * A boolean flag to indicate that the $callback must be called
   * for the optional configurations of the current processed configuration.
   *
   * @var boolean
   */
  protected $process_optionals = TRUE;

  /**
   * An array that storage the already processed configurations.
   * If a configuration is in this array, it will not be loaded
   * in each iteration.
   *
   * @var array
   */
  protected $cache = array();

  /**
   * An array of already processed configurations. In the id of the
   * configuration is is this array, it will not be proccesed by the
   * iterator.
   *
   * @var array
   */
  protected $already_processed = array();


  /**
   * An array to storage the useful info obtained after process the $callback
   * function.
   *
   * For example, while discovering module dependencies, each required
   * module should be included in the $info array. When processing dependency
   * trees, the ids of the configurations to enable should be saved in this
   * array.
   *
   * @var array
   */
  protected $info = array();

  /**
   * An array of settings that can be modified by the $callback function.
   * @var array
   */
  protected $settings = array();

  function __construct($settings) {
    $keys = array(
      'process_dependencies',
      'process_optionals',
      'callback',
      'cache',
      'already_processed',
      'settings'
    );
    foreach ($keys as $key) {
      if (isset($settings[$key])) {
        $this->{$key} = $settings[$key];
      }
    }
  }

  function processDependencies() {
    return $this->process_dependencies;
  }

  function processOptionals() {
    return $this->process_optionals;
  }

  function getCallback() {
    return $this->callback;
  }

  function getFromCache($id) {
    if (!empty($this->cache[$id])) {
      return $this->cache[$id];
    }
  }

  function addToCache($configuration) {
    $id = $configuration->getUniqueId();
    $already_processed[$id] = TRUE;
    $cthis->cache[$id] = $configuration;
  }

  function alreadyProcessed($configuration) {
    return !empty($this->already_processed[$configuration->getUniqueId()]);
  }

  function getSetting($key) {
    if (isset($this->settings[$key])) {
      return $this->settings[$key];
    }
  }

  function setSetting($key, $value) {
    $this->settings[$key] = $value;
  }

  function getInfo($key) {
    if (isset($this->info[$key])) {
      return $this->info[$key];
    }
  }

  function setInfo($key, $value) {
    $this->info[$key] = $value;
  }

  function addInfo($key, $value) {
    if (!isset($this->info[$key])) {
      $this->info[$key] = array();
    }
    $this->info[$key][] = $value;
  }

  function resetAlreadyProcessed() {
    $this->already_processed = array();
  }

  function resetCache() {
    $this->cache = array();
  }

  function resetSettings() {
    $this->settings = array();
  }

  function resetInfo() {
    $this->info = array();
  }
}