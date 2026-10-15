# Contributing

Thanks for helping improve reproducible failure testing for Laravel.

Please include: the exact original failure scenario, your expected business invariant, supported PHP/Laravel versions, a reproducible failing test, a focused fix, and an explanation of what the test proves **and does not prove**.

Development:

```bash
composer install
composer test
vendor/bin/pint --test
```

Avoid committing production credentials, real payment data, network-based fixtures, broad framework monkey patches, or code that targets real systems. Report sensitive vulnerabilities privately.

Breaking API changes require explicit discussion; stable releases require human review.
