<?php

namespace Configuration\Processors;

use Configuration\Configuration;

class MetadataProcessor extends AbstractProcessor {

  static public function availableProcessors() {
    return array(
      'AddDependencies',
      'RemoveDependencies',
      'AddParts',
      'RemoveParts',
      'AddModules',
      'RemoveModules',
    );
  }

  public function apply(Configuration $configuration, $properties = array()) {
    switch ($this->getName()) {
      case 'AddDependencies':
        foreach ($properties as $dependency) {
          $this->configuration_manager->newDependency($configuration, $dependency);
        }
        break;
      case 'AddParts':
        foreach ($properties as $part) {
          $this->configuration_manager->newPart($configuration, $dependency);
        }
        break;
      case 'AddModules':
        foreach ($properties as $module) {
          $configuration->addModule($module);
        }
        break;

      case 'RemoveDependencies':
        foreach ($properties as $dependency) {
          $configuration->removeDependency($dependency);
        }
        break;
      case 'RemoveParts':
        foreach ($properties as $part) {
          $configuration->removePart($part);
        }
        break;
      case 'RemoveModules':
        foreach ($properties as $module) {
          $configuration->removeModule($module);
        }
        break;
    }
  }

  public function revert(Configuration $configuration, $properties = array()) {
    // Nothing to do. This proccesors are not reversible.
  }

}
