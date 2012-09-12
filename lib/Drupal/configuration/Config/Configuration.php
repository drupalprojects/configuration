<?php

/**
 * @file
 * Definition of Drupal\configuration\Config\Configuration.
 */

namespace Drupal\configuration\Config;

use \StdClass;
use Drupal\configuration\Utils\ConfigIteratorSettings;

class Configuration {

  /**
   * The component of this configuration, example, content_type, field, etc.
   */
  static protected $component = '';

  /**
   * The identifier that identifies to the component, usually the machine name.
   */
  protected $identifier;

  /**
   * The required modules to load this configuration.
   */
  protected $required_modules = array();

  /**
   * An array of configuration objects required to use this configuration.
   */
  protected $dependencies = array();

  /**
   * An array of configuration objects that are parts of this configurations
   * but are not required to use this configuration.
   */
  protected $optional_configurations = array();

  /**
   * The status of this configuration.
   *
   * Possible values:
   * CONFIGURATION_ACTIVESTORE_ONLY
   * CONFIGURATION_DATESTORE_ONLY
   * CONFIGURATION_ACTIVESTORE_OVERRIDDEN
   * CONFIGURATION_IN_SYNC
   */
  protected $status;

  /**
   * The data of this configuration.
   */
  protected $data;

  /**
   * An array of keys names to export. If the array is empty,
   * all the keys of the configuration will be exported.
   */
  protected $keys_to_export = array();

  /**
   * An object to save and load the data from a persistent medium.
   */
  protected $storage;

  public function __construct($identifier) {
    $this->identifier = $identifier;
    $this->status = CONFIGURATION_IN_SYNC;
    $this->storage = static::getStorageInstance();
    $this->storage->setFileName($this->getUniqueId());
  }

  /**
   * Returns a class with its namespace to save data to the disk.
   */
  static protected function getStorageSystem() {
    $default = '\Drupal\configuration\Storage\StoragePhp';
    // Specify a default Storage system
    $return = variable_get('configuration_storage_system', $default);
    // Allow to configure the Storage System per configuration component
    $return = variable_get('configuration_storage_system_' . static::$component, $return);
    return $return;
  }

  static protected function getStorageInstance() {
    $storage = static::getStorageSystem();
    return new $storage();
  }

  /**
   * Return TRUE if this class can handle multiple configurations componenets.
   */
  static public function multiComponent() {
    return FALSE;
  }

  /**
   * Returns all the identifiers available for this component.
   */
  public static function getAllIdentifiers() {
    return array();
  }

  /**
   * Returns a list of all the clases that manages configurations
   */
  public static function getAllConfigurationHandlers() {
    // @TODO: Use the chache for this
    $handlers = module_invoke_all('configuration_handlers');
    return $handlers;
  }

  /**
   * Returns a handler that manages the configurations for the given component.
   */
  public static function getConfigurationHandler($component) {
    static $handlers;
    if (!isset($handlers)) {
      $handlers = static::getAllConfigurationHandlers();
    }
    return '\\' . $handlers[$component]['namespace'] . '\\' . $handlers[$component]['handler'];
  }

  /**
   * Loads configurations into staging setting status as "needs rebuild".
   */
  public static function revertConfigurations($component_stack = array(), $list_to_revert = array(), $revert_dependencies = TRUE, $revert_optionals = TRUE) {
    $reverted = array();

    // If there are no defined components to revert, import all.
    if (empty($list_to_revert)) {
      $list_to_revert = $component_stack;
    }

    // While there is items to revert.
    while (!empty($list_to_revert)) {
      // Extract a component to import.
      $component = array_pop($list_to_revert);

      $reverted[$component] = TRUE;

      list($component_name, $identifier) = explode('.', $component, 2);
      $handler = Configuration::getConfigurationHandler($component_name);

      $config_instance = new $handler($identifier);
      $config_instance->load("staging");

      // Add the dependencies to the list of configurations to revert
      if ($revert_dependencies) {
        foreach ($config_instance->getDependencies() as $dependency) {
          if (empty($reverted[$dependency])) {
            $list_to_revert[$dependency] = $dependency;
          }
        }
      }

      // Add the optional configurations to the list of configurations to revert
      if ($revert_optionals) {
       foreach ($config_instance->getOptionalConfigurations() as $optional) {
          if (empty($reverted[$optional])) {
            $list_to_revert[$optional] = $optional;
          }
        }
      }

      $config_instance->revert()->setStatus(CONFIGURATION_NEEDS_REBUILD);
    }
    return array_keys($reverted);
  }

