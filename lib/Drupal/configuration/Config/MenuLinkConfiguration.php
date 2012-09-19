<?php

/**
 * @file
 * Definition of Drupal\configuration\Config\FieldConfiguration.
 */

namespace Drupal\configuration\Config;

use Drupal\configuration\Config\Configuration;
use Drupal\configuration\Utils\ConfigIteratorSettings;

class MenuLinkConfiguration extends Configuration {

  static protected $component = 'menulink';

  protected function prepareBuild() {
    list(, $mlid) = explode('.', $this->getIdentifier());
    $this->data = menu_link_load($mlid);
    return $this;
  }

  public function saveToActiveStore(ConfigIteratorSettings &$settings) {
    menu_link_save($this->getData());
    $settings->addInfo('imported', $this->getUniqueId());
  }

  /**
   * Returns all the identifiers available for this component.
   */
  public static function getAllIdentifiers() {
    global $menu_admin;
    // Need to set this to TRUE in order to get menu links that the
    // current user may not have access to (i.e. user/login)
    $menu_admin = TRUE;
    $menu_links = menu_parent_options(menu_get_menus(), array('mlid' => 0));
    $return = array();
    foreach ($menu_links as $key => $name) {
      list($menu_name, $mlid) = explode(':', $key, 2);
      if ($mlid != 0) {
        $menulink_name = str_replace(' ', '_', trim(str_replace(array('-', "'"), '', $name)));
        $return[] = $menu_name . '.' . $mlid . '.' . $menulink_name;
      }
    }
    $menu_admin = FALSE;
    return $return;
  }

  public static function alterDependencies(Configuration $config, &$stack) {
    if ($config->getComponent() == 'menulink') {
      list(, $mlid) = explode('.', $config->getIdentifier());
      $config_data = $config->getData();
      if ($config_data['plid'] > 0) {
        $menulinkarray = menu_link_load($config_data['plid']);
        $identifier = $menulinkarray['menu_name'] . '.' . $menulinkarray['mlid'] . '.' . str_replace(' ', '_', $menulinkarray['title']);
        $menulink = new MenuLinkConfiguration($identifier);
        $menulink->build();
        $config->addToDependencies($menulink);
      }
    }
  }

}
