<?php

namespace Configurations\Helpers;

use Configuration\Configuration;

class ConfigurationsCacheInDatabase implements ConfigurationsCacheInterface {

  protected $cache;
  protected $prefix;
  protected $identifiers;

  public function __construct($prefix) {
    $this->prefix = $prefix . '.';
    $this->cache_table = 'cache_cmapi_configurations';
  }

  public function reset() {
    $this->identifiers = array();
    cache_clear_all(NULL, $this->cache_table, $this->prefix);
  }

  public function contains($identifier) {
    return isset($this->indentifiers[$this->prefix . $identifier]);
  }

  public function get($identifier) {
    return cache_get($this->prefix . $identifier, $this->cache_table) !== NULL;
  }

  public function getAll() {
    return cache_get_multiple($this->identifiers, $this->cache_table);
  }

  public function set(Configuration $configuration) {
    $idenfitifer = $this->prefix . $configuration->getIdentifier();
    if (!isset($this->indentifiers[$identifier])) {
      $this->indentifiers[$identifier] = $identifier;
    }
    cache_set($identifier, $configuration, $this->cache_table);
    return $this;
  }

  public function remove($identifier) {
    if (isset($this->idenfitifers[$this->prefix . $identifier])) {
      unset($this->idenfitifers[$this->prefix . $identifier]);
      cache_clear_all($this->prefix . $identifier, $this->cache_table);
    }
    return $this;
  }

}