  /**
   * Loads configurations into staging setting status as "needs rebuild".
   */
  public static function importConfigurations($component_stack = array(), $list_to_import = array(), $import_dependencies = TRUE, $import_optionals = TRUE,  $save_to_staging = TRUE) {
    $to_import = array();

    $imported = array();

    // If there are no defined components to import, import all.
    if (empty($list_to_import)) {
      $list_to_import = $component_stack;
    }

    // While there is items to import.
    while (!empty($list_to_import)) {
      // Extract a component to import.
      $component = array_pop($list_to_import);

      $imported[$component] = TRUE;

      list($component_name, $identifier) = explode('.', $component, 2);
      $handler = Configuration::getConfigurationHandler($component_name);

      $config_instance = new $handler($identifier);
      $config_instance->storage->load();

      $data = $config_instance->storage->getData();
      $dependencies = $config_instance->storage->getDependencies();
      $optional = $config_instance->storage->getOptionalConfigurations();
      $modules = $config_instance->storage->getModules();

      $config_instance
        ->setData($data)
        ->setDependencies($dependencies)
        ->setOptionalConfigurations($optional)
        ->setModules($modules);

      // Add the dependencies to the list of configurations to import
      if ($import_dependencies) {
        foreach ($config_instance->getDependencies() as $dependency) {
          if (empty($imported[$dependency])) {
            $list_to_import[$dependency] = $dependency;
          }
        }
      }

      // Add the optional configurations to the list of configurations to import
      if ($import_optionals) {
       foreach ($config_instance->getOptionalConfigurations() as $optional) {
          if (empty($imported[$optional])) {
            $list_to_import[$optional] = $optional;
          }
        }
      }

      if ($save_to_staging) {
        $config_instance
          ->revert()
          ->setStatus(CONFIGURATION_NEEDS_REBUILD)
          ->saveToStaging();
      }
      else {
        $to_import[$config_instance->getUniqueId()] = $config_instance;
      }
    }
    return $to_import;
  }

  /**
   * Returns the list of components available in the DataStore.
   */
  public static function scanDataStore() {
    $list_of_components = array();

    $path = drupal_realpath('config://');
    $storage_system = static::getStorageSystem();
    $ext = $storage_system::$file_extension;
    $look_for = '/\A' . static::$component . '\..*' . $ext . '$/';

    $files = file_scan_directory($path, $look_for);

    foreach ($files as $file) {
      if (!in_array($file->name, $list_of_components)) {
        $storage = static::getStorageInstance();
        $storage
          ->setFileName($file->name)
          ->load();

        if ($storage->withData()) {
          $list_of_components[$file->name] = $file->name;
        }
      }
    }
    return $list_of_components;
  }

  /**
   * Returns a list of all the configurations that should be proccessed based on
   * the dependencies of the current configuration.
   */
  public function completeComponents(&$component_ordered_stack = array(), $property, $source) {
    $id = $this->getUniqueId();

    if (!empty($this->{$property})) {
      foreach ($this->{$property} as $config_uniqueid) {
        list($config_component, $config_identifier) = explode('.', $config_uniqueid, 2);
        if (!in_array($config_uniqueid, $component_ordered_stack)) {
          $handler = Configuration::getConfigurationHandler($config_component);
          $config_instance = new $handler($config_identifier);
          $config_instance->load($source);
          $config_instance->completeComponents($component_ordered_stack, $property, $source);
          unset($config_instance);
        }
      }
    }
    $component_ordered_stack[$id] = $id;
  }

