<?php

declare(strict_types=1);

/** Dependency-free engine verification. Laravel/Testbench integration still requires Composer CI. */
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

use Mykholy\FailureLab\Exceptions\ExpectationFailed;
use Mykholy\FailureLab\Exceptions\UnsafeEnvironment;
use Mykholy\FailureLab\FailureLab;

putenv('APP_ENV=testing');
$checks = 0;
$verify = static function (bool $condition, string $label) use (&$checks): void {
    $checks++;
    if (! $condition) {
        throw new RuntimeException('Smoke assertion failed: '.$label);
    }
};

$report = FailureLab::scenario('controlled fault')
    ->interruptOnceAt('provider.accepted')->attempts(2)
    ->run(static function ($points, int $attempt): int {
        $points->hit('provider.accepted');

        return $attempt;
    });
$report->assertAttempts(2)->assertInterruptedAt('provider.accepted')->assertFaultPlanSatisfied()->assertCheckpointHit('provider.accepted', 2)->assertCompleted();
$verify($report->value() === 2, 'second attempt result');
$verify($report->attempts[0]->status === 'interrupted', 'first interrupted');
$verify($report->attempts[1]->status === 'completed', 'second completed');

$unexpected = 0;
$failed = FailureLab::scenario('unexpected exception')->attempts(20)->run(static function () use (&$unexpected): void {
    $unexpected++;
    throw new LogicException('ordinary app failure');
});
$verify($unexpected === 1 && count($failed->attempts) === 1, 'unexpected failure is not retried');
$verify($failed->attempts[0]->status === 'failed', 'unexpected status captured');
$thrown = false;
try {
    $failed->assertCompleted();
} catch (ExpectationFailed) {
    $thrown = true;
}
$verify($thrown, 'failed result assertion fails');

$neverReached = FailureLab::scenario('not reached')->interruptOnceAt('not.reached')->run(static fn () => true);
$neverReached->assertCompleted()->assertInterruptedAt('not.reached', 0);
$thrown = false;
try {
    $neverReached->assertInterruptedAt('not.reached');
} catch (ExpectationFailed) {
    $thrown = true;
}
$verify($thrown, 'missing fault cannot silently pass assertion');

$events = 0;
$once = [];
$replay = FailureLab::replay('duplicate event')->times(3)->run(static function () use (&$once, &$events): void {
    if (isset($once['evt1'])) {
        return;
    }
    $once['evt1'] = true;
    $events++;
});
$replay->assertDeliveries(3)->assertAllCompleted();
$verify($events === 1, 'one business effect after duplicate delivery');

$replayed = 0;
$failedReplay = FailureLab::replay('stop at exception')->times(10)->run(static function () use (&$replayed): void {
    $replayed++;
    throw new LogicException('unexpected');
});
$verify($replayed === 1 && count($failedReplay->deliveries) === 1, 'replay fail closed');

$previous = getenv('APP_ENV');
putenv('APP_ENV=production');
$executed = false;
try {
    FailureLab::scenario('unsafe')->run(static function () use (&$executed): void {
        $executed = true;
    });
    throw new RuntimeException('Guard failed to block execution');
} catch (UnsafeEnvironment) {
    $verify(! $executed, 'production environment prevented execution');
} finally {
    putenv('APP_ENV='.$previous);
}

$invalid = false;
try {
    FailureLab::scenario('invalid')->interruptOnceAt('.. invalid name');
} catch (InvalidArgumentException) {
    $invalid = true;
}
$verify($invalid, 'reject invalid checkpoint');
$invalid = false;
try {
    FailureLab::scenario('invalid')->attempts(100000);
} catch (InvalidArgumentException) {
    $invalid = true;
}
$verify($invalid, 'bound attempts');

$multi = FailureLab::scenario('two controlled faults')
    ->interruptOnceAt('first', 1)->interruptOnceAt('second', 2)->attempts(3)
    ->run(static function ($checkpoint): string {
        $checkpoint->hit('first');
        $checkpoint->hit('second');

        return 'success';
    });
$multi->assertAttempts(3)->assertInterruptedAt('first')->assertInterruptedAt('second')->assertFaultPlanSatisfied()->assertCompleted();
$verify($multi->value() === 'success', 'multiple scheduled faults');

$recovered = FailureLab::replay('opt-in transport retry')
    ->times(3)
    ->continueAfterFailure()
    ->run(static function (int $delivery): int {
        if ($delivery === 1) {
            throw new LogicException('synthetic handler error');
        }

        return $delivery;
    });
$recovered->assertDeliveries(3)->assertFailedDeliveries(1)->assertRecoveredAfterFailure();
$verify(
    $recovered->summary()['completed'] === 2 && $recovered->summary()['failed'] === 1,
    'retry recovery metadata has two successes and one failed delivery',
);
$recoveredByDefault = FailureLab::replay('default aborts')->times(3)
    ->run(static fn (): never => throw new LogicException('stop'));
$recoveredByDefault->assertDeliveries(1)->assertFailedDeliveries(1);
$verify(count($recoveredByDefault->deliveries) === 1, 'default remains fail closed');

$unreached = FailureLab::scenario('missing injection')
    ->interruptOnceAt('not.reached', 2)->attempts(3)
    ->run(static fn (): string => 'finished early');
$unreached->assertCompleted();
$unmet = false;
try {
    $unreached->assertFaultPlanSatisfied();
} catch (ExpectationFailed) {
    $unmet = true;
}
$verify($unmet, 'skipped fault plan cannot report success');

printf("PASS: %d standalone smoke assertions, two scenario types, safety guard and report checks\n", $checks);
