# Mono PayUnity Connector

Source: https://github.com/digital-blueprint/relay-mono-connector-payunity-bundle

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

## Bundle installation

You can install the bundle directly from [packagist.org](https://packagist.org/packages/dbp/relay-mono-connector-payunity-bundle).

```bash
composer require dbp/relay-mono-connector-payunity-bundle
```

## Installation Requirements

* A MySQL/MariaDB database
* (for production use) A contract with [PayUnity](https://www.payunity.com/)
* (for production use) A registered webhook with PayUnity

## Documentation

* [Configuration](./config.md)
