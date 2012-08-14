<?php

/**
 * @file
 * Definition of Drupal\configuration\Config\TextFormatConfiguration.
 */

namespace Drupal\configuration\Config;

use Drupal\configuration\Config\Configuration;

class TextFormatConfiguration extends Configuration {

  static protected $component = 'text_format';

  function build($include_dependencies = TRUE) {
    $this->data = $this->filter_format_load($this->getIdentifier());
    if ($include_dependencies) {
      $this->findDependencies();
    }
    return $this;
  }

  /**
   * Returns all the identifiers available for this component.
   */
  public static function getAllIdentifiers() {
    return array_keys(filter_formats());
  }

  protected function filter_format_load($name) {
    // Use machine name for retrieving the format if available.
    $query = db_select('filter_format');
    $query->fields('filter_format');
    $query->condition('format', $name);

    // Retrieve filters for the format and attach.
    if ($format = $query->execute()->fetchObject()) {
      $format->filters = array();
      foreach (filter_list_format($format->format) as $filter) {
        if (!empty($filter->status)) {
          $format->filters[$filter->name]['weight'] = $filter->weight;
          $format->filters[$filter->name]['status'] = $filter->status;
          $format->filters[$filter->name]['settings'] = $filter->settings;
        }
      }
      return $format;
    }
    return FALSE;
  }

}
