<?php

declare(strict_types=1);

// Standalone core demo. Composer autoloader if installed, tiny PSR-4 fallback otherwise.
if (is_file(__DIR__.'/../vendor/autoload.php')) {
    require __DIR__.'/../vendor/autoload.php';
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'Mykholy\\FailureLab\\';
        if (! str_starts_with($class, $prefix)) {
            return;
        }
        $path = __DIR__.'/../src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
        if (is_file($path)) {
            require $path;
        }
    });
}

use Mykholy\FailureLab\FailureLab;

$charges = [];
$providerCalls = 0;
$state = ['order' => 'pending'];

$scenario = FailureLab::scenario('accepted charge before local commit')
    ->interruptOnceAt('provider.accepted')
    ->attempts(2);

$report = $scenario->run(static function ($checkpoint) use (&$charges, &$providerCalls, &$state) {
    $providerCalls++;
    $reference = $charges['order:42'] ??= 'captured_42'; // provider-side idempotency fake
    $checkpoint->hit('provider.accepted');
    $state['order'] = 'paid';

    return $reference;
});

$report->assertAttempts(2)->assertInterruptedAt('provider.accepted')->assertCompleted();
if (count($charges) !== 1) {
    throw new RuntimeException('Unexpected duplicate provider acceptance');
}
if ($state['order'] !== 'paid') {
    throw new RuntimeException('Order recovery failed');
}

$effects = 0;
$seen = [];
$webhook = FailureLab::replay('evt_42')->times(3)->run(static function () use (&$seen, &$effects): void {
    if (isset($seen['evt_42'])) {
        return;
    }
    $seen['evt_42'] = true;
    $effects++;
});
$webhook->assertDeliveries(3)->assertAllCompleted();
if ($effects !== 1) {
    throw new RuntimeException('Webhook replay produced duplicate local effects');
}

printf("PASS: %d provider calls, %d accepted charges, state=%s, webhook deliveries=%d, effects=%d\n",
    $providerCalls, count($charges), $state['order'], count($webhook->deliveries), $effects);
