<?php

/**
 * @file
 * Definition of Drupal\configuration\Config\ImageStyleConfiguration.
 */

namespace Drupal\configuration\Config;

use Drupal\configuration\Config\Configuration;
use Drupal\configuration\Utils\ConfigIteratorSettings;

class ImageStyleConfiguration extends Configuration {

  static protected $component = 'image_style';

  protected function prepareBuild() {
    $style = image_style_load($this->getIdentifier());
    $this->style_sanitize($style);
    $this->data = $style;

    // Reset the order of effects, this will help to generate always the same
    // hash for image styles that have been reverted.
    $this->data['effects'] = array();
    foreach ($style['effects'] as $effect) {
      $this->data['effects'][] = $effect;
    }
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
      if (!empty($field['display'])) {
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
  }

  public function findRequiredModules() {
    foreach ($this->data['effects'] as $effect) {
      $this->addToModules($effect['module']);
    }
  }

  public function saveToActiveStore(ConfigIteratorSettings &$settings) {
    if ($style = image_style_load($this->getIdentifier())) {
      if (!empty($style['isid'])) {
        image_style_delete($style);
      }
    }
    image_default_style_save($this->getData());
    $settings->addInfo('imported', $this->getUniqueId());
  }
}
