<?php

namespace Configuration\Handlers;

use Configuration\Configuration;

class ConfigurationProxy {

  protected $identifier;
  protected $handler;
  protected $configuration_manager;

  function __construct($identifier, $handler, $configuration_manager, $configuration = NULL) {
    $this->identifier = $identifier;
    $this->handler = $handler;
    $this->configuration_manager = $configuration_manager;
    $this->configuration = $configuration;
  }

  public function load() {
    if (empty($this->configuration)) {
      $this->configuration = $this->handler->loadFromDatabase($this->identifier);
    }
    $this->configuration_manager->cache()->set($this->configuration);
    return $this->configuration;
  }

  public function write(Configuration $configuration) {
    return $this->handler->writeToDatabase($configuration);
  }

  public function remove(Configuration $configuration) {
    return $this->handler->removeFromDatabase($configuration);
  }

  public function export() {
    if (empty($this->configuration)) {
      $this->configuration = $this->handler->loadFromDatabase($this->identifier);
    }
    $this->configuration_manager->cache()->set($this->configuration);
    return $this->handler->export($this->configuration);
  }

  public function handler() {
    return $this->handler;
  }

  public function configuration() {
    return $this->configuration;
  }
}
