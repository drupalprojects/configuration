<?php

/**
 * @file
 * Definition of Drupal\configuration\Config\PanelsConfiguration.
 */

namespace Drupal\configuration\Config;

use Drupal\configuration\Config\CtoolsConfiguration;

class PanelsConfiguration extends CtoolsConfiguration {

  // The name of the component that this class handles.
  static protected $component = 'panels';

  // The table where the configurations are storaged.
  static protected $table = 'panels_layout';

  public function findRequiredModules() {
    $this->addToModules('panels');
  }
}
