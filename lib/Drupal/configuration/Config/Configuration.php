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

  /**
   * A hash that represent that sumarizes the configuration and can
   * be used to copare configurations.
   */
  protected $hash;

  /**
   * A boolean flag to indicate if the configuration object was already populated
   * from the ActiveStore, or from the DataStore.
   */
  protected $built;

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

  protected function loadFromStorage() {
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

  protected function loadFromStaging() {
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
      ->condition('component', static::$component)
      ->condition('identifier', $this->getIdentifier())
      ->execute();

    $fields = array(
      'component' => static::$component,
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

  static public function discoverRequiredModules($list = array(), $include_dependencies = TRUE, $include_optionals = TRUE) {
    $settings = new ConfigIteratorSettings(
      array(
        'build_callback' => 'loadFromStorage',
        'callback' => 'discoverModules',
        'process_dependencies' => $import_dependencies,
        'process_optionals' => $import_optionals,
        'info' => array(
          'modules' => array(),
          'modules_missing' => array(),
          'modules_to_install' => array(),
        )
      )
    );
    foreach ($list as $component) {
      list($component_name, $identifier) = explode('.', $component, 2);
      $handler = Configuration::getConfigurationHandler($component_name);
      $config = new $handler($identifier);

      // Make sure the object is built before start to iterate on its
      // dependencies.
      $config->loadFromStorage();
      $config->iterate($settings);
    }

    $missing = array();
    $to_install = array();
    foreach ($settings->getInfo('modules') as $module_name => $status) {
      if ($status == CONFIGURATION_MODULE_MISSING) {
        $missing[] = $module_name;
      }
      elseif ($status == CONFIGURATION_MODULE_TO_INSTALL) {
        $to_install[] = $module_name;
      }
    }
    $settings->setInfo('modules_to_install', array_unique($to_install));
    $settings->setInfo('modules_missing', array_unique($missing));

    return $settings;
  }

  public function discoverModules(ConfigIteratorSettings &$settings) {
    $this->loadFromStorage();
    $modules = $settings->getInfo('modules');
    $modules = array_merge($modules, $this->getRequiredModules());
    $settings->setInfo('modules', $modules);
  }

  static public function startTracking($list = array(), $track_dependencies = TRUE, $track_optionals = TRUE) {
    return static::exportToDataStore($list, $track_dependencies, $track_optionals, TRUE);
  }

  static public function stopTracking($list = array(), $stop_track_dependencies = TRUE, $stop_track_optionals = TRUE) {
    $settings = new ConfigIteratorSettings(
      array(
        'build_callback' => 'build',
        'callback' => 'removeFromStaging',
        'process_dependencies' => $stop_track_dependencies,
        'process_optionals' => $stop_track_optionals,
        'info' => array(
          'untracked' => array(),
        )
      )
    );

    foreach ($list as $component) {
      list($component_name, $identifier) = explode('.', $component, 2);
      $handler = Configuration::getConfigurationHandler($component_name);
      $config = new $handler($identifier);

      // Make sure the object is built before start to iterate on its
      // dependencies.
      $config->build();
      $config->iterate($settings);
    }

    return $settings;
  }

  public function removeFromStaging(ConfigIteratorSettings &$settings) {
    db_delete('configuration_staging')
      ->condition('component', static::$component)
      ->condition('identifier', $this->getIdentifier())
      ->execute();

    $settings->addInfo('untracked', $this->getUniqueId());
  }

  static public function importToActiveStore($list = array(), $import_dependencies = TRUE, $import_optionals = TRUE, $start_tracking = FALSE) {
    $settings = new ConfigIteratorSettings(
      array(
        'build_callback' => 'loadFromStorage',
        'callback' => 'import',
        'process_dependencies' => $import_dependencies,
        'process_optionals' => $import_optionals,
        'settings' => array(
          'start_tracking' => $start_tracking,
        ),
        'info' => array(
          'imported' => array(),
        )
      )
    );

    foreach ($list as $component) {
      list($component_name, $identifier) = explode('.', $component, 2);
      $handler = Configuration::getConfigurationHandler($component_name);
      $config = new $handler($identifier);

      // Make sure the object is built before start to iterate on its
      // dependencies.
      $config->loadFromStorage();
      $config->iterate($settings);
    }

    return $settings;
  }

  public function import(ConfigIteratorSettings &$settings) {
    $this->loadFromStorage();
    $this->saveToActiveStore($settings);

    if ($settings->getSetting('start_tracking')) {
      $this->saveToStaging();
    }
  }

  static public function revertActiveStore($list = array(), $revert_dependencies = TRUE, $revert_optionals = TRUE) {
    $settings = new ConfigIteratorSettings(
      array(
        'build_callback' => 'loadFromStaging',
        'callback' => 'revert',
        'process_dependencies' => $revert_dependencies,
        'process_optionals' => $revert_optionals,
        'info' => array(
          'imported' => array(),
        )
      )
    );

    foreach ($list as $component) {
      list($component_name, $identifier) = explode('.', $component, 2);
      $handler = Configuration::getConfigurationHandler($component_name);
      $config = new $handler($identifier);

      // Make sure the object is built before start to iterate on its
      // dependencies.
      $config->loadFromStaging();
      $config->iterate($settings);
    }

    return $settings;
  }

  public function revert(ConfigIteratorSettings &$settings) {
    $this->loadFromStaging();
    $this->saveToActiveStore($settings);
  }

  public function saveToActiveStore(ConfigIteratorSettings &$settings) {
    // Override
  }

  static public function exportToDataStore($list = array(), $export_dependencies = TRUE, $export_optionals = TRUE, $start_tracking = FALSE) {
    $settings = new ConfigIteratorSettings(
      array(
        'build_callback' => 'build',
        'callback' => 'export',
        'process_dependencies' => $export_dependencies,
        'process_optionals' => $export_optionals,
        'settings' => array(
          'start_tracking' => $start_tracking,
        ),
        'info' => array(
          'exported' => array(),
          'hash' => array(),
        )
      )
    );

    foreach ($list as $component) {
      list($component_name, $identifier) = explode('.', $component, 2);
      $handler = Configuration::getConfigurationHandler($component_name);
      $config = new $handler($identifier);

      // Make sure the object is built before start to iterate on its
      // dependencies.
      $config->build();
      $config->iterate($settings);
    }

    if ($start_tracking) {
      static::updateTrackingFile();
    }

    return $settings;
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
      $this->setHash($this->storage->getHash());
      $settings->addInfo('hash', $this->getHash());
      $this->saveToStaging();
    }

    // Add the current config as an exported item
    $settings->addInfo('exported', $this->getUniqueId());

  }

  /**
   * This function save into config://tracked.inc file the configurations that
   * are currently tracked.
   */
  static function updateTrackingFile() {
    $tracked = db_select('configuration_staging', 'cs')
                  ->fields('cs', array('component', 'identifier', 'hash'))
                  ->execute()
                  ->fetchAll();

    $file = array();
    foreach ($tracked as $config) {
      $file[$config->component . '.' . $config->identifier] = $config->hash;
    }
    $file_content = "<?php\n\n";
    $file_content .= "// This file contains the current being tracked configurations.\n\n";
    $file_content .= var_export($file, TRUE) . ";\n";
    file_put_contents('config://tracked.inc', $file_content);
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
      foreach (array_keys($this->getDependencies()) as $dependency) {
        $config = $settings->getFromCache($dependency);
        if (!$config) {
          list($component_name, $identifier) = explode('.', $dependency, 2);
          $handler = Configuration::getConfigurationHandler($component_name);
          $config = new $handler($identifier);
        }
        $config->{$build_callback}();
        $config->iterate($settings);
      }
    }

    // Now, after proccess the dependencies, proccess the current configuration.
    $this->{$callback}($settings);
    $settings->addToCache($this);

    // After proccess the dependencies and the current configuration, proccess
    // the optionals.
    if ($settings->processOptionals()) {
      foreach (array_keys($this->getOptionalConfigurations()) as $optional) {
        $config = $settings->getFromCache($optional);
        if (!$config) {
          list($component_name, $identifier) = explode('.', $optional, 2);
          $handler = Configuration::getConfigurationHandler($component_name);
          $config = new $handler($identifier);
        }
        $config->{$build_callback}();
        $config->iterate($settings);
      }
    }
  }
}
