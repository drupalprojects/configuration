<?php

/**
 * @file
 * Definition of Drupal\configuration\Config\ContentTypeConfiguration.
 */

namespace Drupal\configuration\Config;

use Drupal\configuration\Config\Configuration;

class ContentTypeConfiguration extends Configuration {

  static protected $component = 'content_type';

  public function configForEntity() {
    return TRUE;
  }

  public function getEntityType() {
    return 'node';
  }

  public function build($include_dependencies = TRUE) {
    $this->data = node_type_get_type($this->identifier);
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
