<?php
namespace Drupal\Installer;

use Drupal\Driver\Cores\Drupal7;

class Drupal7Installer extends Installer {

  protected $database;
  protected static $finalized = FALSE;
  protected static $initialized = FALSE;
  protected static $skip_shutdown_functions = FALSE;

  /**
   * Initializes the Drupal installation. Depending on config, this may or may
   * not include either of the following:
   *  - dropping the existing database
   *  - installing a fresh application
   */
  public function initialize() {
    if ($this->isInitialized()) {
      return FALSE;
    }

    $this->prepareInstall();

    $reinstall = !empty($this->config['reinstall'])
      && $this->config['reinstall'] == 'yes';

    $install = !empty($this->config['install'])
      && $this->config['install'] == 'yes'
      && count($this->getInstalledTables()) == 0;

    if ($reinstall) {
      $this->uninstallDrupal();
    }

    if ($install || $reinstall) {
      $this->installDrupal();
    }

    self::$initialized = TRUE;

    return TRUE;
  }

  /**
   * Finalizes the Drupal installation. Depending on config this can include
   * uninstalling the database.
   */
  public function finalize() {
    if (self::$finalized) {
      return FALSE;
    }

    if (!empty($this->config['uninstall']) && $this->config['uninstall'] == 'yes') {
      $this->uninstallDrupal();
      self::$skip_shutdown_functions = $finalize;
      unlink($this->config['switch_file']);
    }

    self::$finalized = TRUE;

    return self::$finalized;
  }

  /**
   * Return whether the Drupal installation been initialized.
   *
   * @return bool
   *
   * @see self::initialize()
   */
  public function isInitialized() {
    return self::$initialized;
  }

  /**
   * Return whether the Drupal installation been finalized.
   *
   * @return bool
   *
   * @see self::finalize()
   */
  public function isFinalized() {
    return self::$finalized;
  }

  /**
   * Bootstraps Drupal to the appropriate level for database installation, and
   * stores a reference to database config in $this->database.
   *
   * @uses drupal_bootstrap()
   * @uses drupal_register_shutdown_function()
   */
  protected function prepareInstall() {
    // Validate, and prepare environment for Drupal bootstrap.
    $drupal_config = $this->config['drupal'];
    $core = new Drupal7($drupal_config['drupal_root']);

    if (!defined('DRUPAL_ROOT')) {
      define('DRUPAL_ROOT', $drupal_config['drupal_root']);
      require_once DRUPAL_ROOT . '/includes/bootstrap.inc';
    }
    touch($this->config['switch_file']);

    // Bootstrap Drupal.
    chdir(DRUPAL_ROOT);
    $core->validateDrupalSite();
    drupal_bootstrap(DRUPAL_BOOTSTRAP_PAGE_CACHE);
    drupal_register_shutdown_function(array('Drupal\Installer\Installer', 'shutdown'));

    // These globals should now be available.
    global $databases, $conf;

    // If this variable is not set, then Drupal looks for blocked IPs in the
    // Database, and the blocked_ips table may not yet exist.
    $conf['blocked_ips'] = array();

    // This is how Drupal knows that an installation is taking place, so that
    // it doesn't try to query the database.
    define('MAINTENANCE_MODE', 'install');

    drupal_bootstrap(DRUPAL_BOOTSTRAP_DATABASE);

    $this->database = $databases['default']['default'];
  }

  protected function getInstalledTables() {
    $result = db_query('SHOW TABLES LIKE :prefix', array(':prefix' => $this->database['prefix'] . '%'));
    $tables = array();
    foreach ($result as $row) {
      $row = (array) $row;
      $tables[] = reset($row);
    }
    return $tables;
  }

  /**
   * Installs Drupal into the database setup by self::prepareInstall().
   *
   * @uses install_drupal().
   * @uses drupal_bootstrap().
   */
  protected function installDrupal() {
    $drupal_config = $this->config['drupal'];
    $driver = $this->database['driver'];

    $settings = array(
      'parameters' => array(
        'profile' => $drupal_config['install_profile'],
        'locale' => $drupal_config['locale'],
      ),
      'forms' => array(
        'install_settings_form' => array(
          'driver' => $driver,
          $driver => $this->database,
          'op' => t('Save and continue'),
        ),
        'install_configure_form' => array(
          'site_name' => $drupal_config['site_name'],
          'site_mail' => $drupal_config['account_mail'],
          'account' => array(
            'name' => $drupal_config['account_name'],
            'mail' => $drupal_config['account_mail'],
            'pass' => array(
              'pass1' => $drupal_config['account_pass'],
              'pass2' => $drupal_config['account_pass'],
            ),
          ),
          'update_status_module' => array(
            1 => TRUE,
            2 => TRUE,
          ),
          'clean_url' => TRUE,
          'op' => t('Save and continue'),
        ),
      ),
    );

    echo 'Installing...';
    require_once DRUPAL_ROOT . '/includes/install.core.inc';
    install_drupal($settings);
    echo "Done\n";

    drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
  }

  /**
   * Drops all Drupal database tables with the prefix configured in behat.yml
   * and in the site's settings.php.
   */
  protected function uninstallDrupal() {
    // Fail safe check before dropping all database tables!
    if (empty($this->config['drupal']['db_prefix'])) {
      throw new \Exception("There is no Drupal database table prefix configured"
        . " in behat.yml. You are not allowed to drop all tables in the"
        . " database.");
    }

    $prefix = $this->config['drupal']['db_prefix'];
    if ($this->database['prefix'] != $prefix) {
      throw new \Exception("The application's database prefix is not '"
        . $prefix . "'. We don't want to blow away ALL database"
        . " tables... right?!\nConfigure this in behat.yml and settings.php");
    }

    foreach ($this->getInstalledTables() as $table) {
      db_query('DROP TABLE ' . $table);
    }
  }

  /**
   * A shutdown function which gets registered before all others. When this
   * throws an Exception it will prevent any other shutdown functions from
   * being executed, which is useful because the shutdown functions Drupal
   * registers try to perform operations on database tables (eg. semaphore)
   * which will no longer exist if Drupal has been uninstalled.
   *
   * @see self::prepareInstall()
   */
  public static function shutdown() {
    if (!self::$skip_shutdown_functions) {
      return;
    }
    global $conf;
    $conf['error_level'] = 0;
    // By throwing an Exception here the subsequent registered shutdown
    // functions will not get executed.
    throw new \Exception("Skip shutdown functions");
  }

}
