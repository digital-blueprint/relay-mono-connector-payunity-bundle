<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('dbp_relay_mono_connector_payunity');

        $treeBuilder
            ->getRootNode()
                ->children()
                    ->scalarNode('database_url')
                        ->isRequired()
                        ->cannotBeEmpty()
                        ->defaultValue('%env(resolve:DATABASE_URL)%')
                    ->end()
                    ->arrayNode('payment_contracts')
                        ->isRequired()
                        ->cannotBeEmpty()
                        ->arrayPrototype()
                            ->children()
                                ->scalarNode('api_url')
                                ->end()
                                ->scalarNode('entity_id')
                                ->end()
                                ->scalarNode('access_token')
                                ->end()
                                ->enumNode('test_mode')
                                    // See https://www.payunity.com/reference/parameters#testing
                                    ->values(['internal', 'external'])
                                    ->defaultNull()
                                ->end()
                                ->arrayNode('payment_methods_to_widgets')
                                    ->isRequired()
                                    ->cannotBeEmpty()
                                    ->arrayPrototype()
                                    ->children()
                                        ->scalarNode('widget_url')
                                        ->end()
                                        ->scalarNode('template')
                                        ->end()
                                        ->scalarNode('brands')
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
