<?php

namespace Configuration\Helpers;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Camspiers\JsonPretty\JsonPretty;

class ConfigurationSettingsList extends ConfigurationSettings {

  public function __construct() {
    $this->settings_filename = 'configurations.json';
    $this->reset();
  }

  protected function defaultConfig() {
    $settings = new \StdClass;
    $settings->configurations = array();
    $settings->modules = array();

    return $settings;
  }

  public function prepareSettings($imported_settings) {
    $settings = new \StdClass;
    $settings->configurations = $imported_settings['configurations'];
    $settings->modules = $imported_settings['modules'];
    return $settings;
  }

  protected function decodeJsonAsArray() {
    return TRUE;
  }

  public function validate() {
    if (!isset($this->settings->configurations)) {
      throw new \Exception("There is no value defined for settings.configurations");
    }
    else {
      foreach ($this->settings->configurations as $identifier => $value) {
        foreach (array('hash', 'path') as $key => $value) {
          if (!isset($value[$key])) {
            throw new \Exception("There is no $key defined for $identifier");
          }
        }
      }
    }

    if (!isset($this->settings->modules)) {
      throw new \Exception("There is no value defined for settings.modules");
    }
    else {
      if (!is_array($this->settings->modules)) {
        throw new \Exception("There settings.modules is not an array");
      }
    }
  }

  public function getConfigurations() {
    return $this->settings->configurations;
  }

  public function getModules() {
    return $this->settings->modules;
  }

}
