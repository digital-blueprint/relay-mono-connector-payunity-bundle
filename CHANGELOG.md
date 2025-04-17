# Changelog

## v0.2.1

* Drop support for PHP 8.1
* Remove dependency on api-plaform

## v0.2.0

* Various breaking bundle config simplifications:

  * Required options are now enforced, and non-required have better defaults now
  * Rename `payment_methods_to_widgets` to `payment_methods`
  * Removed `payment_methods.template`, no longer needed
  * Removed `payment_methods.widget_url`, no longer needed
  * Convert `payment_methods.brands` from a space separated string to an array

## v0.1.28

* Fix method lookup failing in case multiple contracts are configured
  (0.1.27 regression)

## v0.1.27

* Port to mono-bundle v0.5

## v0.1.26

* Port to phpstan v2
* Test with PHP 8.4
* Drop support for api-platform v2
* Drop support for Symfony 5
* Drop support for psalm
* Adjust CLI command naming schema (old variant still works)

## v0.1.25

* Add support for doctrine/dbal v4
* Add support for doctrine/orm v3.2

## v0.1.24

* Port from doctrine annotations to PHP 8 attributes
* Port to PHPUnit 10

## v0.1.23

* Add support for api-platform 3.2

## v0.1.22

* Add support for Symfony 6

## v0.1.21

* dev: replace abandoned composer-git-hooks with captainhook.
  Run `vendor/bin/captainhook install -f` to replace the old hooks with the new ones
  on an existing checkout.

## v0.1.20

* Drop support for PHP 7.4/8.0

## v0.1.19

* Drop support for PHP 7.3

## v0.1.18

* Set/fetch the payment status right after creating a checkout instead of waiting on the first webhook
* Don't allow creating multiple checkouts for the same payment

## v0.1.17

* Fix health checks broken by v0.1.16

## v0.1.16

* Compatibility with mono-bundle v0.4

## v0.1.15

* Avoid creating a new payunity checkout in case the user reloads the payunity widget page.

## v0.1.14

* Don't allow creating a new payunity checkout once we get notified that a previous checkout
  has advanced already.

## v0.1.13

* Log more things to the audit log when talking to payunity or when updating the payment status

## v0.1.12

* Update to api-platform 2.7

## v0.1.11

* Documentation cleanup
* Some database schema improvements

## v0.1.10

* New `dbp:relay-mono-connector-payunity:webhook-info` CLI command which shows information about the webhooks (URL for registration and example curl calls)
* Minor error handling, logging and linter improvements

## v0.1.9

* webhooks: correctly handle test webhook messages

## v0.1.8

* Improved input validation for webhook requests
* Added health check for webhook secret format

## v0.1.7

* Added support for payunity webhook callbacks. This requires a `webhook_secret`
  being set via the bundle configuration.

## v0.1.6

* Compatibility with mono-bundle v0.3

## v0.1.5

* Compatibility with mono-bundle v0.2

## v0.1.4

* Some cleanup
* composer: add a pre-commit hook for linting

## v0.1.3

* Always set a `merchantTransactionId` when creating a checkout. This ID shows up in the main PUMA transaction list, and also allows the payment to be queried using the reporting API in the future.
* logs: More logging and better audit logs
* logs: Always add a relay-mono-payment-id to the audit logs

## v0.1.2

* Migration to GitHub

## v0.1.1

* Fix an error in the payment status check if the payment wasn't started by the user yet
* Cleanup, tests and documentation

## v0.1.0

* Initial release
