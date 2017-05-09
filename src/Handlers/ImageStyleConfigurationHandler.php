<?php

namespace Configuration\Handlers;

use Configuration\Configuration;
use Configuration\Handlers\ConfigurationHandler;


class ImageStyleConfigurationHandler extends ConfigurationHandler {

  static public function getSupportedTypes() {
    return array('image_style');
  }

  public function getIdentifiers() {
    $identifiers = array();
    foreach (image_styles() as $key => $image_style) {
      $identifiers[$key] = $image_style['name'];
    }
    return $identifiers;
  }

  public function loadFromDatabase($identifier) {
    $name = $this->getInternalId($identifier);

    $configuration = new Configuration;
    $configuration->setIdentifier($identifier);

    $style = $this->configuration_manager->drupal()->image_style_load($name);
    $this->styleSanitize($style);

    // Reset the order of effects, this will help to generate always the same
    // hash for image styles that have been reverted.
    $effects_copy = array();
    if (!empty($style['effects'])) {
      foreach ($style['effects'] as $effect) {
        $configuration->addModule($effect['module']);
        $effects_copy[] = $effect;
      }
    }
    $style['effects'] = $effects_copy;
    $configuration->setData($style);

    $event = $this->triggerEvent('load_from_database', $configuration);

    return $event->configuration;
  }

  public function writeToDatabase(Configuration $configuration) {
    $name = $this->getInternalId($configuration->getIdentifier());

    $event = $this->triggerEvent('write_to_database', $configuration);

    $style = $event->configuration->getData();

    // Does an image style with the same name already exist?
    if ($existing_style = $this->configuration_manager->drupal()->image_style_load($name)) {
      $isExistingEditable = (bool)($existing_style['storage'] & IMAGE_STORAGE_EDITABLE);
      $isNewEditable = (bool)($style['storage'] & IMAGE_STORAGE_EDITABLE);

      // New style is using defaults -> revert existing.
      if (!$isNewEditable && $isExistingEditable) {
        $this->configuration_manager->drupal()->image_default_style_revert($name);
      }

      // New style is editable -> update existing style.
      elseif ($isExistingEditable && $isNewEditable) {
        $style['isid'] = $existing_style['isid'];
        $style = $this->configuration_manager->drupal()->image_style_save($style);
        if (!empty($existing_style['effects'])) {
          foreach ($existing_style['effects'] as $effect) {
            image_effect_delete($effect);
          }
        }
        if (!empty($style['effects'])) {
          foreach ($style['effects'] as $effect) {
            $effect['isid'] = $style['isid'];
            $this->configuration_manager->drupal()->image_effect_save($effect);
          }
        }
      }

      // New style is editable, existing style is using defaults -> update without deleting effects.
      elseif ($isNewEditable && !$isExistingEditable) {
        if (!empty($existing_style['isid'])) {
          $style['isid'] = $existing_style['isid'];
        }
        $style = $this->configuration_manager->drupal()->image_style_save($style);
        if (!empty($style['effects'])) {
          foreach ($style['effects'] as $effect) {
            $effect['isid'] = $style['isid'];
            image_effect_save($effect);
          }
        }
      }

      // Neither style is editable, both default -> do nothing at all.
      else {

      }
    }

    // New style does not exist yet on this system -> save it regardless of its storage.
    else {
      $style = $this->configuration_manager->drupal()->image_style_save($style);
      if (!empty($style['effects'])) {
        foreach ($style['effects'] as $effect) {
          $effect['isid'] = $style['isid'];
          $this->configuration_manager->drupal()->image_effect_save($effect);
        }
      }
      $this->configuration_manager->drupal()->image_style_flush($style);
    }

  }

  public function removeFromDatabase(Configuration $configuration) {
    $name = $this->getInternalId($configuration->getIdentifier());

    $event = $this->triggerEvent('remove_from_database', $configuration);

    $style = $event->configuration->getData();
    $this->configuration_manager->drupal()->image_style_delete($style);
  }

  /**
   * Remove unnecessary keys for export.
   */
  protected function styleSanitize(&$style, $child = FALSE) {
    $omit = $child ? array('isid', 'ieid') : array('isid', 'ieid', 'module');
    if (is_array($style)) {
      foreach ($style as $k => $v) {
        if (in_array($k, $omit, TRUE)) {
          unset($style[$k]);
        }
        elseif (is_array($v)) {
          $this->styleSanitize($style[$k], TRUE);
        }
      }
    }
  }

  public static function getSubscribedEvents() {
    return array(
      'load_from_database.field_instance' => array('onFieldInstanceLoad', 0),
    );
  }


  public function onFieldInstanceLoad($event) {
    // Check if the field is using a image style
    $field = $event->configuration->getData();
    if (!empty($field['display'])) {
      foreach ($field['display'] as $display) {
        if (!empty($display['settings']['image_style'])) {
          $identifier = $display['settings']['image_style'];
          $this->configuration_manager->newDependency($event->configuration, 'image_style.' . $identifier);
        }
      }
    }
  }

  protected function jsonAsArray() {
    return TRUE;
  }

}
