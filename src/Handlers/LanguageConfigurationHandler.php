<?php

namespace Configuration\Handlers;

use Configuration\Configuration;
use Configuration\Handlers\ConfigurationHandler;


class LanguageConfigurationHandler extends ConfigurationHandler {

  static public function getSupportedTypes() {
    return array('language');
  }

  public function getIdentifiers() {
    return $this->configuration_manager->drupal()->locale_language_list('native', TRUE);
  }

  public function loadFromDatabase($identifier) {
    $name = $this->getInternalId($identifier);

    $language_list = $this->configuration_manager->drupal()->language_list();
    $language = $language_list[$name];

    $configuration = new Configuration;
    $configuration->setIdentifier($identifier);
    $configuration->setData($language);
    $configuration->addModule('locale');

    $event = $this->triggerEvent('load_from_database', $configuration);

    return $event->configuration;
  }

  public function writeToDatabase(Configuration $configuration) {
    $name = $this->getInternalId($configuration->getIdentifier());

    $event = $this->triggerEvent('write_to_database', $configuration);

    $language = $event->configuration->getData();

    $this->configuration_manager->drupal()->locale_writeToDatabase($language);
  }

  public function removeFromDatabase(Configuration $configuration) {
    $langcode = $this->getInternalId($configuration->getIdentifier());

    $event = $this->triggerEvent('remove_from_database', $configuration);

    $this->configuration_manager->drupal()->locale_removeFromDatabase($langcode);
  }

  public static function getSubscribedEvents() {
    return array(
      'write_to_database.variable' => array('onVariableSave', 0),
    );
  }

  public function onVariableSave($event) {
    $identifier = $event->configuration->getIdentifier();
    if ($identifier == 'variable.language_default') {
      $data = $event->configuration->getData();
      $event->configuration->setData((object)$data);
    }
  }
}
