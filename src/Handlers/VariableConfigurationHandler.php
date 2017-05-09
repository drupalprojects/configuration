<?php

namespace Configuration\Handlers;

use Configuration\Configuration;
use Configuration\Handlers\ConfigurationHandler;


class VariableConfigurationHandler extends ConfigurationHandler {

  static public function getSupportedTypes() {
    return array('variable');
  }

  public function getIdentifiers() {
    return $this->configuration_manager->drupal()->variable_getIdentifiers();
  }

  public function loadFromDatabase($identifier) {
    $name = $this->getInternalId($identifier);

    $configuration = new Configuration;
    $configuration->setIdentifier($identifier);
    $configuration->setData(variable_get($name, NULL));

    $event = $this->triggerEvent('load_from_database', $configuration);

    return $event->configuration;
  }

  public function writeToDatabase(Configuration $configuration) {
    $name = $this->getInternalId($configuration->getIdentifier());

    $event = $this->triggerEvent('write_to_database', $configuration);

    $this->configuration_manager->drupal()->variable_set($name, $event->configuration->getData());
  }

  public function removeFromDatabase(Configuration $configuration) {
    $name = $this->getInternalId($configuration->getIdentifier());

    $event = $this->triggerEvent('remove_from_database', $configuration);

    $this->configuration_manager->drupal()->variable_del($name);
  }

  public static function getSubscribedEvents() {
    return array(
      'load_from_database.content_type' => array('onContentTypeLoad', 0),
    );
  }

  /**
   * Reacts when a content type is loaded and add some variables as dependencies
   * of the loaded content type.
   *
   * @param  ConfigurationCRUDEvent $event
   *   The event triggered when loading the content type from the database.
   */
  public function onContentTypeLoad($event) {
    $type = $this->getInternalId($event->configuration->getIdentifier());

    $variables = array(
      'field_bundle_settings_node_',
      'language_content_type',
      'node_options',
      'node_preview',
      'node_submitted',
    );

    if ($this->configuration_manager->drupal()->module_exists('comment')) {
      $variables += array(
        'comment',
        'comment_anonymous',
        'comment_controls',
        'comment_default_mode',
        'comment_default_order',
        'comment_default_per_page',
        'comment_form_location',
        'comment_preview',
        'comment_subject_field',
      );
    }

    if ($this->configuration_manager->drupal()->module_exists('menu')) {
      $variables += array(
        'menu_options',
        'menu_parent',
      );
    }

    foreach ($variables as &$variable) {
      $variable .= '_' . $type;
    }

    // Some variables doesn't have a valude defined in the database and its
    // values are provided by the second parameter of variable_get.
    // Only inform about variables that are actually have values.
    global $conf;
    foreach ($variables as $variable_name) {
      if (isset($conf[$variable_name])) {
        $this->configuration_manager->newDependency($event->configuration, 'variable.' . $variable_name);
      }
    }
  }

  protected function jsonAsArray() {
    return TRUE;
  }

}
