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

  public function build($include_dependencies = TRUE) {
    $this->data = (array)node_type_get_type($this->identifier);

    // Force module name to be 'configuration' if set to 'node. If we leave as
    // 'node' the content type will be assumed to be database-stored by
    // the node module.
    $this->data['base'] = ($this->data['base'] === 'node') ? 'configuration' : $this->data['base'];

    if ($include_dependencies) {
      $this->findDependencies();
    }
    return $this;
  }

  /**
   * Returns all the identifiers available for this component.
   */
  public static function getAllIdentifiers() {
    return array_keys(node_type_get_types());
  }

}