  /**
   * Gets a array of components marked for rebuild and process them.
   */
  static public function executeRebuildHook() {
    $from_staging = db_select('configuration_staging', 'c')
            ->fields('c', array('identifier', 'data'))
            ->condition('component', static::$component)
            ->condition('status', CONFIGURATION_NEEDS_REBUILD)
            ->execute()
            ->fetchAll();

    $components = array();
    foreach ($from_staging as $item) {
      $config = new static($item->identifier);
      $config->setData(unserialize($item->data));
      $components[$config->getUniqueId()] = $config;
    }

    static::saveToActiveStore($components);

    /**
     * @todo
     * Get actual result for each component rebuild and change status only for
     * those processed correctly.
     */
    db_update('configuration_staging')
        ->fields(array('status' => CONFIGURATION_IN_SYNC))
        ->condition('component', static::$component)
        ->execute();
  }

  /**
   * Automatically call to the revertHook using only the information of the
   * current configuration.
   */
  public function revert() {
    static::revertHook(array($this));
    return $this;
  }

  /**
   * Clear the component configuration in the active store.
   *
   * Some configurations provide hooks to load configurations from code.
   * In order to update this configurations each one has to implement a way to
   * clear or update the objects in the database.
   */
  static public function revertHook($components = array()) {
    // Override
  }

    /**
   * Loads the configuration into the active store.
   *
   * Some configurations like fields, permissions, roles, etc doesn't
   * provide hooks to load configurations from code.
   * In order to load this configuration, children classes of this kind
   * of configs, must define the way to load this data into the ActiveStore.
   */
  static public function saveToActiveStore($components = array()) {
    // Override
  }

  /**
   * Load a configuration from $source,
   * @param $source
   *   Available options: "staging" or "datastore".
   */
  public function load($source) {
    if ($source == "staging") {
      return $this->loadFromStaging();
    }
    elseif ($source == "datastore") {
      return $this->loadFromStorage();
    }
  }

  protected function loadFromStorage() {
    $this->storage->load();
    $this->setData($this->storage->getData());
    $this->setDependencies($this->storage->getDependencies());
    $this->setOptionalConfigurations($this->storage->getOptionalConfigurations());
    $this->setModules($this->storage->getModules());
    return $this;
  }

  protected function loadFromStaging() {
    $object = db_select('configuration_staging', 'cs')
                        ->fields('cs')
                        ->condition('component', $this->getComponent())
                        ->condition('identifier', $this->getIdentifier())
                        ->execute()
                        ->fetchObject();

    $this->setData(unserialize($object->data));
    $this->setDependencies(unserialize($object->dependencies));
    $this->setOptionalConfigurations(unserialize($object->optional));
    $this->setModules(unserialize($object->modules));
    return $this;
  }

  /**
   * Save a configuration object into the configuration_staging table.
   */
  public function saveToStaging() {
    db_delete('configuration_staging')
      ->condition('component', static::$component)
      ->condition('identifier', $this->getIdentifier())
      ->execute();

    $fields = array(
      'component' => static::$component,
      'identifier' => $this->getIdentifier(),
      'data' => serialize($this->getData()),
      'status' => $this->status,
      'dependencies' => serialize(array_keys($this->getDependencies())),
      'optional' => serialize(array_keys($this->getOptionalConfigurations())),
      'modules' => serialize($this->getModules()),
    );
    db_insert('configuration_staging')->fields($fields)->execute();
  }

  /**
   * Returns an array of keys names to export. If the array is empty,
   * all the keys of the configuration will be exported.
   */
  public function getKeysToExport() {
    return $this->keys_to_export;
  }

