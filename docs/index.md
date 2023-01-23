# Mono PayUnity Connector

Source: https://gitlab.tugraz.at/dbp/mono/dbp-relay-mono-connector-payunity-bundle

```mermaid
flowchart LR
    subgraph Relay API Gateway
        mono_bundle["Mono Bundle"]
        mono_payunity_bundle["PayUnity Connector"]
    end

    subgraph PayUnity
        payunity_api["PayUnity API"]
    end

    mono_bundle --> mono_payunity_bundle
    mono_payunity_bundle <--> payunity_api
```

The PayUnity Connector connects mono with [PayUnity](https://www.payunity.com/).
It allows configuring multiple different payment contracts with PayUnity, each with
different payment methods.

## Installation Requirements

* A MySQL/MariaDB database
* (for production use) A contract with [PayUnity](https://www.payunity.com/)

## Documentation

* [Configuration](./config.md)
