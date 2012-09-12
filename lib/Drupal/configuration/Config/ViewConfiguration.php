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

  public function findRequiredModules() {
    $this->addToModules('views');
    $view = $this->getData();
    foreach (views_object_types() as $type => $info) {
      foreach ($view->display as $display_id => $display) {
        $view->set_display($display_id);
        foreach ($view->display_handler->get_handlers($type) as $handler_id => $handler) {
          if ($type == 'field') {
            if (!empty($handler->field_info) && !empty($handler->field_info['module'])) {
              $this->addToModules($handler->field_info['module']);
            }
          }
        }
      }
    }
  }

  public static function alterDependencies(Configuration $config, &$stack) {
    // Dependencies for Page Manager Handlers.
    if ($config->getComponent() == 'page_manager_handler') {

      // This line seems to be inconsistent when executed from drush or browser.
      $config_data = $config->getData();

      // This alternative works more consistent althoug it's no so pretty.
      eval(ctools_export_crud_export($config->getTable(), $config_data));
      $config_data = $handler;

      foreach ($config_data->conf['display']->content as $object) {
        list($type, $id, ) = explode('-', $object->subtype);
        if ($type == 'views') {
          $view = new ViewConfiguration($id);
          $view->build();
          $config->addToDependencies($view);
        }
      }
    }
  }
}
