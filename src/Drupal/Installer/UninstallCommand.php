<?php
namespace Drupal\Installer;

use Symfony\Component\Console\Command\Command,
    Symfony\Component\Console\Input\InputArgument,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console\Output\OutputInterface;

class UninstallCommand extends Command {
  protected function configure() {
    $this
      ->setName('uninstall')
      ->setDescription('Uninstall Drupal')
      ->addOption('drupal_root', null, InputOption::VALUE_REQUIRED, 'The docroot of the Drupal site')
      ->addOption('db_prefix', null, InputOption::VALUE_REQUIRED, 'The locale of the site to install')
      ->addOption('switch_file', null, InputOption::VALUE_REQUIRED, 'The switch file to tell Drupal to use it\'s behat profile');
    ;
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $options = $input->getOptions();

    $missing = array();
    foreach ($options as $option => $value) {
      if (is_null($value)) {
        $missing[] = $option;
      }
    }

    if (count($missing) > 0) {
      throw new InstallerException("Missing required options: " . implode(', ', $missing));
    }

    $output->write("Uninstalling Drupal...");

    $installer = new Drupal7Installer($options);

    if (!$installer->isInstalled()) {
      $output->write("Already uninstalled\n");
      return TRUE;
    }

    $installer->uninstall();

    unlink($options['switch_file']);

    $output->write("Done\n");
  }
}
