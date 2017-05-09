<?php

namespace Configuration\Events;

use Symfony\Component\EventDispatcher\Event;
use Configuration\Configuration;

use PhpCollection\Map;

class ConfigurationCRUDEvent extends Event
{
  public $configuration;

  public function __construct(Configuration $configuration, $settings = array()) {
    $this->configuration = $configuration;
    $this->settings = new Map($settings);
  }

  public function getSetting($key) {
    if ($this->settings->containsKey($key)) {
      return $this->settings->get($key)->get();
    }
  }

  public function setSetting($key, $value) {
    $this->settings->set($key, $value);
  }
}
