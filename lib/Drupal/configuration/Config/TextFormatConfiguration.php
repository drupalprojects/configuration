<?php

/**
 * @file
 * Definition of Drupal\configuration\Config\TextFormatConfiguration.
 */

namespace Drupal\configuration\Config;

use Drupal\configuration\Config\Configuration;

class TextFormatConfiguration extends Configuration {

  static protected $component = 'text_format';

  protected function prepareBuild() {
    $this->data = $this->filter_format_load($this->getIdentifier());
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

  static public function rebuildHook($text_formats = array()) {
    if ($text_formats) {
      foreach ($text_formats as $config) {
        $text_format = $config->getData();
        $text_format = (object) $text_format;
        filter_format_save($text_format);
      }
    }
  }

  static public function revertHook($text_formats = array()) {
    static::rebuildHook($text_formats);
  }

  public function findRequiredModules() {
    $filter_info = filter_get_filters();
    foreach (array_keys($this->data->filters) as $filter) {
      if (!empty($filter_info[$filter]) && $filter_info[$filter]['module']) {
        $this->addToModules($filter_info[$filter]['module']);
      }
    }
  }
}
