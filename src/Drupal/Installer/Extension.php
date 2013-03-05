<?php
namespace Drupal\Installer;

use Symfony\Component\DependencyInjection\ContainerBuilder,
    Symfony\Component\DependencyInjection\Loader\YamlFileLoader,
    Symfony\Component\Config\FileLocator,
    Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

class Extension extends \Behat\Behat\Extension\Extension {

  public function load(array $config, ContainerBuilder $container) {
    $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/config'));
    $loader->load('services.yml');
    $container->setParameter('drupal_installer.config', $config);
    $container->setParameter('drupal_installer.drupal_root', $config['drupal']['drupal_root']);
  }

  /**
   * Setup configuration for this extension.
   *
   * @param ArrayNodeDefinition $builder
   *   ArrayNodeDefinition instance.
   */
  public function getConfig(ArrayNodeDefinition $builder) {
    $builder->
      children()->
        arrayNode('drupal')->
          children()->
            scalarNode('install_profile')->
              isRequired()->
            end()->
            scalarNode('drupal_root')->
              isRequired()->
            end()->
            scalarNode('site_name')->
              defaultValue('Site Install')->
              isRequired()->
            end()->
            scalarNode('account_name')->
              defaultValue('admin')->
              isRequired()->
            end()->
            scalarNode('account_pass')->
              defaultValue('admin')->
              isRequired()->
            end()->
            scalarNode('account_mail')->
              defaultValue('admin@example.com')->
              isRequired()->
            end()->
            scalarNode('locale')->
              defaultValue('en')->
              isRequired()->
            end()->
            scalarNode('db_prefix')->
              defaultValue('behat_')->
              isRequired()->
            end()->
          end()->
        end()->
        scalarNode('switch_file')->
          isRequired()->
        end()->
        booleanNode('install')->
          defaultValue(TRUE)->
        end()->
        booleanNode('reinstall')->
          defaultValue(TRUE)->
        end()->
        booleanNode('uninstall')->
          defaultValue(FALSE)->
        end()->
      end()->
    end();
  }

}

return new Extension();
