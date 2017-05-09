<?php

namespace Configuration\Helpers;

use Configuration\Configuration;

interface ConfigurationsCacheInterface {

  public function reset();

  public function contains($identifier);

  public function get($identifier);

  public function getAll();

  public function set(Configuration $configuration);

  public function remove($identifier);

}
