<?php
namespace Drupal\Installer;

use Behat\Behat\Context\Initializer\InitializerInterface,
    Behat\Behat\Context\ContextInterface;

use Drupal\DrupalExtension\Context\DrupalContext;

use Symfony\Component\EventDispatcher\EventSubscriberInterface,
    Symfony\Component\Process\Process;

class Initializer implements InitializerInterface, EventSubscriberInterface
{
  protected $config;
  protected $shutdown_handler;

  protected static $initialized = FALSE;
  protected static $finalized = FALSE;
  protected static $enabled = TRUE;

  public function __construct(array $config, ShutdownHandler $shutdown_handler) {
    $this->config = $config;
    $this->shutdown_handler = $shutdown_handler;
    $this->checkEnabled();
  }

  /**
   * TODO: Ugly hack for avoiding installation when certain options specified.
   */
  protected function checkEnabled() {
    $disabled_options = array(
      '-d',
      '-dl',
      '--definitions',
      '--init',
      '--format',
      '-f',
      '--story-syntax',
    );
    $args = $_SERVER['argv'];
    self::$enabled = count(array_intersect($args, $disabled_options)) == 0;
  }

  /**
   * Implements EventSubscriberInterface::supports().
   */
  public function supports(ContextInterface $context) {
    return $context instanceof DrupalContext;
  }

  /**
   * Implements EventSubscriberInterface::getSubscribedEvents().
   */
  public static function getSubscribedEvents() {
    return array(
      'afterSuite' => array('finalizeDrupalInstallation', 15),
    );
  }

  /**
   * Tell Installer to finalize the Drupal Installation. This may include
   * uninstalling it completely, if so configured in behat.yml.
   */
  public function finalizeDrupalInstallation() {
    if (self::$finalized || !self::$enabled) {
      return FALSE;
    }

    if ($this->config['uninstall']) {
      $options = array(
        'drupal_root' => $this->config['drupal']['drupal_root'],
        'db_prefix' => $this->config['drupal']['db_prefix'],
        'switch_file' => $this->config['switch_file'],
      );

      $this->process('uninstall', $options, 30);
    }

    self::$finalized = TRUE;
  }

  /**
   * Installs Drupal if so configured in behat.yml.
   *
   * @see self::getSubscribedEvents()
   */
  public function initialize(ContextInterface $context) {
    if (self::$initialized || !self::$enabled) {
      return FALSE;
    }

    if ($this->config['install'] || $this->config['reinstall']) {
      $options = $this->config['drupal'] + array(
        'switch_file' => $this->config['switch_file'],
        'drop' => $this->config['reinstall'],
      );

      $this->process('install', $options);
    }

    if ($this->config['uninstall']) {
      $this->shutdown_handler->register()->skip();
    }

    self::$initialized = TRUE;
  }

  protected function process($command, $options, $timeout = NULL) {
    foreach ($options as $option => $value) {
      if ($value) {
        $command .= " --$option";
      }
      if (!is_bool($value)) {
        $command .= '=' . escapeshellarg($value);
      }
    }

    $process = new Process(__DIR__ . '/../../../bin/installer ' . $command);
    $process->setTimeout($timeout);
    $process->run(function($type, $buffer){
      if ('err' === $type) {
        throw new InstallerException($buffer);
      }
      echo $buffer;
    });
  }

}