  /**
   * Set an array of keys names to export. If the array is empty,
   * all the keys of the configuration will be exported.
   */
  public function setKeysToExport($keys) {
    $this->keys_to_export = $keys;
    return $this;
  }

  /**
   * Export the data to the DataStore.
   */
  public function exportToDataStore(&$already_exported = array(), $export_dependencies = TRUE, $export_optionals = TRUE, $save_to_storage = TRUE) {

    $id = $this->getComponent() . '.' . $this->getIdentifier();
    if (!empty($already_exported[$id])) {
      return $this;
    }
    $already_exported[$id] = TRUE;

    // Make sure the configuration is built.
    $this->build();

    $dependencies = array();
    if ($export_dependencies) {
      foreach ($this->getDependencies() as $config_dependency) {
        $id = $config_dependency->getComponent() . '.' . $config_dependency->getIdentifier();
        $dependencies[$id] = $id;
      }
    }

    $optional_configurations = array();
    if ($export_dependencies) {
      foreach ($this->getOptionalConfigurations() as $optional_configuration) {
        $id = $optional_configuration->getComponent() . '.' . $optional_configuration->getIdentifier();
        $optional_configurations[$id] = $id;
      }
    }

    if ($save_to_storage) {
      // Save the configuration into a file.
      $this->storage
              ->setData($this->data)
              ->setKeysToExport($this->getKeysToExport())
              ->setDependencies($dependencies)
              ->setOptionalConfigurations($optional_configurations)
              ->setModules($this->required_modules)
              ->save();
    }

    // Also, save the configuration in the database
    $this->saveToStaging();

    if (!empty($export_optionals)) {
      foreach ($this->getOptionalConfigurations() as $config) {
        $config->exportToDataStore($already_exported, $export_dependencies, $export_optionals, $save_to_storage);
      }
    }
    if (!empty($export_dependencies)) {
      foreach ($this->getDependencies() as $config) {
        $config->exportToDataStore($already_exported, $export_dependencies, $export_optionals, $save_to_storage);
      }
    }
    return $this;
  }

  /**
   * Backup the configuration into the Staging Area.
   */
  public function backupConfiguration(&$already_backuped = array(), $backup_dependencies = TRUE, $backup_optionals = TRUE) {
    // Basically the same mechanism that backup to the datastore but without
    // save the object in the storage, only save in the staging area.
    return $this->exportToDataStore($already_backuped, $backup_dependencies, $backup_optionals, FALSE);
  }

  /**
   * Gets the structure of the configuration and save
   * it into the $data attribute.
   */
  protected function prepareBuild() {
    // This method must be overrided by children classes.
  }

  /**
   * Build the configuration object based on the component name and
   * in the identifier.
   *
   * The build process implies get the structure of the configuration and save
   * it into the $data attribute. Also, this function should look for the
   * dependencies of this configuration if $include_dependencies is TRUE.
   */
  public function build($include_dependencies = TRUE) {
    $this->prepareBuild();
    if ($include_dependencies) {
      $this->findDependencies();
    }
    $this->findRequiredModules();
    return $this;
  }

  /**
   * Provides the differences between two configurations of the same component.
   */
  public function diff() {

  }

  /**
   * Compares the configuration storaged in the ActiveStore with the
   * storaged into the DataStore. The status of the object is
   * updated according to the differences.
   */
  public function checkForChanges() {
    $this->build();
    $this->storage->load();

    if (!$this->storage->withData()) {
      $this->status = CONFIGURATION_ACTIVESTORE_ONLY;
    }
    elseif (empty($this->data)) {
      $this->status = CONFIGURATION_DATESTORE_ONLY;
    }
    else {
      if ($this->storage->checkForChanges($this->data)) {
        $this->status = CONFIGURATION_ACTIVESTORE_OVERRIDDEN;
      }
      else {
        $this->status = CONFIGURATION_IN_SYNC;
      }
    }
    return $this;
  }

