<?php

namespace Configuration;

use Configuration\Configuration;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

interface ConfigurationHandlerInterface extends EventSubscriberInterface {

  /**
   * Returns the types of configurations that this class can handle.
   *
   * @return array.
   */
  static public function getSupportedTypes();

  /**
   * Returns the configuration identifiers handled by this instance.
   *
   * @return array
   *   An array of identifiers.
   */
  public function getIdentifiers();

  /**
   * Loads the configuration from the database.
   *
   * @param string $identifier
   *   The identifier of the configuration to load.
   *
   * @return \Configuration\Configuration
   *   A configuration object.
   */
  public function loadFromDatabase($identifier);

  /**
   * Saves the given configuration into the database.
   *
   * @param  \Configuration\Configuration $configuration
   *   The configuration to be saved.
   */
  public function writeToDatabase(Configuration $configuration);

  /**
   * Deletes a configuration from the database.
   *
   * @param  \Configuration\Configuration $configuration
   *   The configuration to be deleted.
   */
  public function removeFromDatabase(Configuration $configuration);

}
