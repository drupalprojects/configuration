<?php

/**
 * @file
 * Definition of Drupal\configuration\Config\ContentTypeConfiguration.
 */

namespace Drupal\configuration\Config;

use Drupal\configuration\Config\Configuration;
use Drupal\configuration\Utils\ConfigIteratorSettings;

class ContentTypeConfiguration extends Configuration {

  function __construct($identifier, $component = '') {
    parent::__construct($identifier);
    $keys = array(
      'type',
      'name',
      'base',
      'description',
      'has_title',
      'title_label',
      'help',
    );
    $this->setKeysToExport($keys);
  }

  public function getComponent() {
    return 'content_type';
  }

  static public function supportedComponents() {
    return array('content_type');
  }

  static public function getComponentHumanName($component, $plural = FALSE) {
    return $plural ? t('Content types') : t('Content type');
  }

  public function configForEntity() {
    return TRUE;
  }

  public function getEntityType() {
    return 'node';
  }

  protected function prepareBuild() {
    $this->data = (object)node_type_get_type($this->identifier);

    // Force module name to be 'configuration' if set to 'node. If we leave as
    // 'node' the content type will be assumed to be database-stored by
    // the node module.
    $this->data->base = ($this->data->base === 'node') ? 'configuration' : $this->data->base;
    return $this;
  }

  /**
   * Returns all the identifiers available for this component.
   */
  public static function getAllIdentifiers($component) {
    return array_keys(node_type_get_types());
  }

  public function findRequiredModules() {
    if ($this->data->base == 'node_content' || $this->data->base == 'configuration') {
      $this->addToModules('node');
    }
    else {
      $this->addToModules($this->data->base);
    }
  }

  public function saveToActiveStore(ConfigIteratorSettings &$settings) {
    $info = (object)$this->getData();
    node_type_save($info);
    $settings->addInfo('imported', $this->getUniqueId());
  }
}
