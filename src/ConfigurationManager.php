<?php

namespace Configuration;

use Configuration\Handlers\ConfigurationProxy;
use Configuration\Handlers\ContentTypeConfigurationHandler;
use Configuration\Handlers\FieldBaseConfigurationHandler;
use Configuration\Handlers\FieldInstanceConfigurationHandler;
use Configuration\Handlers\PermissionConfigurationHandler;
use Configuration\Handlers\RoleConfigurationHandler;
use Configuration\Handlers\TextFormatConfigurationHandler;
use Configuration\Handlers\VariableConfigurationHandler;

use Configuration\Helpers\ConfigurationSettings;
use Configuration\Helpers\ConfigurationExportManager;
use Configuration\Helpers\ConfigurationImportManager;
use Configuration\Helpers\ConfigurationsCacheInMemory;

use Symfony\Component\EventDispatcher\EventDispatcher;

use PhpCollection\Map;

class ConfigurationManager {

  protected $referenced_configurations;
  protected $handlers;
  protected $dispatcher;
  protected $cache;
  protected $settings;
  protected $exporter_manager;
  protected $importer_manager;
  protected $backend;

  function __construct() {
    $this->referenced_configurations = array();
    $this->handlers = new Map();
    $this->proccesors = new Map();
    $this->cache = new ConfigurationsCacheInMemory();
    $this->settings = new ConfigurationSettings();
    $this->exporter_manager = new ConfigurationExportManager($this);
    $this->importer_manager = new ConfigurationImportManager($this);

    $this->resetDefaultsSettings();
    $this->backend = new \Configuration\Backends\Drupal7Backend();
  }

  protected function resetDefaultsSettings() {
    $this->cache()->reset();
    $this->settings()->reset();
    $this->exporter()->reset();
  }

  public function registerHandlers() {
    $this->handlers->set('content_type', new ContentTypeConfigurationHandler('content_type', $this));
    $this->handlers->set('field_instance', new FieldInstanceConfigurationHandler('field_instance', $this));
    $this->handlers->set('field_base', new FieldBaseConfigurationHandler('field_base', $this));
    $this->handlers->set('variable', new VariableConfigurationHandler('variable', $this));
    $this->handlers->set('permission', new PermissionConfigurationHandler('permission', $this));
    $this->handlers->set('role', new RoleConfigurationHandler('role', $this));
    $this->handlers->set('text_format', new TextFormatConfigurationHandler('text_format', $this));
    if (module_exists('image')) {
      $this->handlers->set('image_style', new \Configuration\Handlers\ImageStyleConfigurationHandler('image_style', $this));
    }
    if (module_exists('locale')) {
      $this->handlers->set('language', new \Configuration\Handlers\LanguageConfigurationHandler('language', $this));
    }
    if (module_exists('menu')) {
      $this->handlers->set('menu', new \Configuration\Handlers\MenuConfigurationHandler('menu', $this));
    }
    if (module_exists('taxonomy')) {
      $this->handlers->set('vocabulary', new \Configuration\Handlers\VocabularyConfigurationHandler('vocabulary', $this));
    }
    if (module_exists('views')) {
      $this->handlers->set('view', new \Configuration\Handlers\ViewConfigurationHandler('view', $this));
    }
    if (module_exists('wysiwyg')) {
      $this->handlers->set('wysiwyg', new \Configuration\Handlers\WysiwygConfigurationHandler('wysiwyg', $this));
    }
    if (module_exists('entity')) {
      foreach (\Configuration\Handlers\EntityConfigurationHandler::getSupportedTypes() as $entity_type){
        $this->handlers->set($entity_type, new \Configuration\Handlers\EntityConfigurationHandler($entity_type, $this));
      }
    }

    // Register Metadata Proccesor due it is not handler specific.
    foreach (\Configuration\Processors\MetadataProcessor::availableProcessors() as $name) {
      $processor = new \Configuration\Processors\MetadataProcessor($name, $this);
      $this->registerProcessor($name, $processor);
    }

    return $this;
  }

  public function registerProcessor($name, $processor) {
    $this->proccesors->set($name, $processor);
  }

