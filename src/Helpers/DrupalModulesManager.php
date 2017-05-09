<?php

namespace Configuration\Helpers;

class DrupalModulesManager {

  protected $modules;
  protected $to_enable;
  protected $enabled;
  protected $missing;

  public function __construct() {
    $this->configuration_manager = $configuration_manager;
    $this->reset();
  }

  public function reset() {
    $this->modules = array();
    $this->to_enable = array();
    $this->enabled = array();
    $this->missing = array();
  }

  public function enableModules($modules = array()) {
    if (empty($modules)) {
      $modules = $this->to_enable;
    }
    module_enable($modules, TRUE);
    return $modules;
  }

  public function disableModules($modules) {
    module_disable($modules);
  }

  public function findDependencies($modules) {
    $status = array();
    $this->getAllModules();
    $not_proccesed_yet = $modules;

    while (count($not_proccesed_yet) > 0) {
      $module = array_pop($not_proccesed_yet);
      if (!empty($status[$module])) {
        continue;
      }

      if (!isset($this->modules[$module])) {
        $this->missing[] = $module;
      }
      else {
        if (!empty($this->modules[$module]->status)) {
          $this->enabled[] = $module;
        }
        else {
          $this->to_enable[] = $module;
        }

        // Add the dependencies of the current module to discover new dependencies
        foreach ($this->modules[$module]->requires as $dependency => $value) {
          if (!isset($this->modules[$dependency])) {
            $not_proccesed_yet[] = $dependency;
          }
        }
      }
    }
    return $status;
  }

  public function getAllModules($rebuild = FALSE) {
    if (empty($this->modules) || $rebuild) {
      $files = system_rebuild_module_data();
      foreach ($files as $id => $file) {
        if ($file->type == 'module' && empty($file->info['hidden'])) {
          $this->modules[$id] = $file;
        }
      }
    }
    return $this->modules;
  }

  public function toInstall() {
    return $this->to_enable;
  }

  public function missing() {
    return $this->missing;
  }

}
