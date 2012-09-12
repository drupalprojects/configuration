<?php

/**
 * @file
 * Definition of Drupal\configuration\Config\PanelsMiniConfiguration.
 */

namespace Drupal\configuration\Config;

use Drupal\configuration\Config\CtoolsConfiguration;

class PanelsMiniConfiguration extends CtoolsConfiguration {

  // The name of the component that this class handles.
  static protected $component = 'panels_mini';

  // The table where the configurations are storaged.
  static protected $table = 'panels_mini';

  public function findRequiredModules() {
    $this->addToModules('panels_mini');
  }
}
