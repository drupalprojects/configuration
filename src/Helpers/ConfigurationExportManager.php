<?php

/**
 * @file ConfigurationExportManager.php handles the export of configurations.
 */

namespace Configuration\Helpers;

use Configuration\Configuration;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use mdagostino\DependencyResolver\DependencyResolver;
use PhpCollection\Map;
use Camspiers\JsonPretty\JsonPretty;

class ConfigurationExportManager {

  protected $configuration_manager;
  protected $configurations_to_export;
  protected $fs;
  protected $modules;
  protected $exported;
  protected $fast_export;

  public function __construct($configuration_manager) {
    $this->configuration_manager = $configuration_manager;
    $this->configurations_to_export = new Map();
    $this->fs = new Filesystem();
    $this->reset();
  }

  public function reset() {
    $this->created_directories = array();
    $this->exported = array();
    $this->modules = array();
    $this->configurations_to_export->clear();
    $this->created_directories = array();
  }

  public function defineItemsToExport($identifier_list) {
    $this->configurations_to_export->setAll($identifier_list);
    return $this;
  }

  public function exportAll() {
    $list = array();
    foreach ($this->configuration_manager->getHandlersTypes() as $type) {
      $identifiers = $this->configuration_manager->getHandlerFromType($type)->getIdentifiers();
      foreach ($identifiers as $id => $label) {
        $list[$type . '.' . $id] = TRUE;
      }
    }
    $this->defineItemsToExport($list)->export();
  }

  /**
   * Export the configuration of the site into the filesystem.
   */
  public function export() {

    if ($this->configuration_manager->settings()->get('export.batch')) {
      // @TODO: implement batch exports.
    }
    else {

      $this->fast_export = $this->configuration_manager->settings()->get('export.fast_export');

      $this->configuration_manager->cache()->reset();

      // Based on the list of selected dependencies to export, build the full
      // list of configurations searching for dependencies of dependecies and
      // parts of all the detected configurations.
      $this->searchComplementaryConfigurations();

      // Remove the excluded configurations.
      foreach ($this->configuration_manager->settings()->get('export.exclude') as $exclude) {
        if ($this->configuration_manager->cache()->contains($exclude)) {
          $this->configuration_manager->cache()->remove($exclude);
        }
      }

      // Check for Circular Dependency and Missing configurations.
      $this->checkForCircularDependecy();

      // Finally, create all the files and the configuration.json file.
      $this->dumpFiles();
    }
  }


  public function searchComplementaryConfigurations() {

    $already_proccessed = array();
    $not_procesed_yet = $this->configurations_to_export->keys();

    while (!empty($not_procesed_yet)) {
      $identifier = array_pop($not_procesed_yet);
      if (empty($already_proccessed[$identifier])) {

        // Load the current configuration
        $handler = $this->configuration_manager->getHandlerFromIdentifier($identifier);
        $configuration = $handler->loadFromDatabase($identifier);

        // Check if there are some alter proccess to apply to the recently
        // loaded configuration.
        $this->alterConfiguration($configuration);

        // Search for available parts already defined for this configuration.
        $parts = $this->configuration_manager->getReferences($identifier);
        $configuration->setParts($parts);

        // Remove the current configuration from the list of pending for
        // proccess parts.
        $this->configuration_manager->clearReferences($identifier);

        // Add the loaded configuration to the cache.
        $this->configuration_manager->cache()->set($configuration);
        $already_proccessed[$identifier] = TRUE;

        // Add its dependencies to the list of not proccesed
        foreach ($configuration->getDependencies() as $dependency) {
          if (empty($already_proccessed[$dependency])) {
            $not_procesed_yet[] = $dependency;
          }
        }

        // Add its parts to the list of not proccesed
        foreach ($configuration->getParts() as $part) {
          if (empty($already_proccessed[$part])) {
            $not_procesed_yet[] = $part;
          }
        }
      }
    }

    // Complete dependency map.
    $this->configuration_manager->applyPendingOperations();
  }

