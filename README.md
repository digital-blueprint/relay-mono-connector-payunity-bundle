# DbpRelayMonoConnectorPayunityBundle

[GitLab](https://gitlab.tugraz.at/dbp/relay/dbp-relay-mono-connector-payunity-bundle) |
[Packagist](https://packagist.org/packages/dbp/relay-mono-connector-payunity-bundle)

## Bundle installation

You can install the bundle directly from [packagist.org](https://packagist.org/packages/dbp/relay-mono-connector-payunity-bundle).

```bash
composer require dbp/relay-mono-connector-payunity-bundle
```
## Integration into the API Server

* Add the necessary bundles to your `config/bundles.php`:

```php
...
Dbp\Relay\MonoBundle\DbpRelayMonoBundle::class => ['all' => true],
Dbp\Relay\MonoConnectorPayunityBundle\DbpRelayMonoConnectorPayunityBundle::class => ['all' => true],
Dbp\Relay\CoreBundle\DbpRelayCoreBundle::class => ['all' => true],
];
```

* Run `composer install` to clear caches

## Configuration

For this create `config/packages/dbp_relay_mono_connector_payunity.yaml` in the app with the following
content:

```yaml
dbp_relay_mono_connector_payunity:
  database_url: '%env(resolve:DATABASE_URL)%'
  payment_contracts:
    payunity_flex_studienservice:
      api_url: '%env(resolve:MONO_CONNECTOR_PAYUNITY_API_URL)%'
      entity_id: '%env(MONO_CONNECTOR_PAYUNITY_ENTITY_ID)%'
      access_token: '%env(MONO_CONNECTOR_PAYUNITY_ACCESS_TOKEN)%'
      payment_methods_to_widgets:
        payunity_creditcard:
          name: 'Kreditkarte'
          widget_url: '/bundles/dbprelaymonoconnectorpayunity/html/index.html?brands={brands}&scriptSrc={scriptSrc}'
          icon_url: '/bundles/dbprelaymonoconnectorpayunity/svg/credit-cards.svg'
          brands: 'AMEX DINERS DISCOVER JCB MASTER VISA'
        payunity_applepay:
          name: 'Apple Pay'
          widget_url: '/bundles/dbprelaymonoconnectorpayunity/html/index.html?brands={brands}&scriptSrc={scriptSrc}'
          icon_url: '/bundles/dbprelaymonoconnectorpayunity/svg/apple-pay.svg'
          brands: 'APPLEPAY'
        payunity_googlepay:
          name: 'Google Pay'
          widget_url: '/bundles/dbprelaymonoconnectorpayunity/html/index.html?brands={brands}&scriptSrc={scriptSrc}'
          icon_url: '/bundles/dbprelaymonoconnectorpayunity/svg/google-pay.svg'
          brands: 'GOOGLEPAY'
```

Add widget route to `config/routes/dbp_route.yaml`:

```yaml
DbpRelayCoreBundle:
    resource: "@DbpRelayCoreBundle/Resources/config/routing.yaml"
# [...]
DbpRelayMonoConnectorPayunityBundle:
    resource: "@DbpRelayMonoConnectorPayunityBundle/Resources/config/routing.yaml"
# [...]
```

For more info on bundle configuration see [Symfony bundles configuration](https://symfony.com/doc/current/bundles/configuration.html).

## Development & Testing

* Install dependencies: `composer install`
* Run tests: `composer test`
* Run linters: `composer run lint`
* Run cs-fixer: `composer run cs-fix`

## Bundle dependencies

Don't forget you need to pull down your dependencies in your main application if you are installing packages in a bundle.

```bash
# updates and installs dependencies from dbp/relay-mono-connector-payunity-bundle
composer update dbp/relay-mono-connector-payunity-bundle
```

### Database migration

Run this script to migrate the database. Run this script after installation of the bundle and
after every update to adapt the database to the new source code.

```bash
php bin/console doctrine:migrations:migrate --em=dbp_relay_mono_connector_payunity_bundle
```
