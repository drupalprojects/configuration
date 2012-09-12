<?php

/**
 * @file
 * Definition of Drupal\configuration\Config\PageManagerHandlerConfiguration.
 */

namespace Drupal\configuration\Config;

use Drupal\configuration\Config\CtoolsConfiguration;

class PageManagerHandlerConfiguration extends CtoolsConfiguration {

  // The name of the component that this class handles.
  static protected $component = 'page_manager_handler';

  // The table where the configurations are storaged.
  static protected $table = 'page_manager_handlers';

  public function findRequiredModules() {
    $this->addToModules('page_manager');
  }

  public static function alterDependencies(Configuration $config, &$stack) {
    // Dependencies for Page Manager Pages. Each page has a handler.
    if ($config->getComponent() == 'page_manager') {
      $config_data = $config->getData();
      $id = 'page_' . $config_data->name . '_panel_context';
      $page_handler = new PageManagerHandlerConfiguration($id);
      $page_handler->build();
      $config->addToDependencies($page_handler);
    }
  }

}
