<?php

/**
 * @file
 * Definition of Drupal\configuration\Config\PageManagerHandlerConfiguration.
 */

namespace Drupal\configuration\Config;

use Drupal\configuration\Config\CtoolsConfiguration;

class PageManagerHandlerConfiguration extends CtoolsConfiguration {

  public static function isActive() {
    return module_exists('page_manager');
  }

  static public function getComponentHumanName($component, $plural = FALSE) {
    return $plural ? t('Page Manager Handlers') : t('Page Manage Handler');
  }

  public function getComponent() {
    return 'page_manager_handlers';
  }

  static public function supportedComponents() {
    return array('page_manager_handlers');
  }

  public static function alterDependencies(Configuration $config, &$stack) {
    // Dependencies for Page Manager Pages. Each page has a handler.
    if ($config->getComponent() == 'page_manager_pages' && !$config->broken) {
      $config_data = $config->getData();
      $id = 'page_manager_handlers.page_' . $config_data->name . '_panel_context';
      if (empty($stack[$id])) {
        $page_handler = ConfigurationManagement::createConfigurationInstace($id);
        $page_handler->build();
        $config->addToDependencies($page_handler);
        $stack[$id] = TRUE;
      }
    }
  }

}
