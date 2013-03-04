<?php
namespace Drupal\Installer;

abstract class Installer {
  protected $config;

  /**
   *  Constructs an Installer.
   *
   *  @param array
   *    Configuration from behat.yml.
   */
  public function __construct(array $config) {
    $this->config = $config;
  }

  /**
   * Initializes the Drupal installation. Depending on config, this may or may
   * not include either of the following:
   *  - dropping the existing database
   *  - installing a fresh application
   */
  abstract public function initialize();

  /**
   * Finalizes the Drupal installation. Depending on config this can include
   * uninstalling the database.
   */
  abstract public function finalize();

  /**
   * Return whether the Drupal installation been initialized.
   *
   * @return bool
   *
   * @see self::initialize()
   */
  abstract public function isInitialized();

  /**
   * Return whether the Drupal installation been finalized.
   *
   * @return bool
   *
   * @see self::finalize()
   */
  abstract public function isFinalized();
}
