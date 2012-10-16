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
   * The identifier that identifies to the component, usually the machine name.
   */
  protected $identifier;

  /**
   * A hash that represent that sumarizes the configuration and can
   * be used to copare configurations.
   */
  protected $hash;

  /**
   * The data of this configuration.
   */
  protected $data;

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
   * The required modules to load this configuration.
   */
  protected $required_modules = array();

  /**
   * An array of keys names to export. If the array is empty,
   * all the keys of the configuration will be exported.
   */
  protected $keys_to_export = array();

  /**
   * An object to save and load the data from a persistent medium.
   */
  protected $storage;

  /**
   * A boolean flag to indicate if the configuration object was already
   * populated from the ActiveStore, or from the DataStore.
   */
  protected $built;

  public function __construct($identifier, $component = '') {
    $this->identifier = $identifier;
    $this->storage = static::getStorageInstance($component);
    $this->storage->setFileName($this->getUniqueId());
  }

  /**
   * Returns a class with its namespace to save data to the disk.
   */
  static protected function getStorageSystem($component) {
    $default = '\Drupal\configuration\Storage\StoragePhp';
    // Specify a default Storage system
    $return = variable_get('configuration_storage_system', $default);
    // Allow to configure the Storage System per configuration component
    $return = variable_get('configuration_storage_system_' . $component, $return);
    return $return;
  }

  /**
   * Returns a Storage Object ready to load or write configurations from the
   * disk.
   */
  static protected function getStorageInstance($component) {
    $storage = static::getStorageSystem($component);
    $return = new $storage();
    return $return;
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
  public static function getAllIdentifiers($component) {
    return array();
  }

  /**
   * Returns the list of components available in the DataStore.
   */
  public static function scanDataStore($component) {
    $list_of_components = array();

    $path = drupal_realpath('config://');
    $storage_system = static::getStorageSystem($component);
    $ext = $storage_system::$file_extension;
    $look_for = '/\A' . $component . '\..*' . $ext . '$/';

    $files = file_scan_directory($path, $look_for);

    foreach ($files as $file) {
      if (!in_array($file->name, $list_of_components)) {
        $storage = static::getStorageInstance($component);
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

  public function loadFromActiveStore() {
    $this->build();
    $this->buildHash();
    return $this;
  }

  /**
   * Load the Configuration data from the disk.
   */
  public function loadFromStorage() {
    $this->storage->load();
    $this->setData($this->storage->getData());
    $this->setDependencies($this->storage->getDependencies());
    $this->setOptionalConfigurations($this->storage->getOptionalConfigurations());
    $this->setModules($this->storage->getModules());
    // This build the Hash;
    $this->storage->getDataToSave();
    $this->setHash($this->storage->getHash());

    $this->built = TRUE;
    return $this;
  }

  /**
   * Load the configuration data using the information saved in the
   * configuration_staging table.
   */
  public function loadFromStaging() {
    $object = db_select('configuration_staging', 'cs')
                        ->fields('cs')
                        ->condition('component', $this->getComponent())
                        ->condition('identifier', $this->getIdentifier())
                        ->execute()
                        ->fetchObject();

    $this->setHash($object->hash);
    $this->setData(unserialize($object->data));
    $this->setDependencies(drupal_map_assoc(unserialize($object->dependencies)));
    $this->setOptionalConfigurations(drupal_map_assoc(unserialize($object->optional)));
    $this->setModules(unserialize($object->modules));
    $this->built = TRUE;
    return $this;
  }

  /**
   * Save a configuration object into the configuration_staging table.
   */
  public function saveToStaging() {
    db_delete('configuration_staging')
      ->condition('component', $this->getComponent())
      ->condition('identifier', $this->getIdentifier())
      ->execute();

    $fields = array(
      'component' => $this->getComponent(),
      'identifier' => $this->getIdentifier(),
      'hash' => $this->getHash(),
      'data' => serialize($this->getData()),
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
   * Internal function to discover what modules are required for the current
   * being proccessed configurations.
   *
   * @see iterate()
   */
  protected function discoverModules(ConfigIteratorSettings &$settings) {
    $this->loadFromStorage();
    $modules = $settings->getInfo('modules');
    $modules = array_merge($modules, $this->getRequiredModules());
    $settings->setInfo('modules', $modules);
  }

  /**
   * Removes the configuration record from the configuration_staging table for
   * the current configuration.
   */
  public function removeFromStaging(ConfigIteratorSettings &$settings) {
    db_delete('configuration_staging')
      ->condition('component', $this->getComponent())
      ->condition('identifier', $this->getIdentifier())
      ->execute();

    $settings->addInfo('untracked', $this->getUniqueId());
  }

  /**
   * Load a configuration from the DataStore and save it into the ActiveStore.
   * This function is called from iterator().
   *
   * @see iterate()
   */
  public function import(ConfigIteratorSettings &$settings) {
    $this->loadFromStorage();
    $this->saveToActiveStore($settings);

    if ($settings->getSetting('start_tracking')) {
      $this->saveToStaging();
    }
  }

  /**
   * Revert a configuration from the staging area and save it into the
   * ActiveStore. This function is called from iterator().
   *
   * @see iterate()
   */
  public function revert(ConfigIteratorSettings &$settings) {
    $this->loadFromStaging();
    $this->saveToActiveStore($settings);
  }

  /**
   * Save a configuration into the ActiveStore.
   *
   * Each configuration should implement their own version of saveToActiveStore.
   * I.e, content types should call to node_save_type(), variables should call
   * to variable_set(), etc.
   */
  public function saveToActiveStore(ConfigIteratorSettings &$settings) {
    // Override
  }

  public function export(ConfigIteratorSettings &$settings) {
    $this->build();

    // Save the configuration into a file.
    $this->storage
            ->setData($this->data)
            ->setKeysToExport($this->getKeysToExport())
            ->setDependencies(drupal_map_assoc(array_keys($this->getDependencies())))
            ->setOptionalConfigurations(drupal_map_assoc(array_keys($this->getOptionalConfigurations())))
            ->setModules(array_keys($this->getRequiredModules()))
            ->save();

    if ($settings->getSetting('start_tracking')) {
      $this->buildHash();
      $settings->addInfo('hash', $this->getHash());
      $this->saveToStaging();
    }

    // Add the current config as an exported item
    $settings->addInfo('exported', $this->getUniqueId());
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
    $this->built = TRUE;
    return $this;
  }

  /**
   * Create a unique hash for this configuration based on the data,
   * dependencies, optional configurations and modules required to use this
   * configuration. Use getHash() after call this function.
   *
   * @return NULL
   */
  public function buildHash() {
    $this->storage
        ->setData($this->data)
        ->setKeysToExport($this->getKeysToExport())
        ->setDependencies(drupal_map_assoc(array_keys($this->getDependencies())))
        ->setOptionalConfigurations(drupal_map_assoc(array_keys($this->getOptionalConfigurations())))
        ->setModules(array_keys($this->getRequiredModules()));
    $this->storage->getDataToSave();
    $this->setHash($this->storage->getHash());
    return $this;
  }

  public function getStatus($human_name = TRUE) {
    $staging_hash = db_select('configuration_staging', 'cs')
                      ->fields('cs', array('hash'))
                      ->condition('component', $this->getComponent())
                      ->condition('identifier', $this->getIdentifier())
                      ->execute()
                      ->fetchField();
    if (empty($staging_hash)) {
      return $human_name ? t('ActiveStore only') : CONFIGURATION_ACTIVESTORE_ONLY;
    }
    else {
      if ($this->getHash() == $staging_hash) {
        return $human_name ? t('In Sync') : CONFIGURATION_IN_SYNC;
      }
      else {
        return $human_name ? t('Overriden') : CONFIGURATION_ACTIVESTORE_OVERRIDEN;
      }
    }

  }

  /**
   * Provides the differences between two configurations of the same component.
   */
  public function diff() {

  }

  /**
   * Returns an unique identifier for this configuration. Usually something like
   * 'content_type.article' where content_type is the component of the
   * configuration and 'article' is the identifier of the configuration for the
   * given component.
   *
   * @return string
   */
  public function getUniqueId() {
    return $this->getComponent() . '.' . $this->getIdentifier();
  }

  /**
   * Returns the component that this configuration represent.
   */
  public function getComponent() {
  }

  /**
   * Returns the all the components that this handler can handle.
   */
  static public function supportedComponents() {
    return array();
  }

  /**
   * Returns the human name of the given component.
   */
  static public function getComponentHumanName($component, $plural = FALSE) {
    return t('UNDEFINED: ') . $component;
  }

  /**
   * Determine if the handler can be used. Usually this function should check
   * that modules required to handle the configuration are installed.
   *
   *  @return boolean
   *    TRUE if the handler is active and can be used. FALSE otherwise.
   */
  public static function isActive() {
    return TRUE;
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
   * Returns the hash of the configuration object.
   */
  public function getHash() {
    return $this->hash;
  }

  /**
   * Set the hash for this configuration.
   */
  public function setHash($value) {
    $this->hash = $value;
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
    $handlers = ConfigurationManagement::getConfigurationHandler();
    $stack = array();
    // Include this configuration to the stack to avoid add it again
    // in a circular dependency cycle
    $stack[$this->getUniqueId()] = TRUE;

    foreach ($handlers as $component => $handler) {
      $handler::alterDependencies($this, $stack);
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
                        ->condition('component', $this->getComponent())
                        ->condition('identifier', $this->getIdentifier())
                        ->fetchField();

      if (!$exists) {
        // If not exists in the database, look into the config:// directory.
        $storage_system = static::getStorageSystem();
        $file_exists = $storage_system::configFileExists($this->getComponent(), $this->getIdentifier());
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
      foreach ($files as $id => $file) {
        if ($file->type == 'module' && empty($file->info['hidden'])) {
          $modules[$id] = $file;
        }
      }
    }

    return $modules;
  }

  /**
   * Print the configuration as plain text formatted to use in a tar file.
   *
   * @param  ConfigIteratorSettings $settings
   * @see iterate()
   */
  protected function printRaw(ConfigIteratorSettings &$settings) {
    $this->build();

    // Save the configuration into a file.
    $file_content = $this->storage
                      ->setData($this->data)
                      ->setKeysToExport($this->getKeysToExport())
                      ->setDependencies(drupal_map_assoc(array_keys($this->getDependencies())))
                      ->setOptionalConfigurations(drupal_map_assoc(array_keys($this->getOptionalConfigurations())))
                      ->setModules(array_keys($this->getRequiredModules()))
                      ->getDataToSave();

    $this->buildHash();
    $settings->addInfo('hash', $this->getHash());

    $file_name = $this->storage->getFileName() ;
    $settings->addInfo('exported', $this->getUniqueId());
    $settings->addInfo('exported_files', $file_name);

    print static::createTarContent("configuration/{$file_name}", $file_content);
  }

  /**
   * This function will exectute a callback function over all the configurations
   * objects that it process.
   *
   * @param  ConfigIteratorSettings $settings
   *   A ConfigIteratorSettings instance that specifies, which is the callback
   *   to execute. If dependencies and optional configurations should be
   *   processed too, and storage the cache of already processed configurations.
   *
   * @see importToActiveStore()
   * @see exportToDataStore()
   * @see revertActiveStore()
   * @see discoverRequiredModules()
   */
  function iterate(ConfigIteratorSettings &$settings) {
    $callback = $settings->getCallback();
    $build_callback = $settings->getBuildCallback();

    if ($settings->alreadyProcessed($this)) {
      return;
    }

    // First proccess requires the dependencies that have to be processed before
    // load the current configuration.
    if ($settings->processDependencies()) {
      foreach ($this->getDependencies() as $dependency => $config_dependency) {

        // In some callbacks, the dependencies storages the full config object
        // other simply use a plain string. If the object is available, use
        // that version.
        if (is_object($config_dependency)) {
          $config = $config_dependency;
        }
        else {
          $config = $settings->getFromCache($dependency);
          if (!$config) {
            $config = ConfigurationManagement::createConfigurationInstance($dependency);
          }
        }
        $config->{$build_callback}();
        $config->iterate($settings);
      }
    }

    if ($settings->alreadyProcessed($this)) {
      return;
    }

    // Now, after proccess the dependencies, proccess the current configuration.
    $this->{$callback}($settings);
    $settings->addToCache($this);

    // After proccess the dependencies and the current configuration, proccess
    // the optionals.
    if ($settings->processOptionals()) {
      foreach ($this->getOptionalConfigurations() as $optional => $optional_config) {
        $config = $settings->getFromCache($optional);

        // In some callbacks, the optionals storages the full config object
        // other simply use a plain string. If the object is available, use
        // that version.
        if (is_object($optional_config)) {
          $config = $optional_config;
        }
        else {
          if (!$config) {
            $config = ConfigurationManagement::createConfigurationInstance($optional);
          }
        }
        $config->{$build_callback}();
        $config->iterate($settings);
      }
    }
  }
}