  public function alterConfiguration(Configuration $configuration) {
    $alter = $this->configuration_manager->settings()->get('alter');
    if (!empty($alter) && !empty($alter[$configuration->getIdentifier()])) {
      foreach ($alter[$configuration->getIdentifier()] as $processor_name => $properties) {
        $processor = $this->configuration_manager->getProccesor($processor_name);
        $processor->apply($configuration, $properties);
      }
    }
  }

  public function checkForCircularDependecy() {
    $resolver = new DependencyResolver();
    foreach ($this->configuration_manager->cache()->getAll() as $configuration) {
      $resolver->addComponent($configuration->getIdentifier(), $configuration->getDependencies());
    }

    try {
      // The list of Configurations sorted by order of import.
      $ordered = $resolver->resolveDependencies();
    }
    catch (Exception $e) {
      if ($e->getMessage() == "Circular dependency detected") {
        return FALSE;
      }
      if (strpos($e->getMessage(), "There is a component not defined") !== FALSE) {
        return FALSE;
      }
    }
    return $ordered;
  }


  public function dumpFiles() {
    $config_path = drupal_realpath('public://' . rtrim($this->configuration_manager->settings()->get('export.path'), '/'));

    $this->createDirectory($config_path);

    $export_format = $this->configuration_manager->settings()->get('export.format');
    $configurations = $this->configuration_manager->cache()->getAll();

    $step_level = count($configurations) / 10;
    $steps_completed = 0;
    $export_counter = 0;
    foreach ($configurations as $configuration) {
      $this->dumpConfiguration($configuration, $config_path, $export_format);
      $export_counter++;
      if ($export_counter > $step_level) {
        $steps_completed += 10;
        echo "$steps_completed% completed\n";
        $export_counter = 0;
      }
    }

    $this->createConfigurationsFile($config_path);
  }

  protected function createDirectory($path) {
    if (!in_array($path, $this->created_directories)) {
      try {
        $this->fs->mkdir($path);
        $this->created_directories[] = $path;
      }
      catch (IOExceptionInterface $e) {
        echo "The directory for configs could not be created: ". $e->getPath();
      }
    }
  }

  protected function dumpConfiguration(Configuration $configuration, $config_path, $export_format) {
    $handler = $this->configuration_manager->getHandler($configuration);
    $relative_path = $handler->getExportPath($configuration);
    $path = $config_path . '/' . $relative_path;
    $this->createDirectory($path);

    $file_path = $path . $configuration->getIdentifier() . '.' . $export_format;
    $export = $handler->export($configuration, $export_format);
    if (!isset($this->exported[$configuration->getGroup()])) {
      $this->exported[$configuration->getGroup()] = array();
    }
    $this->modules = array_merge($this->modules, $configuration->getModules(), $configuration->getAdditionalModules());
    $this->exported[$configuration->getGroup()][$configuration->getIdentifier()] = array(
      'hash' => sha1($export),
      'path' => $relative_path . $configuration->getIdentifier() . '.' . $export_format,
    );

    //echo "$file_path\n";
    if ($this->fast_export) {
      file_put_contents($file_path, $export);
    }
    else {
      $this->fs->dumpFile($file_path, $export);
    }
  }

  protected function createConfigurationsFile($config_path) {
    $jsonPretty = new JsonPretty;
    $configurations_list = new \StdClass;

    ksort($this->exported);
    $this->modules = array_unique($this->modules);
    sort($this->modules);
    foreach (array_keys($this->exported) as $key) {
      ksort($this->exported[$key]);
    }

    $configurations_list->configurations = (object)$this->exported;
    $configurations_list->modules = array_values($this->modules);
    $this->fs->dumpFile($config_path . '/configurations.json', $jsonPretty->prettify($configurations_list, JSON_UNESCAPED_SLASHES, '  '));
  }

  function getModules() {
    return $this->modules;
  }

  function getExported() {
    return $this->exported;
  }

}
