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

    // We get the module that creates the table for the view query.
    $schema = drupal_get_schema($view->base_table);
    $this->addToModules($schema['module']);

    foreach (views_object_types () as $type => $info) {
      foreach ($view->display as $display_id => $display) {
        // Views with a display provided by views_content module.
        if ($display->display_plugin == 'panel_pane') {
          $this->addToModules('views_content');
        }
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

      @eval(ctools_export_crud_export($config->getTable(), $config_data));

      $config_data = $handler;

      foreach ($config_data->conf['display']->content as $object) {
        $type = $object->type;
        switch ($type) {
          case 'block':
            list($subtype, $id, ) = explode('-', $object->subtype);
            switch ($subtype) {
              // Display block from a view.
              case 'views':
                $view = new ViewConfiguration($id);
                $view->build();
                $config->addToDependencies($view);
                break;
            }
            break;
          // A view added directly.
          case 'views':
            $view = new ViewConfiguration($object->subtype);
            $view->build();
            $config->addToDependencies($view);
            break;
          // A view added using the Views content panes module.
          case 'views_panes':
            list($subtype, ) = explode('-', $object->subtype);
            $view = new ViewConfiguration($subtype);
            $view->build();
            $config->addToDependencies($view);
            break;
        }
      }
    }
  }
}
