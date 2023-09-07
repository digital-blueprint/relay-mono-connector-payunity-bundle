# v0.1.14

* Don't allow creating a new payunity checkout once we get notified that a previous checkout
  has advanced already.

# v0.1.13

* Log more things to the audit log when talking to payunity or when updating the payment status

# v0.1.12

* Update to api-platform 2.7

# v0.1.11

* Documentation cleanup
* Some database schema improvements

# v0.1.10

* New `dbp:relay-mono-connector-payunity:webhook-info` CLI command which shows information about the webhooks (URL for registration and example curl calls)
* Minor error handling, logging and linter improvements

# v0.1.9

* webhooks: correctly handle test webhook messages

# v0.1.8

* Improved input validation for webhook requests
* Added health check for webhook secret format

# v0.1.7

* Added support for payunity webhook callbacks. This requires a `webhook_secret`
  being set via the bundle configuration.

# v0.1.6

* Compatibility with mono-bundle v0.3

# v0.1.5

* Compatibility with mono-bundle v0.2

# v0.1.4

* Some cleanup
* composer: add a pre-commit hook for linting

# v0.1.3

* Always set a `merchantTransactionId` when creating a checkout. This ID shows up in the main PUMA transaction list, and also allows the payment to be queried using the reporting API in the future.
* logs: More logging and better audit logs
* logs: Always add a relay-mono-payment-id to the audit logs

# v0.1.2

* Migration to GitHub

# v0.1.1

* Fix an error in the payment status check if the payment wasn't started by the user yet
* Cleanup, tests and documentation

# v0.1.0

* Initial release
