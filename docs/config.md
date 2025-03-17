# Configuration

## Bundle Configuration

Created via `./bin/console config:dump-reference DbpRelayMonoConnectorPayunityBundle | sed '/^$/d'`

```yaml
# Default configuration for "DbpRelayMonoConnectorPayunityBundle"
dbp_relay_mono_connector_payunity:
    # The database DSN
    database_url:         '%env(resolve:DATABASE_URL)%' # Required
    payment_contracts:    # Required
        # Prototype
        -
            # The payunity API endpoint. For example https://eu-test.oppwa.com
            api_url:              ~
            # The entityId provided by payunity
            entity_id:            ~
            # The access token provided by payunity
            access_token:         ~
            # The WebHook secret provided by payunity
            webhook_secret:       ~
            # If an internal or external test system should be used. Only allowed to be set with the test server.
            test_mode:            null # One of "internal"; "external"
            payment_methods_to_widgets: # Required
                # Prototype
                -
                    template:             ~
                    brands:               ~
```

Example configuration:

```yaml
dbp_relay_mono_connector_payunity:
  database_url: '%env(resolve:DATABASE_URL)%'
  payment_contracts:
    payunity_flex_studienservice:
      api_url: '%env(resolve:MONO_CONNECTOR_PAYUNITY_API_URL)%'
      entity_id: '%env(MONO_CONNECTOR_PAYUNITY_ENTITY_ID)%'
      access_token: '%env(MONO_CONNECTOR_PAYUNITY_ACCESS_TOKEN)%'
      webhook_secret: '%env(MONO_CONNECTOR_PAYUNITY_WEBHOOK_SECRET)%'
      payment_methods_to_widgets:
        payunity_creditcard:
          template: 'index.html.twig'
          brands: 'AMEX DINERS DISCOVER JCB MASTER VISA'
        payunity_applepay:
          template: 'applepay.html.twig'
          brands: 'APPLEPAY'
        payunity_googlepay:
          template: 'index.html.twig'
          brands: 'GOOGLEPAY'
        payunity_sofortueberweisung:
          template: 'index.html.twig'
          brands: 'SOFORTUEBERWEISUNG'
```

## Test Mode

* `test_mode` is not allowed to be set when the payunity production server is configured, or payments will fail, see https://www.payunity.com/reference/parameters#testing

## Web Hook

You can use the `dbp:relay:mono-connector-payunity:webhook-info` to see the URL you need to forward to PayUnity the webhook registration:

```console
./bin/console dbp:relay:mono-connector-payunity:webhook-info payunity_flex_studienservice
Webhook URL for PayUnity:

http://localhost:8000/mono-connector-payunity/webhook/payunity_flex_studienservice
```