  public function getHandlerFromIdentifier($identifier) {
    $type = substr($identifier, 0, strpos($identifier, '.'));
    return $this->handlers->get($type)->get();
  }

  public function getHandlerFromType($type) {
    return $this->handlers->get($type)->get();
  }

  public function getProccesor($name) {
    return $this->proccesors->get($name)->get();
  }

  public function getHandler(Configuration $configuration) {
    $type = substr($configuration->getIdentifier(), 0, strpos($configuration->getIdentifier(), '.'));
    return $this->handlers->get($type)->get();
  }

  public function getHandlersTypes() {
    return $this->handlers->keys();
  }

  public function registerEvents() {
    $this->dispatcher = new EventDispatcher();
    foreach (iterator_to_array($this->handlers) as $handler) {
      $this->dispatcher->addSubscriber($handler);
    }
    foreach (iterator_to_array($this->proccesors) as $processor) {
      $this->dispatcher->addSubscriber($processor);
    }
    return $this;
  }

  public function loadSettings($path = NULL) {
    $this->settings->load($path);
    return $this;
  }

  public function settings() {
    return $this->settings;
  }

  public function exporter() {
    return $this->exporter_manager;
  }

  public function importer() {
    return $this->importer_manager;
  }

  public function cache() {
    return $this->cache;
  }

  public function drupal() {
    return $this->backend;
  }

  public function dispatchEvent($name, $event) {
    $this->dispatcher->dispatch($name, $event);
  }

  public function newDependency(Configuration $configuration, $dependency, $type = NULL) {
    $configuration->addDependency($dependency);
    $this->referenceConfiguration($dependency, $configuration->getIdentifier(), $type);
  }

  public function newPart(Configuration $configuration, $part, $type = NULL) {
    $configuration->addPart($part);
    $this->referenceConfiguration($configuration->getIdentifier(), $part, $type);
  }

  public function getReferences($identifier) {
    if (isset($this->referenced_configurations[$identifier])) {
      return $this->referenced_configurations[$identifier]->all();
    }
    return array();
  }

  public function clearReferences($identifier) {
    if (isset($this->referenced_configurations[$identifier])) {
      $this->referenced_configurations[$identifier]->clear();
      unset($this->referenced_configurations[$identifier]);
    }
  }

  protected function referenceConfiguration($from, $to, $type = NULL) {
    if (!isset($this->referenced_configurations[$from])) {
      $this->referenced_configurations[$from] = new Map();
    }

    $this->referenced_configurations[$from]->set($to, $type);
  }

  public function configuration($identifier) {
    $type = substr($identifier, 0, strpos($identifier, '.'));
    if ($this->cache()->contains($identifier)) {
      return new ConfigurationProxy($identifier,
                                    $this->handlers->get($type)->get(),
                                    $this,
                                    $this->cache()->get($identifier));
    }
    else {
      return new ConfigurationProxy($identifier, $this->handlers->get($type)->get(), $this);
    }
  }

  public function currentlyManagerConfigurations() {
    return array(
      'variable.site_name' => array(
        'hash' => 'd1f17f8edf66e197a48c04b505126acd8bf07e42',
        'group' => 'none',
        'path' => 'none/variable/variable.site_name.json',
      )
    );
  }

  public function getHash($identifier) {
    $currentlyManagerConfigurations = $this->currentlyManagerConfigurations();
    if (!empty($currentlyManagerConfigurations[$identifier])) {
      return $currentlyManagerConfigurations[$identifier]['hash'];
    }
  }

  public function applyPendingOperations() {
    foreach ($this->referenced_configurations as $identifier => $map) {
      foreach ($map->keys() as $dependency) {
          $this->cache()->get($identifier)->addPart($dependency);
          $this->cache()->get($dependency)->addDependency($identifier);
      }
    }
  }

  /**
   * Export the configuration of the site into the filesystem.
   */
  public function export() {
    $this->exporter()->export();
  }

  /**
   * Load the configurations and write them into the database.
   *
   * @param array $settings
   *  An array of settings
   */
  public function load($settings) {

  }

}
