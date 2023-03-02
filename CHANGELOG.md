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
