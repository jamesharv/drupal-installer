<?php
namespace Drupal\Installer;

use Symfony\Component\Console\Command\Command,
    Symfony\Component\Console\Input\InputArgument,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console\Output\OutputInterface;

class InstallCommand extends Command {
  protected function configure() {
    $this
      ->setName('install')
      ->setDescription('Install Drupal')
      ->addOption('drupal_root', null, InputOption::VALUE_REQUIRED, 'The docroot of the Drupal site')
      ->addOption('db_prefix', null, InputOption::VALUE_REQUIRED, 'The locale of the site to install')
      ->addOption('switch_file', null, InputOption::VALUE_REQUIRED, 'The switch file to tell Drupal to use it\'s behat profile')
      ->addOption('drop', null, InputOption::VALUE_NONE, 'If set, the task will drop all tables before installing.')
      ->addOption('site_name', null, InputOption::VALUE_REQUIRED, 'The site name', 'Site Install')
      ->addOption('account_name', null, InputOption::VALUE_REQUIRED, 'user#1 account name', 'admin')
      ->addOption('account_pass', null, InputOption::VALUE_REQUIRED, 'user#1 account password', 'admin')
      ->addOption('account_mail', null, InputOption::VALUE_REQUIRED, 'user#1 account mail', 'admin@example.com')
      ->addOption('install_profile', null, InputOption::VALUE_REQUIRED, 'Installation profile to use', 'standard')
      ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'The locale of the site to install', 'en');
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

    $output->write("Installing Drupal...");

    touch($options['switch_file']);

    $installer = new Drupal7Installer($options);

    if ($options['drop'] && $installer->isInstalled()) {
      $installer->uninstall();
    }

    if (!$installer->isInstalled()) {
      $installer->install();
      $output->write("Done\n");
    }
    else {
      $output->write("Drupal is already installed\n");
    }
  }
}
