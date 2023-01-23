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
      # If an internal or external test system should be used. Only allowed to be set with the test server.
      test_mode:            null # One of "internal"; "external"
      payment_methods_to_widgets: # Required
        # Prototype
        -
          widget_url:           ~
          template:             ~
          brands:               ~
```

## Test Mode

* `test_mode` is not allowed to be set when the payunity production server is configured, or payments will fail, see https://www.payunity.com/reference/parameters#testing
