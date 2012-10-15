<?php

/**
 * @file
 * Definition of Drupal\configuration\Config\CtoolsConfiguration.
 */

namespace Drupal\configuration\Config;

use Drupal\configuration\Config\Configuration;
use Drupal\configuration\Utils\ConfigIteratorSettings;

class CtoolsConfiguration extends Configuration {

  protected $component;

  public function __construct($identifier, $component = '') {
    $this->identifier = $identifier;
    $this->component = $component;
    $this->storage = static::getStorageInstance($component);
    $this->storage->setFileName($this->getUniqueId());
  }

  static protected function getStorageInstance($component) {
    $storage = static::getStorageSystem($component);
    return new $storage($component);
  }

  public static function isActive() {
    return module_exists('ctools');
  }

  static public function getComponentHumanName($component, $plural = FALSE) {
    ctools_include('export');
    foreach (ctools_export_get_schemas_by_module() as $module => $schemas) {
      if (!empty($schemas[$component])) {
        if (!empty($schemas[$component]['export']['identifier'])) {
          return $schemas[$component]['export']['identifier'];
        }
        return $component;
      }
    }
    return '';
  }

  public function getComponent() {
    return $this->component;
  }

  static public function supportedComponents() {
    $supported = array();
    ctools_include('export');
    foreach (ctools_export_get_schemas_by_module() as $module => $schemas) {
      foreach ($schemas as $table => $schema) {
        $supported[] = $table;
      }
    }
    return $supported;
  }

  public function prepareBuild() {
    ctools_include('export');
    $this->data = ctools_export_crud_load($this->getComponent(), $this->getIdentifier());
    return $this;
  }

  /**
   * Returns all the identifiers available for the given component.
   */
  public static function getAllIdentifiers($component) {
    ctools_include('export');
    $objects = ctools_export_load_object($component, 'all');
    return drupal_map_assoc(array_keys($objects));
  }

  /**
   * Returns a class with its namespace to save data to the disk.
   */
  static protected function getStorageSystem($component) {
    return '\Drupal\configuration\Storage\StorageCtools';
  }

  public function saveToActiveStore(ConfigIteratorSettings &$settings) {
    ctools_include('export');
    $object = ctools_export_crud_load($this->getComponent(), $this->getIdentifier());
    if ($object && ($object->export_type & EXPORT_IN_DATABASE)) {
      ctools_export_crud_delete($this->getComponent(), $object);
    }
    ctools_export_crud_save($this->getComponent(), $this->getData());
    $settings->addInfo('imported', $this->getUniqueId());
  }

  public function findRequiredModules() {
    $this->addToModules('ctools');
    foreach (ctools_export_get_schemas_by_module() as $module => $schemas) {
      foreach ($schemas as $table => $schema) {
        if ($table == $this->getComponent()) {
          $this->addToModules($module);
        }
      }
    }
  }
}
