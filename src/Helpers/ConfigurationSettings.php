<?php

namespace Configuration\Helpers;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Camspiers\JsonPretty\JsonPretty;

class ConfigurationSettings {

  protected $settings;
  protected $base_settings_path;
  protected $environment;
  protected $settings_filename;
  protected $cache;

  public function __construct() {
    $this->settings_filename = 'settings.json';
    $this->reset();
  }

  public function reset() {
    $this->cache = array();
    $this->base_settings_path = variable_get('configapi_base_settings_path', NULL);
    if (empty($this->base_settings_path)) {
      $this->base_settings_path = drupal_realpath('public://config');
    }
    $this->environment = variable_get('configapi_environment', 'dev');
    $this->settings = $this->defaultConfig();
  }

  protected function defaultConfig() {
    $settings = new \StdClass;
    $settings->environments = array();

    $settings->export = new \StdClass;
    $settings->export->path = 'config';
    $settings->export->overriden_patterns = new \StdClass;
      // 'identifier' => 'specific pattern',
      // 'type.*' => 'specific pattern for all the components of this type',

    $settings->export->default_pattern = '[group]/[type]/';
    $settings->export->fast_export = TRUE;
    $settings->export->format = 'json';
    $settings->export->batch = FALSE;
    $settings->export->exclude = array();
    $settings->export->groups = array();

    $settings->import = new \StdClass;
    $settings->import->path = 'config';
    $settings->import->format = 'json';
    $settings->import->batch = FALSE;
    $settings->import->exclude = array();
    $settings->import->import_parts = TRUE;
    $settings->import->import_only_if_hash_changed = TRUE;
    $settings->import->delete_configs_not_exported = FALSE;

    $settings->alter = array();
    return $settings;
  }

  public function load($path = NULL) {
    $directories = array();
    if (!empty($path)) {
      $directories[] = $path;
    }
    $directories[] = $this->base_settings_path;

    $locator = new FileLocator($directories);
    $config_full_path = $locator->locate($this->settings_filename);
    if (!empty($config_full_path)) {
      $file_content = file_get_contents($config_full_path);
      $this->settings = $this->prepareSettings(json_decode($file_content, $this->decodeJsonAsArray()));

      $this->validate();
    }
  }

  protected function prepareSettings($settings) {
    return $settings;
  }

  protected function decodeJsonAsArray() {
    return FALSE;
  }

  public function validate() {
    $check = array(
      'export' => array(
        'path', 'format', 'fast_export', 'batch', 'exclude', 'groups',
      ),
      'import' => array(
        'path', 'format', 'batch', 'exclude', 'import_only_if_hash_changed', 'delete_configs_not_exported',
      )
    );

    foreach ($check as $group => $keys) {
      foreach ($keys as $key) {
        if (!isset($this->settings->$group->$key)) {
          throw new \Exception("There is no value defined for settings.$group.$key");
        }
      }
    }
  }

  public function save() {
    $fs = new Filesystem();
    try {
      $fs->mkdir($this->base_settings_path);
      $jsonPretty = new JsonPretty;
      $export = $jsonPretty->prettify($this->settings, NULL, '  ');
      $path = $this->base_settings_path . '/' . $this->settings_filename;
      $fs->dumpFile($path, $export);
    }
    catch (IOExceptionInterface $e) {
      echo "The directory for configs could not be created: ". $e->getPath();
    }
  }

  public function get($key) {
    if (isset($this->cache[$key])) {
      return $this->cache[$key];
    }

    $accessor = PropertyAccess::createPropertyAccessor();
    if ($accessor->isReadable($this->settings, $key)) {
      $this->cache[$key] = $accessor->getValue($this->settings, $key);
      return $this->cache[$key];
    }
    else {
      throw new \Exception("Error trying to read the property " . $key);
    }
  }

  public function set($key, $value) {
    $accessor = PropertyAccess::createPropertyAccessor();
    if ($accessor->isWritable($this->settings, $key)) {
      $accessor->setValue($this->settings, $key, $value);
      $this->cache[$key] = $value;
      return $this;
    }
    else {
      throw new \Exception("Error trying to write a value for the property " . $key);
    }
  }

}
