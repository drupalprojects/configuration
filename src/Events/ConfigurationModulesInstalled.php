<?php

namespace Configuration\Events;

use Symfony\Component\EventDispatcher\Event;


class ConfigurationModulesInstalled extends Event
{
  protected $modules;

  public function __construct($modules) {
    $this->modules = $modules;
  }

  public function getModules() {
    return $this->modules;
  }
}
