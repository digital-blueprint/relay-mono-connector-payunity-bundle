<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\DependencyInjection;

use Dbp\Relay\CoreBundle\Extension\ExtensionTrait;
use Dbp\Relay\MonoConnectorPayunityBundle\Config\ConfigurationService;
use Dbp\Relay\MonoConnectorPayunityBundle\Controller\Widget;
use Dbp\Relay\MonoConnectorPayunityBundle\Service\PayunityService;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

class DbpRelayMonoConnectorPayunityExtension extends ConfigurableExtension implements PrependExtensionInterface
{
    use ExtensionTrait;

    public function loadInternal(array $mergedConfig, ContainerBuilder $container)
    {
        $this->addResourceClassDirectory($container, __DIR__.'/../Entity');
        $this->addPathToHide($container, '/mono-connector-payunity/widget/index');

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../Resources/config')
        );
        $loader->load('services.yaml');

        $this->addRouteResource($container, __DIR__.'/../Resources/config/routing.yaml', 'yaml');

        $definition = $container->getDefinition(ConfigurationService::class);
        $definition->addMethodCall('setConfig', [$mergedConfig]);

        $definition = $container->getDefinition(PayunityService::class);
        $definition->addMethodCall('setConfig', [$mergedConfig]);

        $definition = $container->getDefinition(Widget::class);
        $definition->addMethodCall('setConfig', [$mergedConfig]);
    }

    public function prepend(ContainerBuilder $container)
    {
        $configs = $container->getExtensionConfig($this->getAlias());
        $config = $this->processConfiguration(new Configuration(), $configs);

        foreach (['doctrine', 'doctrine_migrations'] as $extKey) {
            if (!$container->hasExtension($extKey)) {
                throw new \Exception("'".$this->getAlias()."' requires the '$extKey' bundle to be loaded!");
            }
        }

        $container->prependExtensionConfig('doctrine', [
            'dbal' => [
                'connections' => [
                    'dbp_relay_mono_connector_payunity' => [
                        'url' => $config['database_url'] ?? '',
                    ],
                ],
            ],
            'orm' => [
                'entity_managers' => [
                    'dbp_relay_mono_connector_payunity_bundle' => [
                        'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware',
                        'connection' => 'dbp_relay_mono_connector_payunity',
                        'mappings' => [
                            'dbp_relay_mono_connector_payunity' => [
                                'type' => 'annotation',
                                'dir' => __DIR__.'/../Entity',
                                'prefix' => 'Dbp\Relay\MonoConnectorPayunityBundle\Entity',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->registerEntityManager($container, 'dbp_relay_mono_connector_payunity_bundle');

        $container->prependExtensionConfig('doctrine_migrations', [
            'migrations_paths' => [
                'Dbp\Relay\MonoConnectorPayunityBundle\Migrations' => __DIR__.'/../Migrations',
            ],
        ]);
    }
}
