---
title: Overview
---

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
It allows configuring multiple different payment contracts with PayUnity with
each different payment methods.

## Installation Requirements

* A SQL database like MySQL, PostgreSQL or similar.

## Configuration

```yaml
dbp_relay_mono_connector_payunity:
    database_url:         # Required
    payment_contracts:    # Required
        # Prototype
        -
            api_url:              ~
            entity_id:            ~
            access_token:         ~
            payment_methods_to_widgets: # Required
                # Prototype
                -
                    name:                 ~
                    widget_url:           ~
                    template:             ~
                    icon_url:             ~
                    brands:               ~
```
