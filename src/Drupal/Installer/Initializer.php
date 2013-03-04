<?php
namespace Drupal\Installer;

use Behat\Behat\Context\Initializer\InitializerInterface,
    Behat\Behat\Context\ContextInterface;

use Drupal\DrupalExtension\Context\DrupalContext;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class Initializer implements InitializerInterface, EventSubscriberInterface
{
  protected $installer;

  public function __construct(Installer $installer) {
    $this->installer = $installer;
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
    if (!$this->installer->isFinalized()) {
      $this->installer->finalize();
    }
  }

  /**
   * Installs Drupal if so configured in behat.yml.
   *
   * @see self::getSubscribedEvents()
   */
  public function initialize(ContextInterface $context) {
    if (!$this->installer->isInitialized()) {
      $this->installer->initialize();
    }
  }

}
