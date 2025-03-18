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
                        ->info('The database DSN')
                        ->isRequired()
                        ->defaultValue('%env(resolve:DATABASE_URL)%')
                    ->end()
                    ->arrayNode('payment_contracts')
                        ->info('Zero or more payment contracts. The "payment_contract" can be referenced in the "mono" config.')
                        ->useAttributeAsKey('payment_contract')
                        ->defaultValue([])
                        ->arrayPrototype()
                            ->children()
                                ->scalarNode('api_url')
                                    ->info('The PayUnity API endpoint.')
                                    ->example('https://eu-test.oppwa.com')
                                    ->isRequired()
                                ->end()
                                ->scalarNode('entity_id')
                                    ->info('The entityId provided by PayUnity')
                                    ->isRequired()
                                ->end()
                                ->scalarNode('access_token')
                                    ->info('The access token provided by PayUnity')
                                    ->isRequired()
                                ->end()
                                ->scalarNode('webhook_secret')
                                    ->info('The WebHook secret provided by PayUnity')
                                    ->defaultNull()
                                ->end()
                                ->enumNode('test_mode')
                                    ->info('If an internal or external test system should be used. Only allowed to be set with the test server.')
                                    // See https://www.payunity.com/reference/parameters#testing
                                    ->values(['internal', 'external'])
                                    ->defaultNull()
                                ->end()
                                ->arrayNode('payment_methods')
                                    ->info('Zero or more payment methods. The "payment_method" can be referenced in the "mono" config.')
                                    ->useAttributeAsKey('payment_method')
                                    ->defaultValue([])
                                    ->arrayPrototype()
                                    ->children()
                                        ->arrayNode('brands')
                                            ->info('A list of payment brands. See the PayUnity documentation for more info.')
                                            // See https://www.payunity.com/integrations/widget/customization#optionsbrands
                                            ->example(['MASTER', 'VISA'])
                                            ->defaultValue([])
                                            ->scalarPrototype()
                                            ->end()
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
