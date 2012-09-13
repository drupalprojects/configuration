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

  public function saveToActiveStore(ConfigIteratorSettings &$settings) {
    ctools_export_crud_save(static::$table, $config->getData());
    $settings->addInfo('imported', $this->getUniqueId());
  }

  /**
   * Returns the table where the configurations are storaged.
   */
  static public function getTable() {
    return static::$table;
  }
}
