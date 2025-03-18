# Configuration

## Bundle Configuration

Created via `./bin/console config:dump-reference DbpRelayMonoConnectorPayunityBundle | sed '/^$/d'`

```yaml
# Default configuration for "DbpRelayMonoConnectorPayunityBundle"
dbp_relay_mono_connector_payunity:
  # The database DSN
  database_url:         '%env(resolve:DATABASE_URL)%' # Required
  # Zero or more payment contracts. The "payment_contract" can be referenced in the "mono" config.
  payment_contracts:
    # Prototype
    payment_contract:
      # The PayUnity API endpoint.
      api_url:              ~ # Required, Example: 'https://eu-test.oppwa.com'
      # The entityId provided by PayUnity
      entity_id:            ~ # Required
      # The access token provided by PayUnity
      access_token:         ~ # Required
      # The WebHook secret provided by PayUnity
      webhook_secret:       null
      # If an internal or external test system should be used. Only allowed to be set with the test server.
      test_mode:            null # One of "internal"; "external"
      # Zero or more payment methods. The "payment_method" can be referenced in the "mono" config.
      payment_methods:
        # Prototype
        payment_method:
          # A list of payment brands. See the PayUnity documentation for more info.
          brands:               []
            # Examples:
            # - MASTER
            # - VISA
```

Example configuration:

```yaml
dbp_relay_mono_connector_payunity:
  database_url: '%env(DATABASE_URL)%'
  payment_contracts:
    payunity_flex_studienservice:
      api_url: '%env(MONO_CONNECTOR_PAYUNITY_API_URL)%'
      entity_id: '%env(MONO_CONNECTOR_PAYUNITY_ENTITY_ID)%'
      access_token: '%env(MONO_CONNECTOR_PAYUNITY_ACCESS_TOKEN)%'
      webhook_secret: '%env(MONO_CONNECTOR_PAYUNITY_WEBHOOK_SECRET)%'
      payment_methods:
        creditcard:
          brands: ['AMEX', 'DINERS', 'DISCOVER', 'JCB', 'MASTER', 'VISA']
        applepay:
          brands: ['APPLEPAY']
        googlepay:
          brands: ['GOOGLEPAY']
        sofortueberweisung:
          brands: ['SOFORTUEBERWEISUNG']
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
