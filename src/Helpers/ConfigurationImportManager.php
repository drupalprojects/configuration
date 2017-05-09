<?php

/**
 * @file ConfigurationExportManager.php handles the export of configurations.
 */

namespace Configuration\Helpers;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use mdagostino\DependencyResolver\DependencyResolver;
use PhpCollection\Map;
use Camspiers\JsonPretty\JsonPretty;

use Configuration\Events\ConfigurationModulesInstalled;

class ConfigurationImportManager {

  protected $configurations_to_import;
  protected $configuration_on_filesystem;
  protected $required_modules;
  protected $moduleManager;

  public function __construct($configuration_manager) {
    $this->configuration_manager = $configuration_manager;
    $this->configurations_to_import = new Map();
    $this->configuration_list = new ConfigurationSettingsList();
    $this->fs = new Filesystem();
    $this->reset();
  }

  public function reset() {
    $this->cache = array();
    $this->configuration_on_filesystem = array();
    $this->required_modules = array();
    $this->configuration_list->reset();
    $this->configurations_to_import->clear();
  }

  public function defineItemsToImport($identifier_list) {
    $this->configurations_to_import->setAll($identifier_list);
    return $this;
  }

  /**
   * Import configurations into the database.
   */
  public function import() {
    $this->readConfigurationsFile();

    $this->findMissingModules();

    $this->installMissingModules();

    // Register new handlers after install the modules.
    $this->configuration_manager->registerHandlers();

    $this->normalizeConfigurations();

    $ordered = $this->resolveDependencies();

    $import_always = !$this->configuration_manager->settings()->get('import.import_only_if_hash_changed');
    foreach ($ordered as $identifier) {
      $db_hash = $this->configuration_manager->getHash($identifier);
      $file_hash = $this->configuration_on_filesystem[$identifier]['hash'];
      if ($db_hash != $file_hash || $import_always)  {
        $configuration = $this->cache[$identifier];
        $handler = $this->configuration_manager->getHandlerFromIdentifier($identifier);
        echo "importing... " . $configuration->getIdentifier() . "\n";
        $handler->writeToDatabase($configuration);
      }
      else {
        echo "Skiping import of $identifier. It hash has not changed.";
      }
    }
  }

  protected function findMissingModules() {
    $this->required_modules = $this->configuration_list->getModules();
    $this->moduleManager = new DrupalModulesManager($this->configuration_manager);
    $modules = $this->moduleManager->findDependencies($this->required_modules);

    $missing = $this->moduleManager->missing();
    if ($missing) {
      throw new \Exception("The following modules are missing: " . implode(', ', $missing));
    }
  }

  protected function installMissingModules() {
    $installed = $this->moduleManager->enableModules();
    $event = new ConfigurationModulesInstalled($installed);
    $this->configuration_manager->dispatchEvent('modules_installed', $event);
  }

  protected function normalizeConfigurations() {
    $configurations_on_filesystem = array();
    // Foreach configuration defined in configurations.json:
    // - Check if it is duplicated in several groups.
    // - Reorganize it using the identifier as main key
    foreach ($this->configuration_list->getConfigurations() as $group => $configurations) {
      foreach ($configurations as $identifier => $info) {
        if (!empty($configurations_on_filesystem[$identifier])) {
          $older_group = $configurations[$identifier]['group'];
          throw new \Exception("$identifier that belogns to $group was already defined in $older_group");
        }

        $configurations_on_filesystem[$identifier] = array(
          'group' => $group,
          'hash' => $info['hash'],
          'path' => $info['path'],
        );
      }
    }
    $this->configuration_on_filesystem = $configurations_on_filesystem;

    // Free memory
    $this->configuration_list->reset();
  }

  protected function resolveDependencies() {
    // Validate that all the items defined to be imported can actually be imported
    // At the same time, start to discover dependencies and finally if everything
    // is ok, build the list of configurations to import sorted by import order.

    $resolver = new DependencyResolver();
    $exclude = $this->configuration_manager->settings()->get('import.exclude');


    $already_proccessed = array();
    $not_procesed_yet = $this->configurations_to_import->keys();

    $import_parts = $this->configuration_manager->settings()->get('import.import_parts');
    while (!empty($not_procesed_yet)) {
      $identifier = array_pop($not_procesed_yet);
      if (!isset($this->configuration_on_filesystem[$identifier])) {
        throw new \Exception("$identifier cannot be imported. There is no data defined for it in configurations.json");
      }
      else {
        $already_proccessed[$identifier] = TRUE;
        if (!isset($exclude[$identifier]) && !isset($this->cache[$identifier])) {
          $handler = $this->configuration_manager->getHandlerFromIdentifier($identifier);
          $path = $this->configuration_on_filesystem[$identifier]['path'];
          $configuration = $handler->import($path);

          // Apply proccesors on reverse mode.
          $this->alterConfiguration($configuration);

          $this->cache[$identifier] = $configuration;
          $resolver->addComponent($configuration->getIdentifier(), $configuration->getDependencies());

          // Add its dependencies to the list of not proccesed
          foreach ($configuration->getDependencies() as $dependency) {
            if (empty($already_proccessed[$dependency])) {
              $not_procesed_yet[] = $dependency;
            }
          }

          if ($import_parts) {
            // Add its dependencies to the list of not proccesed
            foreach ($configuration->getParts() as $part) {
              if (empty($already_proccessed[$part])) {
                $not_procesed_yet[] = $part;
              }
            }
          }
        }
      }
    }

    $ordered = array();
    try {
      // The list of Configurations sorted by order of import.
      $ordered = $resolver->resolveDependencies();
    }
    catch (\Exception $e) {
      if ($e->getMessage() == "Circular dependency detected") {
        return FALSE;
      }
      if (strpos($e->getMessage(), "There is a component not defined") !== FALSE) {
        return FALSE;
      }
    }
    return $ordered;
  }

  public function alterConfiguration(Configuration $configuration) {
    $alter = $this->configuration_manager->settings()->get('alter');
    if (!empty($alter) && !empty($alter[$configuration->getIdentifier()])) {
      foreach ($alter[$configuration->getIdentifier()] as $processor_name => $properties) {
        $processor = $this->configuration_manager->getProccesor($processor_name);
        $processor->revert($configuration, $properties);
      }
    }
  }

  public function configurations() {
    return $this->configuration_list;
  }

  protected function readConfigurationsFile() {
    $this->loadConfigurationList();
  }

  public function loadConfigurationList($path = NULL) {
    $this->configuration_list->load($path);
    return $this;
  }

}
