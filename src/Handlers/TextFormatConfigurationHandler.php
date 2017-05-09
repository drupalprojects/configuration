<?php

namespace Configuration\Handlers;

use Configuration\Configuration;
use Configuration\Handlers\ConfigurationHandler;


class TextFormatConfigurationHandler extends ConfigurationHandler {

  static public function getSupportedTypes() {
    return array('text_format');
  }

  public function getIdentifiers() {
    $identifiers = array();
    foreach ($this->configuration_manager->drupal()->filter_formats() as $format) {
      $identifiers[$format->format] = $format->name;
    }
    return $identifiers;
  }

  public function loadFromDatabase($identifier) {
    $name = $this->getInternalId($identifier);

    $configuration = new Configuration;
    $configuration->setIdentifier($identifier);

    $format = $this->configuration_manager->drupal()->text_format_getFilterFormat($name);

    if (!empty($format)) {
      $filters_info = $this->configuration_manager->drupal()->filter_get_filters();
      $format->filters = array();
      foreach ($this->configuration_manager->drupal()->filter_list_format($format->format) as $filter) {
        if (!empty($filter->status)) {
          $format->filters[$filter->name]['weight'] = $filter->weight;
          $format->filters[$filter->name]['status'] = $filter->status;
          $format->filters[$filter->name]['settings'] = $filter->settings;

          $configuration->addModule($filters_info[$filter->name]['module']);
        }
      }
      $configuration->setData($format);
    }

    $event = $this->triggerEvent('load_from_database', $configuration);

    return $event->configuration;
  }

  public function writeToDatabase(Configuration $configuration) {

    $event = $this->triggerEvent('write_to_database', $configuration);
    $format_array = $event->configuration->getData();

    $format = new \StdClass;
    $format->format = $format_array["format"];
    $format->name = $format_array["name"];
    $format->cache = $format_array["cache"];
    $format->status = $format_array["status"];
    $format->weight = $format_array["weight"];
    $format->filters = $format_array["filters"];

    $this->configuration_manager->drupal()->filter_format_save($format);
  }

  public function removeFromDatabase(Configuration $configuration) {
    $name = $this->getInternalId($configuration->getIndentifier());

    $this->configuration_manager->drupal()->filter_format_load($name);
    $this->configuration_manager->drupal()->filter_format_disable($format);

    $event = $this->triggerEvent('remove_from_database', $configuration);
  }

  public static function getSubscribedEvents() {
    return array(
      'load_from_database.permission' => array('onPermissionLoad', 0),
    );
  }

  public function onPermissionLoad($event) {
    // Search for permissions that match: 'use text format ' . $format->format
    $permission = $event->configuration->getData();
    if (strpos($permission['permission'], 'user text format') !== FALSE) {
      foreach ($this->configuration_manager->drupal()->filter_formats() as $format) {
        $permission = $this->configuration_manager->drupal()->filter_permission_name($format);
        if (!empty($permission)) {
          $data = $event->configuration->getData();
          if ($permission == $data['permission']) {
            $this->configuration_manager->newDependency($event->configuration, 'text_format.' . $format->format);
            // Match found, no other text format will match.
            break;
          }
        }
      }
    }
  }

  protected function jsonAsArray() {
    return TRUE;
  }

}
