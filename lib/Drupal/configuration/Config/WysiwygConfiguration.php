<?php

/**
 * @file
 * Definition of Drupal\configuration\Config\WysiwygConfiguration.
 */

namespace Drupal\configuration\Config;

use Drupal\configuration\Config\Configuration;
use Drupal\configuration\Utils\ConfigIteratorSettings;

class WysiwygConfiguration extends Configuration {

  protected function prepareBuild() {
    $this->data = wysiwyg_get_profile($this->getIdentifier());
    return $this;
  }

  static public function getComponentHumanName($component, $plural = FALSE) {
    return $plural ? t('Wyswyg Profiles') : t('Wyswyg Profile');
  }

  public static function isActive() {
    return module_exists('wysiwyg');
  }

  public function getComponent() {
    return 'wysiwyg';
  }

  static public function supportedComponents() {
    return array('wysiwyg');
  }

  /**
   * Returns all the identifiers available for this component.
   */
  public static function getAllIdentifiers($component) {
    $profiles = array();

    $formats = filter_formats();

    foreach (array_keys(wysiwyg_profile_load_all()) as $format) {
      // Text format may vanish without deleting the wysiwyg profile.
      if (isset($formats[$format])) {
        $profiles[] = $format;
      }
    }
    return $profiles;
  }

  public static function alterDependencies(Configuration $config, &$stack) {
    if ($config->getComponent() == 'text_format') {
      $formats = filter_formats();
      foreach (array_keys(wysiwyg_profile_load_all()) as $format) {
        // Text format may vanish without deleting the wysiwyg profile.
        if (isset($formats[$format]) && $format == $config->getIdentifier()) {
          $identifier = $format;
          if (empty($stack['wysiwyg.' . $identifier])) {
            $wysiwig_profile = new WysiwygConfiguration($identifier);
            $wysiwig_profile->build();

            $config->addToOptionalConfigurations($wysiwig_profile);

            $wysiwig_profile->addToDependencies($config);
            $stack['wysiwyg.' . $identifier] = TRUE;
          }
        }
      }
    }
  }

  public function findDependencies() {
    $format = $this->getIdentifier();

    $formats = filter_formats();
    if (isset($formats[$format])) {
      $filter_format = Configuration::createConfigurationInstance('text_format.' . $format);
      $this->addToDependencies($filter_format);
    }

    parent::findDependencies();
  }

  public function findRequiredModules() {
    $this->addToModules('wysiwyg');
    // @todo figure out if there is a way to add modules that provides plugins
    // for this wysiwyg
  }

  public function saveToActiveStore(ConfigIteratorSettings &$settings) {
    $profile = $this->getData();

    // For profiles that doens't have editors assigned, provide a default
    // object to avoid sql exceptions.
    if (empty($profile)) {
      $profile = new \StdClass();
      $profile->editor = '';
      $profile->format = $this->getIdentifier();
      $profile->settings = array();
    }

    db_merge('wysiwyg')
      ->key(array('format' => $profile->format))
      ->fields(array(
        'format' => $profile->format,
        'editor' => $profile->editor,
        'settings' => serialize($profile->settings),
      ))
      ->execute();
    wysiwyg_profile_cache_clear();

    $settings->addInfo('imported', $this->getUniqueId());
  }
}
