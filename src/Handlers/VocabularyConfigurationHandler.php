<?php

namespace Configuration\Handlers;

use Configuration\Configuration;
use Configuration\Handlers\ConfigurationHandler;


class VocabularyConfigurationHandler extends ConfigurationHandler {

  static public function getSupportedTypes() {
    return array('vocabulary');
  }

  public function getIdentifiers() {
    $return = array();
    $vocabularies = $this->configuration_manager->drupal()->taxonomy_get_vocabularies();
    foreach ($vocabularies as $vocabulary) {
      $return[$vocabulary->machine_name] = $vocabulary->name;
    }
    return $return;
  }

  protected function registerProcessors() {
    foreach (\Configuration\Processors\VocabularyProcessor::availableProcessors() as $name) {
      $processor = new \Configuration\Processors\VocabularyProcessor($name, $this->configuration_manager);
      $this->configuration_manager->registerProcessor($name, $processor);
    }
  }

  public function loadFromDatabase($identifier) {
    $name = $this->getInternalId($identifier);

    $configuration = new Configuration;
    $configuration->setIdentifier($identifier);

    $vocabulary = $this->configuration_manager->drupal()->taxonomy_vocabulary_machine_name_load($name);
    unset($vocabulary->vid);
    $configuration->setData($vocabulary);
    $configuration->addModule('taxonomy');

    $event = $this->triggerEvent('load_from_database', $configuration);
    $event_settings = array(
      'entity_type' => 'taxonomy_term',
      'bundle_name' => $vocabulary->machine_name,
    );
    $event = $this->triggerEvent('load_from_database.entity', $event->configuration, $event_settings, FALSE);

    return $event->configuration;
  }

  public function writeToDatabase(Configuration $configuration) {
    $name = $this->getInternalId($configuration->getIdentifier());

    $vocabulary = $configuration->getData();
    $existent_vocabulary = $this->configuration_manager->drupal()->taxonomy_vocabulary_machine_name_load($name);
    if (!empty($existent_vocabulary)) {
      $vocabulary->vid = $existent_vocabulary->vid;
    }

    $event = $this->triggerEvent('write_to_database', $configuration);

    $this->configuration_manager->drupal()->taxonomy_vocabulary_save($vocabulary);
  }

  public function removeFromDatabase(Configuration $configuration) {
    $name = $this->getInternalId($configuration->getIdentifier());

    $existent_vocabulary = $this->configuration_manager->drupal()->taxonomy_vocabulary_machine_name_load($name);
    $vocabulary = $configuration->getData();
    $vocabulary->vid = $existent_vocabulary->vid;
    $event = $this->triggerEvent('remove_from_database', $configuration);

    $this->configuration_manager->drupal()->taxonomy_vocabulary_delete($vocabulary->vid);
  }

  public static function getSubscribedEvents() {
    return array(
      'load_from_database.field_base' => array('onFieldBaseLoad', 0),
    );
  }

  public function onFieldBaseLoad($event) {
    $field = $event->configuration->getData();

    if ($field['type'] == 'taxonomy_term_reference' && !empty($field['settings']['allowed_values'])) {
      foreach ($field['settings']['allowed_values'] as $vocabulary) {
        $this->configuration_manager->newDependency($event->configuration, 'vocabulary.' . $vocabulary['vocabulary']);
      }
    }
  }

}
