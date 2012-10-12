<?php

/**
 * @file
 * Definition of Drupal\configuration\Config\MenuConfiguration.
 */

namespace Drupal\configuration\Config;

use Drupal\configuration\Config\Configuration;
use Drupal\configuration\Utils\ConfigIteratorSettings;

class MenuConfiguration extends Configuration {

  static protected $component = 'menu';

  protected function prepareBuild() {
    $this->data = menu_load($this->getIdentifier());
    return $this;
  }

  public function saveToActiveStore(ConfigIteratorSettings &$settings) {
    menu_save($this->getData());
    $settings->addInfo('imported', $this->getUniqueId());
  }

  /**
   * Returns all the identifiers available for this component.
   */
  public static function getAllIdentifiers($component) {
    $menus = db_query("SELECT menu_name FROM {menu_custom}")->fetchAll();
    $return = array();
    foreach ($menus as $menu) {
      $return[] = $menu->menu_name;
    }
    return $return;
  }

  public static function alterDependencies(Configuration $config, &$stack) {
    if ($config->getComponent() == 'menulink') {
      $config_data = $config->getData();
      if ($config_data['plid'] == 0) {
        $identifier = current(explode('.', $config->getIdentifier()));
        $menuarray = menu_load($identifier);
        $menu = new MenuConfiguration($identifier);
        $menu->build();
        $config->addToDependencies($menu);
      }
    }
  }

}
