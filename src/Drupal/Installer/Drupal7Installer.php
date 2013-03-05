<?php
namespace Drupal\Installer;

use Drupal\Driver\Cores\Drupal7;

class Drupal7Installer extends Installer {

  protected $database;
  protected $shutdown_handler;
  protected $bootstrapped = FALSE;

  public function __construct($config) {
    parent::__construct($config);
    $this->shutdown_handler = new ShutdownHandler($this->config['drupal_root']);
    $this->shutdown_handler->register();
  }

  /**
   * Bootstraps Drupal to the appropriate level for database installation, and
   * stores a reference to database config in $this->database.
   *
   * @uses drupal_bootstrap()
   */
  protected function bootstrap() {
    if ($this->bootstrapped) {
      return;
    }

    touch($this->config['switch_file']);

    // Validate, and prepare environment for Drupal bootstrap.
    $core = new Drupal7($this->config['drupal_root']);

    if (!defined('DRUPAL_ROOT')) {
      define('DRUPAL_ROOT', $this->config['drupal_root']);
      require_once DRUPAL_ROOT . '/includes/bootstrap.inc';
    }

    // Bootstrap Drupal.
    chdir(DRUPAL_ROOT);
    $core->validateDrupalSite();
    drupal_bootstrap(DRUPAL_BOOTSTRAP_PAGE_CACHE);

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

    $this->bootstrapped = TRUE;
  }

  protected function getInstalledTables() {
    $this->bootstrap();

    $result = db_query('SHOW TABLES LIKE :prefix', array(':prefix' => $this->database['prefix'] . '%'));
    $tables = array();
    foreach ($result as $row) {
      $row = (array) $row;
      $tables[] = reset($row);
    }
    return $tables;
  }

  /**
   * Returns whether Drupal is already installed.
   *
   * @return bool
   */
  public function isInstalled() {
    return count($this->getInstalledTables()) > 0;
  }

  /**
   * Installs Drupal into the database.
   *
   * @param bool $drop
   *  Whether or not to drop an existing installation before install.
   *
   * @uses install_drupal().
   * @uses drupal_bootstrap().
   */
  public function install() {
    $this->bootstrap();

    $driver = $this->database['driver'];

    $settings = array(
      'parameters' => array(
        'profile' => $this->config['install_profile'],
        'locale' => $this->config['locale'],
      ),
      'forms' => array(
        'install_settings_form' => array(
          'driver' => $driver,
          $driver => $this->database,
          'op' => t('Save and continue'),
        ),
        'install_configure_form' => array(
          'site_name' => $this->config['site_name'],
          'site_mail' => $this->config['account_mail'],
          'account' => array(
            'name' => $this->config['account_name'],
            'mail' => $this->config['account_mail'],
            'pass' => array(
              'pass1' => $this->config['account_pass'],
              'pass2' => $this->config['account_pass'],
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

    require_once DRUPAL_ROOT . '/includes/install.core.inc';
    install_drupal($settings);

    drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
  }

  /**
   * Drops all Drupal database tables with the prefix configured in behat.yml
   * and in the site's settings.php.
   */
  public function uninstall() {
    $this->bootstrap();
    $this->shutdown_handler->skip();

    // Fail safe check before dropping all database tables!
    if (empty($this->config['db_prefix'])) {
      throw new \Exception("There is no Drupal database table prefix configured"
        . " in behat.yml. You are not allowed to drop all tables in the"
        . " database.");
    }

    $prefix = $this->config['db_prefix'];
    if ($this->database['prefix'] != $prefix) {
      throw new \Exception("The application's database prefix is not '"
        . $prefix . "'. We don't want to blow away ALL database"
        . " tables... right?!\nConfigure this in behat.yml and settings.php");
    }

    foreach ($this->getInstalledTables() as $table) {
      db_query('DROP TABLE ' . $table);
    }

    unlink($this->config['switch_file']);
  }
}
