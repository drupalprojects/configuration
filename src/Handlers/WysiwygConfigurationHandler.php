<?php

namespace Configuration\Handlers;

use Configuration\Configuration;
use Configuration\Handlers\ConfigurationHandler;


class WysiwygConfigurationHandler extends ConfigurationHandler {

  static public function getSupportedTypes() {
    return array('wysiwyg');
  }

  public function getIdentifiers() {
    $profiles = array();
    $formats = filter_formats();

    foreach (array_keys($this->configuration_manager->drupal()->wysiwyg_profile_load_all()) as $format) {
      // Text format may vanish without deleting the wysiwyg profile.
      if (isset($formats[$format])) {
        $profiles[$format] = $format;
      }
    }
    return $profiles;
  }

  public function loadFromDatabase($identifier) {
    $name = $this->getInternalId($identifier);

    $configuration = new Configuration;
    $configuration->setIdentifier($identifier);

    $profile = $this->configuration_manager->drupal()->wysiwyg_get_profile($name);
    if (empty($profile)) {
      $profile = new \StdClass();
      $profile->editor = '';
      $profile->format = $name;
      $profile->settings = array();
    }
    $configuration->setData($profile);
    $this->configuration_manager->newDependency($configuration, 'text_format.' . $name);

    $event = $this->triggerEvent('load_from_database', $configuration);

    return $event->configuration;
  }

  public function writeToDatabase(Configuration $configuration) {
    $name = $this->getInternalId($configuration->getIdentifier());

    $event = $this->triggerEvent('write_to_database', $configuration);

    $profile_array = $event->configuration->getData();

    $profile = new \StdClass;
    $profile->format = $profile_array["format"];
    $profile->editor = $profile_array["editor"];
    $profile->settings = $profile_array["settings"];

    $this->configuration_manager->drupal()->wysiwyg_saveProfile($profile);

    $this->configuration_manager->drupal()->wysiwyg_profile_cache_clear();
  }

  public function removeFromDatabase(Configuration $configuration) {
    $name = $this->getInternalId($configuration->getIdentifier());

    $event = $this->triggerEvent('remove_from_database', $configuration);

    $this->configuration_manager->drupal()->wysiwyg_profile_delete($name);
  }

  public static function getSubscribedEvents() {
    return array(
      'load_from_database.text_format' => array('onTextFormatLoad', 0),
    );
  }

  public function onTextFormatLoad($event) {
    // Check if this format is used by any wysiwyg profile
    $name = $this->getInternalId($event->configuration->getIdentifier());
    $profile = $this->configuration_manager->drupal()->wysiwyg_get_profile($name);
    if (!empty($profile)) {
      $this->configuration_manager->newPart($event->configuration, 'wysiwyg.' . $name);
    }
  }

  protected function jsonAsArray() {
    return TRUE;
  }

}
