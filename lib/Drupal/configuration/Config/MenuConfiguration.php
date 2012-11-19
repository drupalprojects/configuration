<?php

/**
 * @file
 * Definition of Drupal\configuration\Config\MenuConfiguration.
 */

namespace Drupal\configuration\Config;

use Drupal\configuration\Config\Configuration;
use Drupal\configuration\Utils\ConfigIteratorSettings;

class MenuConfiguration extends Configuration {

  protected function prepareBuild() {
    $this->data = menu_load(str_replace('_', '-', $this->getIdentifier()));
    return $this;
  }

  public static function isActive() {
    return module_exists('menu');
  }

  static public function getComponentHumanName($component, $plural = FALSE) {
    return $plural ? t('Menus') : t('Menu');
  }

  public function getComponent() {
    return 'menu';
  }

  static public function supportedComponents() {
    return array('menu');
  }

  public function saveToActiveStore(ConfigIteratorSettings &$settings) {
    menu_save($this->getData());
    $settings->addInfo('imported', $this->getUniqueId());
  }

  /**
   * Returns all the identifiers available for this component.
   */
  public static function getAllIdentifiers($component) {
    $menus = db_query("SELECT menu_name, title FROM {menu_custom}")->fetchAll();
    $return = array();
    foreach ($menus as $menu) {
      $return[str_replace('-', '_', $menu->menu_name)] = $menu->title;
    }
    return $return;
  }

  public static function alterDependencies(Configuration $config, &$stack) {
    if ($config->getComponent() == 'menu_link') {
      $config_data = $config->getData();
      if (!empty($config_data) && empty($config_data['plid'])) {
        $menu = new MenuConfiguration(str_replace('-', '_', $config_data['menu_name']));
        $menu->build();
        $config->addToDependencies($menu);
      }
    }
  }

}
