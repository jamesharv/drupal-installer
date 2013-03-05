<?php
namespace Drupal\Installer;

class ShutdownHandler {
  protected $skip;
  protected $drupal_root;

  public function __construct($drupal_root, $skip = FALSE) {
    $this->skip($skip);
    $this->drupal_root = $drupal_root;
  }

  public function register() {
    require_once $this->drupal_root . '/includes/bootstrap.inc';
    drupal_register_shutdown_function(array($this, 'shutdown'));
    return $this;
  }

  public function skip($skip = TRUE) {
    $this->skip = (bool) $skip;
    return $this;
  }

  /**
   * A shutdown function which gets registered before all others. When this
   * throws an Exception it will prevent any other shutdown functions from
   * being executed, which is useful because the shutdown functions Drupal
   * registers try to perform operations on database tables (eg. semaphore)
   * which will no longer exist if Drupal has been uninstalled.
   */
  public function shutdown() {
    global $conf;
    if ($this->skip) {
      $conf['error_level'] = 0;
      // By throwing an Exception here the subsequent registered shutdown
      // functions will not get executed.
      throw new \Exception("Skip shutdown functions");
    }
  }
}
