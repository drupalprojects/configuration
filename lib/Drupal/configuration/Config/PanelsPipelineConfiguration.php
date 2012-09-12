<?php

/**
 * @file
 * Definition of Drupal\configuration\Config\PanelsConfiguration.
 */

namespace Drupal\configuration\Config;

use Drupal\configuration\Config\CtoolsConfiguration;

class PanelsPipelineConfiguration extends CtoolsConfiguration {

  // The name of the component that this class handles.
  static protected $component = 'panels_pipeline';

  // The table where the configurations are storaged.
  static protected $table = 'panels_pipeline';

  public function findRequiredModules() {
    $this->addToModules('panels');
  }
}
