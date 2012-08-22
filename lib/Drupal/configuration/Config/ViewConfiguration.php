<?php

/**
 * @file
 * Definition of Drupal\configuration\Config\ViewConfiguration.
 */

namespace Drupal\configuration\Config;

use Drupal\configuration\Config\CtoolsConfiguration;

class ViewConfiguration extends CtoolsConfiguration {

  // The name of the component that this class handles.
  static protected $component = 'view';

  // The table where the configurations are storaged.
  static protected $table = 'views_view';
}
