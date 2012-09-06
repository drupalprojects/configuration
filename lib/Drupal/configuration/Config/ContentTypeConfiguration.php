<?php

/**
 * @file
 * Definition of Drupal\configuration\Config\ContentTypeConfiguration.
 */

namespace Drupal\configuration\Config;

use Drupal\configuration\Config\Configuration;

class ContentTypeConfiguration extends Configuration {

  static protected $component = 'content_type';

  function __construct($identifier) {
    parent::__construct($identifier);
    $keys = array(
      'name',
      'base',
      'description',
      'has_title',
      'title_label',
      'help',
    );
    $this->setKeysToExport($keys);
  }

  public function configForEntity() {
    return TRUE;
  }

  public function getEntityType() {
    return 'node';
  }

  protected function prepareBuild() {
    $this->data = (array)node_type_get_type($this->identifier);

    // Force module name to be 'configuration' if set to 'node. If we leave as
    // 'node' the content type will be assumed to be database-stored by
    // the node module.
    $this->data['base'] = ($this->data['base'] === 'node') ? 'configuration' : $this->data['base'];
    return $this;
  }

  /**
   * Returns all the identifiers available for this component.
   */
  public static function getAllIdentifiers() {
    return array_keys(node_type_get_types());
  }

  public function findRequiredModules() {
    if ($this->data['base'] == 'node_content' || $this->data['base'] == 'configuration') {
      $this->addToModules('node');
    }
    else {
      $this->addToModules($this->data['base']);
    }
  }

  static public function saveToActiveStore($components = array()) {
    if ($components) {
      foreach ($components as $config) {
        $info = $config->getData();
        node_type_save($info);
      }
    }
  }

  static public function revertHook($components = array()) {
    foreach ($components as $component) {
      // Delete node types
      // We don't use node_type_delete() because we do not actually
      // want to delete the node type (and invoke hook_node_type()).
      // This can lead to bad consequences like CCK deleting field
      // storage in the DB.
      db_delete('node_type')
        ->condition('type', $component->getIdentifier())
        ->execute();
    }
    if (!empty($components)) {
      node_types_rebuild();
      menu_rebuild();
    }
  }
}
