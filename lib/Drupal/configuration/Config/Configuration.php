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
  protected $component;

  /**
   * The identifier that identifies to the component, usually the machine name.
   */
  protected $identifier;

  /**
   * The required modules to load this configuration.
   */
  protected $required_modules;

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
   * An object to save and load the data from a persistent medium.
   */
  protected $storage;

  public function __construct($component, $identifier) {
    $this->component = $component;
    $this->identifier = $identifier;

    $this->storage = new StoragePhp();
    $this->storage->setFileName($component . '.' . $identifier . '.inc');
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
    $files = file_scan_directory($path, '/configuration.' . $this->component . '.*\.' . $ext . '$/');

    $storage = new StoragePhp();
    foreach ($files as $file) {
      $storage
        ->setFileName($file->filename)
        ->load();
      $data = $storage->getData();
      $dependencies = $storage->getDependencies();

      // @TODO Build the import order based on the dependencies.
      // @TODO initialize a config object and call to $this->import($data);
    }
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
   * Export the data to the DataStore.
   */
  public function exportToDataStore($export_dependencies = TRUE) {

    $dependencies = array();
    if ($export_dependencies) {
      foreach ($this->dependencies as $config_dependency) {
        $dependencies[] = $config_dependency->getComponent() . '.' . $config_dependency->getIdentifier();
      }
    }

    $this->storage
            ->setData($this->data)
            ->setDependencies($dependencies)
            ->save();

    if (!empty($export_dependencies)) {
      foreach ($this->dependencies as $config) {
        $config->exportToDataStore($export_dependencies);
      }
    }
    return $this;
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
    // This method must be overrided by children classes.
    if ($include_dependencies) {
      $this->findDependencies();
    }
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
  public function getComponent() {
    return $this->component;
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
   * Returns the name of the required_modules that provide this configuration.
   */
  public function getModule() {
    return $this->required_modules;
  }

  /**
   * Returns the list of dependencies of this configuration
   */
  public function getDependencies() {
    return array();
  }

  /**
   * Set the component name of this configuration
   */
  public function setComponent($value) {
    $this->name = $value;
  }

  /**
   * Set the component identifier of this configuration
   */
  public function setIdentifier($value) {
    $this->identifier = $value;
  }

  /**
   * Set the name of the required_modules that provide this configuration.
   */
  public function setModule($list) {
    $this->required_modules = $list;
  }

  /**
   * Return TRUE if this is the configuration for an entity.
   */
  public function configForEntity() {
    return FALSE;
  }

  /**
   * Set the depedent configuration objects required to load this object.
   */
  public function setDependencies($value) {
    $this->dependencies = $value;
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
    // @TODO: Check config dependencies too
  }
}
