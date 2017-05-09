<?php

namespace Configuration\Processors;

use Configuration\Configuration;

abstract class AbstractProcessor implements ProcessorInterface {

  protected $configuration_manager;
  protected $name;

  public function __construct($name, $configuration_manager) {
    $this->name = $name;
    $this->configuration_manager = $configuration_manager;
  }

  static public function availableProcessors() {
    return array();
  }

  abstract public function apply(Configuration $configuration, $properties = array());

  abstract public function revert(Configuration $configuration, $properties = array());

  static public function getSubscribedEvents() {
    return array();
  }

  public function getName() {
    return $this->name;
  }

}
