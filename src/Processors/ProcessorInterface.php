<?php

namespace Configuration\Processors;

use Configuration\Configuration;

interface ProcessorInterface {

  static public function availableProcessors();

  public function apply(Configuration $configuration, $properties = array());

  public function revert(Configuration $configuration, $properties = array());

}
