# Runnable local demonstration

The standalone demo runs under PHP 8.3+ without Laravel dependencies. It tests the fault/replay engine only, with an in-memory fake gateway and in-memory persistence. It **does not** validate Laravel integration, processes, database commits, locks, queues, or a real payment gateway.

```bash
APP_ENV=testing php examples/payment-demo.php
```

For Laravel integration examples using disposable SQLite tables, see `tests/Feature/CheckoutRecoveryTest.php` and `tests/Feature/WebhookReplayTest.php` (require `composer install`, Testbench and PHPUnit).
