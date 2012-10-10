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

  /**
   * The stream to use while importing and exporting configurations.
   *
   * @var string
   */
  static protected $stream = 'config://';

  public function __construct($identifier) {
    $this->identifier = $identifier;
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

  /**
   * Returns a Storage Object ready to load or write configurations from the
   * disk.
   */
  static protected function getStorageInstance() {
    $storage = static::getStorageSystem();
    $return = new $storage();
    $return::setStream(static::$stream);
    return $return;
  }

  /**
   * Returns the current stream used to import and export configurations.
   * Default value is config://
   *
   * @return string
   */
  public static function getStream() {
    return static::$stream;
  }

  /**
   * Set the stream to use while importing and exporting configurations.
   *
   * @param string $stream
   */
  public static function setStream($stream) {
    static::$stream = $stream;
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

  /**
   * Returns a list of modules required to import the configurations indicated
   * in $list.
   *
   * @param  array   $list
   *   The list of components that have to will be imported.
   * @param  boolean $include_dependencies
   *   If TRUE, modules required to load the dependencies of each configuration
   *   dependency will be returned too.
   * @param  boolean $include_optionals
   *   If TRUE, modules required to load the optionals configurations of each
   *   configuration will be returned too.
   * @return ConfigIteratorSettings
   *   A ConfigIteratorSettings object that contains the required modules to
   *   install and the modules missing.
   */
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
   * Includes a record of each configuration tracked in the
   * configuration_staging table and export the configurations to the DataStore.
   *
   * @param  array   $list
   *   The list of components that have to will be tracked.
   * @param  boolean $track_dependencies
   *   If TRUE, dependencies of each proccessed configuration will be tracked
   *   too.
   * @param  boolean $track_optionals
   *   If TRUE, optionals configurations of each proccessed configuration will
   *   be tracked too.
   * @return ConfigIteratorSettings
   *   An ConfigIteratorSettings object that contains the tracked
   *   configurations.
   */
  static public function startTracking($list = array(), $track_dependencies = TRUE, $track_optionals = TRUE) {
    return static::exportToDataStore($list, $track_dependencies, $track_optionals, TRUE);
  }

  /**
   * Removes a record of each configuration that is not tracked anymore and
   * deletes the configuration file in the DataStore.
   *
   * @param  array   $list
   *   The list of components that have to will be tracked.
   * @param  boolean $track_dependencies
   *   If TRUE, dependencies of each proccessed configuration will not be
   *   tracked anymore.
   * @param  boolean $track_optionals
   *   If TRUE, optionals configurations of each proccessed configuration will
   *   not be tracked anymore.
   * @return ConfigIteratorSettings
   *   An ConfigIteratorSettings object that contains configurations that are
   *   not tracked anymore.
   */
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

    //@TODO: Delete the file from the DataStore.

    return $settings;
  }

  /**
   * Removes the configuration record from the configuration_staging table for
   * the current configuration.
   */
  public function removeFromStaging(ConfigIteratorSettings &$settings) {
    db_delete('configuration_staging')
      ->condition('component', static::$component)
      ->condition('identifier', $this->getIdentifier())
      ->execute();

    $settings->addInfo('untracked', $this->getUniqueId());
  }

  /**
   * Loads the configuration from the DataStore into the ActiveStore.
   *
   * @param  array   $list
   *   The list of components that have to will be imported.
   * @param  boolean $import_dependencies
   *   If TRUE, dependencies of each proccessed configuration will be imported
   *   too.
   * @param  boolean $import_optionals
   *   If TRUE, optionals configurations of each proccessed configuration will
   *   be imported too.
   * @param  boolean $start_tracking
   *   If TRUE, after import the configuration, it will be also tracked.
   * @return ConfigIteratorSettings
   *   An ConfigIteratorSettings object that contains the imported
   *   configurations.
   */
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
   * Revert configurations configurations of the ActiveStore using the data
   * of the staging area.
   *
   * @param  array   $list
   *   The list of components that have to will be restored.
   * @param  boolean $import_dependencies
   *   If TRUE, dependencies of each proccessed configuration will be restored
   *   too.
   * @param  boolean $import_optionals
   *   If TRUE, optionals configurations of each proccessed configuration will
   *   be restored too.
   * @return ConfigIteratorSettings
   *   An ConfigIteratorSettings object that contains the restored
   *   configurations.
   */
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

  /**
   * Export the configuration from the ActiveStore to the DataStore.
   *
   * @param  array   $list
   *   The list of components that have to will be exported.
   * @param  boolean $import_dependencies
   *   If TRUE, dependencies of each proccessed configuration will be exported
   *   too.
   * @param  boolean $import_optionals
   *   If TRUE, optionals configurations of each proccessed configuration will
   *   be exported too.
   * @param  boolean $star_tracking
   *   If TRUE, after export the configuration, it will be also tracked.
   * @return ConfigIteratorSettings
   *   An ConfigIteratorSettings object that contains the exported
   *   configurations.
   */
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
      $this->buildHash();
      $settings->addInfo('hash', $this->getHash());
      $this->saveToStaging();
    }

    // Add the current config as an exported item
    $settings->addInfo('exported', $this->getUniqueId());
  }

  /**
   * Returns a list of configurations that are currently being tracked.
   *
   * @return array
   */
  static public function trackedConfigurations() {
    $tracked = db_select('configuration_staging', 'cs')
                  ->fields('cs', array('component', 'identifier', 'hash'))
                  ->execute()
                  ->fetchAll();
    // Prepare the array to return
    $handlers = configuration_configuration_handlers();
    $return = array();
    foreach ($handlers as $component => $handler) {
      $return[$component] = array();
    }

    foreach ($tracked as $object) {
      // Only return tracked Configurations for supported components.
      if (isset($return[$object->component])) {
        $return[$object->component][$object->identifier] = $object->hash;
      }
    }
    return $return;
  }

  /**
   * Returns a list of configurations that are not currently being tracked.
   *
   * @return array
   */
  static public function nonTrackedConfigurations() {
    $handlers = configuration_configuration_handlers();

    $tracked = static::trackedConfigurations();
    $non_tracked = array();

    foreach ($handlers as $component => $handler) {
      $identifiers = configuration_get_identifiers($component);
      foreach ($identifiers as $identifier) {
        if (empty($tracked[$component]) || empty($tracked[$component][$identifier])) {
          $id = $component . '.' . $identifier;
          $non_tracked[$component][$identifier] = $id;
        }
      }
    }
    return $non_tracked;
  }

  /**
   * Returns a list of configurations available in the site without distinction
   * of tracked and not tracked.
   *
   * @return array
   */
  static public function allConfigurations() {
    $handlers = configuration_configuration_handlers();

    $tracked = static::trackedConfigurations();
    $all = array();

    foreach ($handlers as $component => $handler) {
      $identifiers = configuration_get_identifiers($component);
      foreach ($identifiers as $identifier) {
        $id = $component . '.' . $identifier;
        if (!empty($tracked[$component][$identifier])) {
          // Set the hash for the tracked configurations
          $all[$component][$identifier] = $tracked[$component][$identifier];
        }
        else {
          // Set FALSE for the non tracked configurations
          $all[$component][$identifier] = FALSE;
        }
      }
    }
    return $all;
  }

  /**
   * This function save into config://tracked.inc file the configurations that
   * are currently tracked.
   */
  static public function updateTrackingFile() {
    $tracked = static::trackedConfigurations();

    $file = array();
    foreach ($tracked as $component => $list) {
      foreach ($list as $identifier => $hash) {
        $file[$component . '.' . $identifier] = $hash;
      }
    }
    $file_content = "<?php\n\n";
    $file_content .= "// This file contains the current being tracked configurations.\n\n";
    $file_content .= '$tracked = ' . var_export($file, TRUE) . ";\n";
    file_put_contents(static::$stream . 'tracked.inc', $file_content);
  }

  /**
   * Returns a list of files that are listed in the config://tracked.inc file.
   */
  static public function readTrackingFile() {
    if (file_exists(static::$stream . 'tracked.inc')) {
      $file_content = drupal_substr(file_get_contents(static::$stream . 'tracked.inc'), 6);
      @eval($file_content);
      return $tracked;
    }
    return array();
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
                      ->condition('component', static::$component)
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
    return static::$component . '.' . $this->getIdentifier();
  }

  /**
   * Returns the component that this configuration represent.
   */
  static public function getComponent() {
    return static::$component;
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
      foreach ($files as $id => $file) {
        if ($file->type == 'module' && empty($file->info['hidden'])) {
          $modules[$id] = $file;
        }
      }
    }

    return $modules;
  }

  /**
   * Download the entire configuration packaged up into tar file
   */
  public static function exportAsTar($list = array(), $export_dependencies = TRUE, $export_optionals = TRUE) {
    $settings = new ConfigIteratorSettings(
      array(
        'build_callback' => 'build',
        'callback' => 'printRaw',
        'process_dependencies' => $export_dependencies,
        'process_optionals' => $export_optionals,
        'info' => array(
          'exported' => array(),
          'exported_files' => array(),
          'hash' => array(),
        )
      )
    );

    $filename = 'configuration.' . time() . '.tar';

    // Clear out output buffer to remove any garbage from tar output.
    if (ob_get_level()) {
      ob_end_clean();
    }

    drupal_add_http_header('Content-type', 'application/x-tar');
    drupal_add_http_header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    drupal_send_headers();

    foreach ($list as $component) {
      list($component_name, $identifier) = explode('.', $component, 2);
      $handler = Configuration::getConfigurationHandler($component_name);
      $config = new $handler($identifier);

      // Make sure the object is built before start to iterate on its
      // dependencies.
      $config->build();
      $config->iterate($settings);
    }

    $exported = $settings->getInfo('exported');
    $file_content = "<?php\n\n";
    $file_content .= "// This file contains the list of configurations contained in this package.\n\n";
    $file_content .= '$configurations = ' . var_export($exported, TRUE) . ";\n";

    print static::createTarContent("configuration/configurations.inc", $file_content);

    print pack("a1024", "");
    exit;
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
   * Import configurations from a Tar file.
   *
   * @param  StdClass $file
   *   A file object.
   * @param  boolean $start_tracking
   *   If TRUE, all the configurations provided in the Tar file will be imported
   *   and automatically tracked.
   *
   * @return ConfigIteratorSettings
   *   An ConfigIteratorSettings object that contains the imported
   *   configurations.
   */
  static public function importToActiveStoreFromTar($uri, $start_tracking = FALSE) {
    $path = 'temporary://';

    $archive = archiver_get_archiver($uri);
    $files = $archive->listContents();
    foreach ($files as $filename) {
      if (is_file($path . $filename)) {
        file_unmanaged_delete($path . $filename);
      }
    }

    $config_temp_path = 'temporary://' . 'config-tmp-' . time();
    $archive->extract(drupal_realpath($config_temp_path));

    $file_content = drupal_substr(file_get_contents($config_temp_path . '/configuration/configurations.inc'), 6);
    @eval($file_content);

    static::$stream = $config_temp_path . '/configuration/';

    $settings = static::importToActiveStore($configurations, FALSE, FALSE, $start_tracking);

    static::deteleTempConfigDir($config_temp_path);

    return $settings;
  }

  static protected function deteleTempConfigDir($dir, $force = FALSE) {
    // Allow to delete symlinks even if the target doesn't exist.
    if (!is_link($dir) && !file_exists($dir)) {
      return TRUE;
    }
    if (!is_dir($dir)) {
      if ($force) {
        // Force deletion of items with readonly flag.
        @chmod($dir, 0777);
      }
      return unlink($dir);
    }
    foreach (scandir($dir) as $item) {
      if ($item == '.' || $item == '..') {
        continue;
      }
      if ($force) {
        @chmod($dir, 0777);
      }
      if (!static::deteleTempConfigDir($dir . '/' . $item, $force)) {
        return FALSE;
      }
    }
    if ($force) {
      // Force deletion of items with readonly flag.
      @chmod($dir, 0777);
    }
    return rmdir($dir);
  }

  /**
   * Tar creation function. Written by dmitrig01.
   *
   * @param $name
   *   Filename of the file to be tarred.
   * @param $contents
   *   String contents of the file.
   *
   * @return
   *   A string of the tar file contents.
   */
  protected static function createTarContent($name, $contents) {
    $tar = '';
    $binary_data_first = pack("a100a8a8a8a12A12",
      $name,
      '100644 ', // File permissions
      '   765 ', // UID,
      '   765 ', // GID,
      sprintf("%11s ", decoct(drupal_strlen($contents))), // Filesize,
      sprintf("%11s", decoct(REQUEST_TIME)) // Creation time
    );
    $binary_data_last = pack("a1a100a6a2a32a32a8a8a155a12", '', '', '', '', '', '', '', '', '', '');

    $checksum = 0;
    for ($i = 0; $i < 148; $i++) {
      $checksum += ord(drupal_substr($binary_data_first, $i, 1));
    }
    for ($i = 148; $i < 156; $i++) {
      $checksum += ord(' ');
    }
    for ($i = 156, $j = 0; $i < 512; $i++, $j++) {
      $checksum += ord(drupal_substr($binary_data_last, $j, 1));
    }

    $tar .= $binary_data_first;
    $tar .= pack("a8", sprintf("%6s ", decoct($checksum)));
    $tar .= $binary_data_last;

    $buffer = str_split($contents, 512);
    foreach ($buffer as $item) {
      $tar .= pack("a512", $item);
    }
    return $tar;
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
            list($component_name, $identifier) = explode('.', $dependency, 2);
            $handler = Configuration::getConfigurationHandler($component_name);
            $config = new $handler($identifier);
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
            list($component_name, $identifier) = explode('.', $optional, 2);
            $handler = Configuration::getConfigurationHandler($component_name);
            $config = new $handler($identifier);
          }
        }
        $config->{$build_callback}();
        $config->iterate($settings);
      }
    }
  }
}