  public function getUniqueId() {
    return static::$component . '.' . $this->getIdentifier();
  }

  /**
   * Returns the component that this configuration represent.
   */
  static public function getComponent() {
    return static::$component;
  }

  /**
   * Returns the component that this configuration represent.
   */
  public function getStatus() {
    return $this->status;
  }

  /**
   * Update the object status.
   */
  public function setStatus($value) {
    $this->status = $value;
    return $this;
  }

  /**
   * Returns the identifier of the configuration object.
   */
  public function getIdentifier() {
    return $this->identifier;
  }

  /**
   * Set the component identifier of this configuration
   */
  public function setIdentifier($value) {
    $this->identifier = $value;
    return $this;
  }

  /**
   * Return the data for this configuration.
   */
  public function getData() {
    return $this->data;
  }

  /**
   * Set the data for this configuration.
   */
  public function setData($value) {
    $this->data = $value;
    return $this;
  }

  /**
   * Returns the name of the required_modules that provide this configuration.
   */
  public function getModules() {
    return $this->required_modules;
  }

  /**
   * Set the name of the required_modules that provide this configuration.
   */
  public function setModules($list) {
    $this->required_modules = $list;
    return $this;
  }

  /**
   * Add the required modules to load this configuration.
   */
  public function findRequiredModules() {
    // Configurations classes should use this method to add the required
    // modules to load the configuration.
  }

  /**
   * Add a new dependency for this configuration.
   */
  public function addToModules($module) {
    if (!in_array($module, $this->required_modules)) {
      $this->required_modules[] = $module;
    }
    return $this;
  }

  /**
   * Return TRUE if this is the configuration for an entity.
   */
  public function configForEntity() {
    return FALSE;
  }

  /**
   * Add a new child configuration for this configuration.
   */
  public function addToOptionalConfigurations(Configuration $config) {
    if (!isset($this->optional_configurations)) {
      $this->optional_configurations = array();
    }
    $this->optional_configurations[$config->getUniqueId()] = $config;
    return $this;
  }

  /**
   * Returns the list of optional_configurations of this configuration
   */
  public function getOptionalConfigurations() {
    return $this->optional_configurations;
  }

  /**
   * Returns the list of optional_configurations of this configuration
   */
  public function setOptionalConfigurations($optional_configurations) {
    $this->optional_configurations = $optional_configurations;
    return $this;
  }

  /**
   * Add a new dependency for this configuration.
   */
  public function addToDependencies(Configuration $config) {
    if (!isset($this->dependencies)) {
      $this->dependencies = array();
    }
    $this->dependencies[$config->getUniqueId()] = $config;
    return $this;
  }

  /**
   * Returns the list of dependencies of this configuration
   */
  public function getDependencies() {
    return $this->dependencies;
  }

  /**
   * Returns the list of dependencies of this configuration
   */
  public function setDependencies($dependencies) {
    $this->dependencies = $dependencies;
    return $this;
  }

  /**
   * Ask to each configuration handler to add its dependencies
   * to the current configuration that is being exported.
   */
  public function findDependencies() {
    $handlers = static::getAllConfigurationHandlers();
    $stack = array();
    // Include this configuration to the stack to avoid add it again
    // in a circular dependency cycle
    $stack[$this->getUniqueId()] = TRUE;

    foreach ($handlers as $configuration_component => $info) {
      $class = '\\' . $info['namespace'] . '\\' . $info['handler'];
      $class::alterDependencies($this, $stack);
    }
  }

  /**
   * Configurations should implement this function to add configuration
   * objects (by using addToDepedencies).
   *
   * @param $config
   *   The object that requires all the dependencies.
   * @param $stack
   *   A list of already added dependencies, to avoid duplicates.
   */
  public static function alterDependencies(Configuration $config, &$stack) {
    // Override
  }

