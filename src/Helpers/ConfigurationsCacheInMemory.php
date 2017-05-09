<?php

namespace Configuration\Helpers;

use Configuration\Configuration;

class ConfigurationsCacheInMemory implements ConfigurationsCacheInterface {

  protected $cache;

  public function __construct() {
    $this->reset();
  }

  public function reset() {
    $this->cache = array();
  }

  public function contains($identifier) {
    return isset($this->cache[$identifier]);
  }

  public function get($identifier) {
    return $this->cache[$identifier];
  }

  public function getAll() {
    return $this->cache;
  }

  public function set(Configuration $configuration) {
    $this->cache[$configuration->getIdentifier()] = $configuration;
    return $this;
  }

  public function remove($identifier) {
    if (isset($this->cache[$identifier])) {
      unset($this->cache[$identifier]);
    }
    return $this;
  }

}
