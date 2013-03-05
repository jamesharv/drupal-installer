<?php
namespace Drupal\Installer;

abstract class Installer {

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
   * Installs Drupal.
   *
   * @param bool $drop
   *  Whether or not to drop an existing installation before installing.
   */
  abstract public function install();

  /**
   * Returns whether Drupal is already installed.
   *
   * @return bool
   */
  abstract public function isInstalled();

  /**
   * Uninstalls Drupal.
   */
  abstract public function uninstall();

}