  /**
   * Returns TRUE if all the dependencies of this configurations are met.
   * Returns FALSE if a module or a dependency is required by this configuration
   * is not enabled.
   */
  public function checkDependencies() {
    foreach ($this->required_modules as $module) {
      if (!module_exists($module)) {
        return FALSE;
      }
    }
    foreach ($this->getDependencies() as $dependency) {
      // First, look for the dependency in the staging table.
      $exists = db_select('configuration_staging', 'cs')
                        ->fields('cs', array('identifier'))
                        ->condition('component', static::$component)
                        ->condition('identifier', $this->getIdentifier())
                        ->fetchField();

      if (!$exists) {
        // If not exists in the database, look into the config:// directory.
        $storage_system = static::getStorageSystem();
        $file_exists = $storage_system::configFileExists(static::$component, $this->getIdentifier());
        if (!$file_exists) {
          return FALSE;
        }
      }
    }
  }

  /**
   * Returns a list of modules that are required to run this configuration.
   *
   * @return
   *   A keyed array by module name that idicates the status of each module.
   */
  public function getRequiredModules() {
    $stack = array();
    foreach ($this->getModules() as $module) {
      $this->getDependentModules($module, $stack);
    }
    return $stack;
  }

  /**
   * Determine the status of the given module and of its dependencies.
   */
  protected function getDependentModules($module, &$stack) {
    $available_modules = static::getAvailableModules();
    if (!isset($available_modules[$module])) {
      $stack[$module] = CONFIGURATION_MODULE_MISSING;
      return;
    }
    else {
      if (empty($available_modules[$module]->status)) {
        $stack[$module] = CONFIGURATION_MODULE_TO_INSTALL;
        foreach ($available_modules[$module]->requires as $required_module) {
          if (empty($stack[$required_module['name']])) {
            $this->getDependentModules($required_module['name'], $stack);
          }
        }
      }
      else {
        $stack[$module] = CONFIGURATION_MODULE_INSTALLED;
      }
    }
  }

  /**
   * Helper for retrieving info from system table.
   */
  protected function getAvailableModules($reset = FALSE) {
    static $modules;

    if (!isset($modules)) {
      // @todo use cache for this function

      $files = system_rebuild_module_data();
      $modules = array();
      foreach($files as $id => $file) {
        if ($file->type == 'module' && empty($file->info['hidden'])) {
          $modules[$id] = $file;
        }
      }
    }

    return $modules;
  }

  /**
   * This function will exectute a callback function over all the configurations
   * objects that it process.
   *
   * @param  ConfigIteratorSettings $settings
   *   A ConfigIteratorSettings instance that specifies, which is the callback
   *   to execute. If dependencies and optional configurations should be
   *   processed too, and storage the cache of already processed configurations.
   * @return [type]                           [description]
   */
  function iterate(ConfigIteratorSettings &$settings) {
    $callback = $settings->getCallback();

    // First proccess requires the dependencies that have to be processed before
    // load the current configuration.
    if ($settings->processDepdendencies()) {
      foreach ($this->getDependencies() as $dependency) {
        $handler = $settings->getFromCache($dependency);
        if (!$handler) {
          list($component_name, $identifier) = explode('.', $dependency, 2);
          $handler = Configuration::getConfigurationHandler($component_name);
          $handler->iterate($settings);
        }
      }
    }

    // Now, after proccess the dependencies, proccess the current configuration.
    if (!$settings->alreadyProcessed($this)) {
      $this->{$callback}($settings);
      $settings->addToCache($this);
    }

    // After proccess the dependencies and the current configuration, proccess
    // the optionals.
    if ($this->processOptionals()) {
      foreach ($this->getOptionalConfigurations() as $optional) {
        $handler = $settings->getFromCache($optional);
        if (!$handler) {
          list($component_name, $identifier) = explode('.', $optional, 2);
          $handler = Configuration::getConfigurationHandler($component_name);
          $handler->iterate($settings);
        }
      }
    }
  }
}
