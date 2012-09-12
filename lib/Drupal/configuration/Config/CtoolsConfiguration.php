<?php

/**
 * @file
 * Definition of Drupal\configuration\Config\CtoolsConfiguration.
 */

namespace Drupal\configuration\Config;

use Drupal\configuration\Config\Configuration;

abstract class CtoolsConfiguration extends Configuration {

  // The table where the configurations are storaged.
  static protected $table;

  static protected function getStorageInstance() {
    $storage = static::getStorageSystem();
    return new $storage(static::$table);
  }

  public function prepareBuild() {
    ctools_include('export');
    $this->data = ctools_export_crud_load(static::$table, $this->getIdentifier());
    return $this;
  }

  /**
   * Returns all the identifiers available for the given component.
   */
  public static function getAllIdentifiers() {
    ctools_include('export');
    $objects = ctools_export_load_object(static::$table, 'all');
    return drupal_map_assoc(array_keys($objects));
  }

  /**
   * Returns a class with its namespace to save data to the disk.
   */
  static protected function getStorageSystem() {
    return '\Drupal\configuration\Storage\StorageCtools';
  }

  static public function saveToActiveStore($components = array()) {
    if ($components) {
      foreach ($components as $config) {
        ctools_export_crud_save(static::$table, $config->getData());
      }
    }
  }

  static public function revertHook($components = array()) {
    ctools_include('export');
    foreach ($components as $component) {
      // Some things (like views) do not use the machine name as key
      // and need to be loaded explicitly in order to be deleted.
      $object = ctools_export_crud_load($component->getTable(), $component->getIdentifier());
      if ($object && ($object->export_type & EXPORT_IN_DATABASE)) {
        ctools_export_crud_delete($component->getTable(), $object);
      }
    }
  }

  /**
   * Returns the table where the configurations are storaged.
   */
  static public function getTable() {
    return static::$table;
  }
}
