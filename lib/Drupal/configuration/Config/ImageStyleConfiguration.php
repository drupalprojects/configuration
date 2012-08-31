<?php

/**
 * @file
 * Definition of Drupal\configuration\Config\ImageStyleConfiguration.
 */

namespace Drupal\configuration\Config;

use Drupal\configuration\Config\Configuration;

class ImageStyleConfiguration extends Configuration {

  static protected $component = 'image_style';

  protected function prepareBuild() {
    $style = image_style_load($this->getIdentifier());
    $this->style_sanitize($style);
    $this->data = $style;
    return $this;
  }

  /**
   * Returns all the identifiers available for this component.
   */
  public static function getAllIdentifiers() {
    return array_keys(image_styles());
  }

  /**
   * Remove unnecessary keys for export.
   */
  protected function style_sanitize(&$style, $child = FALSE) {
    $omit = $child ? array('isid', 'ieid', 'storage') : array('isid', 'ieid', 'storage', 'module');
    if (is_array($style)) {
      foreach ($style as $k => $v) {
        if (in_array($k, $omit, TRUE)) {
          unset($style[$k]);
        }
        elseif (is_array($v)) {
          $this->style_sanitize($style[$k], TRUE);
        }
      }
    }
  }

  public static function alterDependencies(Configuration $config, &$stack) {
    if ($config->getComponent() == 'field') {
      // Check if the field is using a image style
      $field = $config->data['field_instance'];
      foreach ($field['display'] as $display) {
        if (!empty($display['settings']) && !empty($display['settings']['image_style'])) {
          $identifier = $display['settings']['image_style'];
          if (empty($stack['image_style.' . $identifier])) {
            $image_style = new ImageStyleConfiguration($identifier);
            $image_style->build();
            $config->addToDependencies($image_style);
            $stack['image_style.' . $identifier] = TRUE;
          }
        }
      }
    }
  }

  public function findRequiredModules() {
    foreach ($this->data['effects'] as $effect) {
      $this->addToModules($effect['module']);
    }
  }

  static function revertHook($components = array()) {
    foreach ($components as $component) {
      if ($style = image_style_load($component->getIdentifier())) {
        image_style_delete($style);
      }
    }
  }
}
