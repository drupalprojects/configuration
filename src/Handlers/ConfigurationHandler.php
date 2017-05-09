<?php

namespace Configuration\Handlers;

use Configuration\Configuration;
use Configuration\ConfigurationHandlerInterface;
use Configuration\Events\ConfigurationCRUDEvent;

use Camspiers\JsonPretty\JsonPretty;
use Symfony\Component\Config\FileLocator;

abstract class ConfigurationHandler implements ConfigurationHandlerInterface {

  // Global handler that coordinates all the configuration handlers
  protected $configuration_manager;

  // The current configuration type this instance is handling.
  protected $type;

  public function __construct($type, $configuration_manager) {
    $this->type = $type;
    $this->configuration_manager = $configuration_manager;
    $this->registerProcessors();
  }

  protected function registerProcessors() { }

  public function getType() {
    return $this->type;
  }

  static public function getSupportedTypes() {
    return array();
  }

  abstract public function getIdentifiers();

  abstract public function loadFromDatabase($identifier);

  abstract public function writeToDatabase(Configuration $configuration);

  abstract public function removeFromDatabase(Configuration $configuration);

  static public function getSubscribedEvents() {
    return array();
  }

  protected function getInternalId($identifier) {
    return substr($identifier, strpos($identifier, '.') + 1);
  }

  protected function getTypeFromId($identifier) {
    return substr($identifier, 0, strpos($identifier, '.'));
  }

  protected function triggerEvent($event_name, Configuration $configuration, $settings = array(), $append_configuration_type = TRUE) {
    $event = new ConfigurationCRUDEvent($configuration, $settings);
    if ($append_configuration_type) {
      $event_name = $event_name . '.' . $this->getType();
    }
    $this->configuration_manager->dispatchEvent($event_name, $event);
    return $event;
  }

  /**
   * Generates the JSON representation of this configuration.
   */
  public function export(Configuration $configuration, $format = 'json') {
    switch ($format) {
      case 'json':
        return $this->exportToJson($configuration);
        break;

      default:
        return '';
        break;
    }
  }

  protected function exportToJson(Configuration $configuration) {
    $object = $configuration->toObject();

    $jsonPretty = new JsonPretty;
    return $jsonPretty->prettify($object, NULL, '  ');
  }

  public function getExportPath(Configuration $configuration) {
    $pattern = $this->configuration_manager->settings()->get('export.default_pattern');
    $overriden_patterns = $this->configuration_manager->settings()->get('export.overriden_patterns');

    foreach ($overriden_patterns as $current_pattern => $replacement) {
      if ($current_pattern == $this->getType() . '.*') {
        $pattern = $replacement;
        break;
      }
      elseif ($current_pattern == $configuration->getIdentifier()) {
        $pattern = $replacement;
        break;
      }
    }
    $tokens = array(
      '[group]' => $configuration->getGroup(),
      '[type]' => $this->getType(),
    );
    $path = strtr($pattern, $tokens);
    $path = rtrim($path, '/');
    $path = ltrim($path, '/');
    return $path . '/';
  }

  public function import($path, $format = "json") {
    $import_path = drupal_realpath('public://' . $this->configuration_manager->settings()->get('import.path'));
    $directories = array($import_path);
    $locator = new FileLocator($directories);
    $config_full_path = $locator->locate($path);

    if (!empty($config_full_path)) {
      $file_content = file_get_contents($config_full_path);

      switch ($format) {
        case 'json':
          return $this->importFromJson($file_content);
          break;

        default:
          return '';
          break;
      }
    }
  }

  protected function jsonAsArray() {
    return FALSE;
  }

  public function importFromJson($file_content) {
    $object = new \stdClass;
    if ($this->jsonAsArray()) {
      $object = $this->importFromJsonAsArray($file_content);
    }
    else {
      $object = json_decode($file_content);
    }

    $configuration = new Configuration();
    $configuration->fromObject($object);
    return $configuration;
  }

  protected function importFromJsonAsArray($file_content) {
    // Load the view as an array, it will be converted to proper views objects later.
    $array = json_decode($file_content, TRUE);

    $object = new \stdClass;
    $object->identifier = $array['identifier'];
    $object->notes = $array['notes'];
    $object->tags = $array['tags'];
    $object->dependencies = $array['dependencies'];
    $object->parts = $array['parts'];
    $object->modules = $array['modules'];
    $object->data = $array['data'];
    unset($array);
    return $object;
  }

}
