# DbpRelayMonoConnectorPayunityBundle

[GitHub](https://github.com/digital-blueprint/relay-mono-connector-payunity-bundle) |
[Packagist](https://packagist.org/packages/dbp/relay-mono-connector-payunity-bundle)

[![Test](https://github.com/digital-blueprint/relay-mono-connector-payunity-bundle/actions/workflows/test.yml/badge.svg)](https://github.com/digital-blueprint/relay-mono-connector-payunity-bundle/actions/workflows/test.yml)

A PayUnity connector for the mono bundle for the Relay API gateway

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
          widget_url: '/mono-connector-payunity/widget?identifier={identifier}&lang={lang}'
          template: 'index.html.twig'
          brands: 'AMEX DINERS DISCOVER JCB MASTER VISA'
        payunity_applepay:
          widget_url: '/mono-connector-payunity/widget?identifier={identifier}&lang={lang}'
          template: 'applepay.html.twig'
          brands: 'APPLEPAY'
        payunity_googlepay:
          widget_url: '/mono-connector-payunity/widget?identifier={identifier}&lang={lang}'
          template: 'index.html.twig'
          brands: 'GOOGLEPAY'
        payunity_sofortueberweisung:
          widget_url: '/mono-connector-payunity/widget?identifier={identifier}&lang={lang}'
          template: 'index.html.twig'
          brands: 'SOFORTUEBERWEISUNG'
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
