<?php

namespace Configuration;

use \StdClass;
use PhpCollection\Map;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * A generic configuration of the site.
 */
class Configuration {

  // The unique identifier to identify this configuration.
  protected $identifier = "";

  // The group where this config is organized.
  protected $group = "";

  // Internal notes to explain what this configuration do.
  protected $notes = "";

  // Tags to categorize this configuration.
  protected $tags = "";

  // A Map of configurations that must exists before be able to load this
  // configuration.
  protected $dependencies;

  // If this configuration is a container that groups others configurations.
  // (This is the reverse of a dependency).
  protected $parts;

  // Required Modules to run this configuration.
  protected $modules;

  // Additional Modules that this configuration will require.
  // This additional module list is not automatically populated by the system.
  // And values defined here will be respected when importing and exporting.
  protected $additional_modules;

  // The configuration values.
  protected $data;

  // A PropertyAccess object.
  protected $property_accessor;

  function __construct() {
    $this->dependencies = new Map();
    $this->parts = new Map();
    $this->modules = new Map();
    $this->additional_modules = new Map();
    $this->group = 'none';
  }

  /* Getters */

  public function getIdentifier() {
    return $this->identifier;
  }

  public function getGroup() {
    return $this->group;
  }

  public function getNotes() {
    return $this->notes;
  }

  public function getTags() {
    return $this->tags;
  }

  public function getDependencies() {
    return $this->dependencies->keys();
  }

  public function getParts() {
    return $this->parts->keys();
  }

  public function getModules() {
    return $this->modules->keys();
  }

  public function getAdditionalModules() {
    return $this->additional_modules->keys();
  }

  public function getData() {
    return $this->data;
  }

  /** Setters **/

  public function setIdentifier($identifier) {
    $this->identifier = $identifier;
    return $this;
  }

  public function setGroup($group) {
    $this->group = $group;
    return $this;
  }

  public function setNotes($notes) {
    $this->notes = $notes;
    return $this;
  }

  public function setTags($tags) {
    $this->tags = $tags;
    return $this;
  }

  public function addDependency($identifier) {
    $this->dependencies->set($identifier, 0);
    return $this;
  }

  public function removeDependency($identifier) {
    if ($this->dependencies->contains($identifier)) {
      $this->dependencies->remove($identifier, 0);
    }
    return $this;
  }

  public function setDependencies($dependencies = array()) {
    $this->dependencies->setAll($dependencies);
    return $this;
  }

  public function addPart($identifier) {
    $this->parts->set($identifier, 0);
    return $this;
  }

  public function removePart($identifier) {
    if ($this->parts->contains($identifier)) {
      $this->parts->remove($identifier, 0);
    }
    return $this;
  }

  public function setParts($parts = array()) {
    $this->parts->setAll($parts);
    return $this;
  }

  public function addModule($module) {
    $this->modules->set($module, 0);
  }

  public function removeModule($identifier) {
    if ($this->modules->contains($identifier)) {
      $this->modules->remove($identifier, 0);
    }
    return $this;
  }

  public function setModules($modules = array()) {
    $this->modules->setAll($modules);
    return $this;
  }

  public function addAdditionalModule($module) {
    $this->additional_modules->set($module, 0);
  }

  public function setAdditionalModules($modules = array()) {
    $this->additional_modules->setAll($modules);
    return $this;
  }

  public function setData($data) {
    $this->data = $data;
    return $this;
  }

  /** Other methods **/

  /**
   * Returns the name that storage this configuration.
   */
  public function getFileName() {
    return $this->getIdentifier() . '.json';
  }

  /**
   * Returns the type of this configuration.
   */
  public function getType() {
    return substr($this->getIdentifier(), 0, strpos($configuration->getIdentifier(), '.'));
  }


  public function toObject() {
    $object = new \stdClass;
    $object->identifier = $this->identifier;
    $object->notes = $this->notes;
    $object->tags = $this->tags;
    $object->dependencies = $this->dependencies->keys();
    $object->parts = $this->parts->keys();
    $object->modules = $this->modules->keys();
    $object->data = $this->data;
    return $object;
  }

  public function fromObject($object) {
    $this->setIdentifier($object->identifier);
    $this->setNotes($object->notes);
    $this->setTags($object->tags);

    $dependencies = array();
    foreach ($object->dependencies as $dependency) {
      $dependencies[$dependency] = TRUE;
    }
    $this->setDependencies($dependencies);

    $parts = array();
    foreach ($object->parts as $part) {
      $parts[$part] = TRUE;
    }
    $this->setParts($parts);

    $modules = array();
    foreach ($object->modules as $module) {
      $modules[$module] = TRUE;
    }
    $this->setModules($modules);

    $this->setData($object->data);
  }

  public function getValue($property) {
    if (!isset($this->property_accessor)) {
      $this->property_accessor = PropertyAccess::createPropertyAccessor();
    }
    return $this->property_accessor->getValue($this->data, $property);
  }

  public function setValue($property, $value) {
    if (!isset($this->property_accessor)) {
      $this->property_accessor = PropertyAccess::createPropertyAccessor();
    }
    return $this->property_accessor->setValue($this->data, $property, $value);
  }
}
