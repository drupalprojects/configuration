<?php

/**
 * @file
 * Definition of Drupal\configuration\Config\PageManagerConfiguration.
 */

namespace Drupal\configuration\Config;

use Drupal\configuration\Config\CtoolsConfiguration;

class PageManagerConfiguration extends CtoolsConfiguration {

  // The name of the component that this class handles.
  static protected $component = 'page_manager';

  // The table where the configurations are storaged.
  static protected $table = 'page_manager_pages';

  public function findRequiredModules() {
    $this->addToModules('page_manager');
  }
}
