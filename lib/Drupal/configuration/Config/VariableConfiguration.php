<?php

/**
 * @file
 * Definition of Drupal\configuration\Config\VariableConfiguration.
 */

namespace Drupal\configuration\Config;

use Drupal\configuration\Config\Configuration;
use Drupal\configuration\Utils\ConfigIteratorSettings;

class VariableConfiguration extends Configuration {

  static protected $component = 'variable';
  protected $variable_name = '';

  function __construct($identifier) {
    $this->variable_name = $identifier;
    parent::__construct(str_replace(' ', '_', $identifier));

    $this->storage->setFileName('variable.' . str_replace(' ', '_', $identifier));
  }


  protected function prepareBuild() {
    $this->data = array(
      'name' => $this->variable_name,
      'content' => variable_get($this->getIdentifier(), NULL),
    );
    return $this;
  }

  /**
   * Returns all the identifiers available for this component.
   */
  public static function getAllIdentifiers() {
    $variables = db_query("SELECT name FROM variable")->fetchAll();
    $return = array();
    foreach ($variables as $variable) {
      $return[] = $variable->name;
    }
    return $return;
  }

  public function saveToActiveStore(ConfigIteratorSettings &$settings) {
    $variable = $this->getData();
    variable_set($variable['name'], $variable['content']);
    $settings->addInfo('imported', $this->getUniqueId());
  }

  public static function alterDependencies(Configuration $config, &$stack) {
    if ($config->getComponent() == 'content_type') {
      $variables = array(
        'field_bundle_settings_node_',
        'language_content_type',
        'node_options',
        'node_preview',
        'node_submitted',
      );

      if (module_exists('comment')) {
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

      if (module_exists('menu')) {
        $variables += array(
          'menu_options',
          'menu_parent',
        );
      }

      $entity_type = $config->getEntityType();
      $fields = field_info_instances($entity_type, $config->getIdentifier());
      foreach ($variables as $variable) {
        $identifier = $variable . '_' . $config->getIdentifier();

        // Avoid include multiple times the same dependency.
        if (empty($stack['variable.' . $identifier])) {
          $var_config = new VariableConfiguration($identifier);
          $var_config->build();
          $config->addToDependencies($var_config);
          $stack['variable.' . $identifier] = TRUE;
        }
      }
    }
  }
}
