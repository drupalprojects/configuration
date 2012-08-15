<?php

/**
 * @file
 * Definition of Drupal\configuration\Config\Configuration.
 */

namespace Drupal\configuration\Config;

use Drupal\configuration\Storage\StoragePhp;

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
    $this->storage = new StoragePhp();
    $this->storage->setFileName(static::$component . '.' . $identifier);
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
   * Returns a that manages the configurations for the given component.
   */
  public static function getConfigurationHandler($component) {
    $handlers = self::getAllConfigurationHandlers();
    return  '\\' . $handlers[$component]['namespace'] . '\\' . $handlers[$component]['handler'];
  }

  /**
   * Loads all the non imported configurations.
   */
  public static function importAllNewConfigurations() {

    $path = drupal_realpath('config://');
    $ext = StoragePhp::$file_extension;
    $look_for = '/' . static::$component . '\..*' . $ext . '$/';

    $files = file_scan_directory($path, $look_for);

    $storage = new StoragePhp();
    foreach ($files as $file) {
      $storage->reset();
      // Avoid namespace issues.
      $file_array = (array)$file;
      $filename = $file_array['name'];
      $storage
        ->setFileName($filename)
        ->load();

      $data = $storage->getData();
      $dependencies = $storage->getDependencies();
      $modules = $storage->getModules();

      // Obtain the identifier of the configuration based on the file name.
      $identifier = substr($file_array['name'], strpos($file_array['name'], '.') + 1);

      // Create a configuration object, and save it into the staging area.
      $config = new static($identifier);
      $config
        ->setData($data)
        ->setDependencies($dependencies)
        ->setModules($modules)
        ->saveToStaging();

      unset($config);
      // @TODO Build the import order based on the dependencies.
      // @TODO initialize a config object and call to $this->import($data);
    }
  }

  public static function defaultHook() {
    $configurations = db_select('configuration_staging', 'cs')
                        ->fields('cs', array('identifier', 'data'))
                        ->condition('component', static::$component)
                        ->execute();
    $return = array();
    foreach ($configurations as $configuration) {
      $return[$configuration->identifier] = unserialize($configuration->data);
    }
    return $return;
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
      'dependencies' => serialize(array()),
      'modules' => serialize(array()),
    );
    db_insert('configuration_staging')->fields($fields)->execute();
  }

  /**
   * Import the configuration into the ActiveStore.
   */
  public function importToActiveStore() {
    // This method must be overrided by children classes.
    $this->data = $this->storage->load($this->data)->getData();
    // Perform the neccesary actions here.
    return $this;
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
  public function exportToDataStore($export_dependencies = TRUE) {
    // Make sure the configuration is built.
    $this->build();

    $dependencies = array();
    if ($export_dependencies) {
      foreach ($this->dependencies as $config_dependency) {
        $dependencies[] = $config_dependency->getComponent() . '.' . $config_dependency->getIdentifier();
      }
    }

    // Save the configuration into a file.
    $this->storage
            ->setData($this->data)
            ->setKeysToExport($this->getKeysToExport())
            ->setDependencies($dependencies)
            ->setModules($this->required_modules)
            ->save();

    // Also, save the configuration in the database
    $this->saveToStaging();

    if (!empty($export_dependencies)) {
      foreach ($this->dependencies as $config) {
        $config->exportToDataStore($export_dependencies);
      }
    }
    return $this;
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
   * Add a new dependency for this configuration.
   */
  public function addToDependencies(Configuration $config) {
    if (!isset($this->dependencies)) {
      $this->dependencies = array();
    }
    $this->dependencies[] = $config;
    return $this;
  }

  /**
   * Returns the list of dependencies of this configuration
   */
  public function getDependencies() {
    return array();
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
    $handlers = self::getAllConfigurationHandlers();
    $stack = array();
    // Include this configuration to the stack to avoid add it again
    // in a circular dependency cycle
    $stack[$this->getComponent() . '.' . $this->getIdentifier()] = TRUE;

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
   *   The object that requires all the dependecies.
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
    foreach ($this->dependecies as $dependency) {
      // First, look for the dependency in the staging table.
      $exists = db_select('configuration_staging', 'cs')
                        ->fields('cs', array('identifier'))
                        ->condition('component', static::$component)
                        ->condition('identifier', $this->getIdentifier())
                        ->fetchField();

      if (!$exists) {
        // If not exists in the database, look into the config:// directory.
        $file_exists = StoragePHP::configFileExists(static::$component, $this->getIdentifier());
        if (!$file_exists) {
          return FALSE;
        }
      }
    }
  }
}
