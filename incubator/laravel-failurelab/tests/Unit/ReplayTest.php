<?php

declare(strict_types=1);

namespace Mykholy\FailureLab\Tests\Unit;

use InvalidArgumentException;
use Mykholy\FailureLab\Exceptions\ExpectationFailed;
use Mykholy\FailureLab\FailureLab;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ReplayTest extends TestCase
{
    public function test_sequential_redelivery_can_be_asserted_without_duplicate_local_effects(): void
    {
        $seen = [];
        $effects = 0;

        $report = FailureLab::replay('webhook-42')->times(3)->run(function () use (&$seen, &$effects) {
            if (! isset($seen['webhook-42'])) {
                $seen['webhook-42'] = true;
                $effects++;
            }
        });

        $report->assertDeliveries(3)->assertAllCompleted();
        self::assertSame(1, $effects);
    }

    public function test_real_failure_stops_further_deliveries(): void
    {
        $report = FailureLab::replay('real error')->times(3)
            ->run(fn () => throw new RuntimeException('database unavailable'));
        $report->assertDeliveries(1);
        $this->expectException(ExpectationFailed::class);
        $report->assertAllCompleted();
    }

    public function test_opt_in_redelivery_can_recover_from_a_transient_handler_failure(): void
    {
        $calls = 0;
        $localEffects = 0;

        $report = FailureLab::replay('temporary delivery failure')
            ->times(3)
            ->continueAfterFailure()
            ->run(static function (int $delivery) use (&$calls, &$localEffects): void {
                $calls++;
                if ($delivery === 1) {
                    throw new RuntimeException('private-provider-error-detail');
                }
                // Host-controlled duplicate suppression, not provided by FailureLab.
                $localEffects = 1;
            });

        self::assertSame(3, $calls);
        $report->assertDeliveries(3)
            ->assertFailedDeliveries(1)
            ->assertRecoveredAfterFailure()
            ->assertInvariant('one local business effect', static fn (): bool => $localEffects === 1);

        self::assertSame(
            ['scenario' => 'temporary delivery failure', 'deliveries' => 3, 'completed' => 2, 'failed' => 1],
            $report->summary()
        );
        self::assertSame('failed', $report->deliveries[0]->status);
        self::assertSame('completed', $report->deliveries[1]->status);
        self::assertSame('completed', $report->deliveries[2]->status);
    }

    public function test_continue_after_failure_builder_does_not_mutate_default_fail_closed_behavior(): void
    {
        $base = FailureLab::replay('safe default')->times(3);
        $continuing = $base->continueAfterFailure();
        self::assertNotSame($base, $continuing);

        $callback = static fn (int $delivery): int => $delivery === 1
            ? throw new RuntimeException('temporary')
            : $delivery;

        $base->run($callback)->assertDeliveries(1)->assertFailedDeliveries(1);
        $continuing->run($callback)->assertDeliveries(3)->assertRecoveredAfterFailure();
    }

    public function test_recovery_assertion_does_not_pass_if_all_deliveries_fail(): void
    {
        $report = FailureLab::replay('never recovered')
            ->times(3)
            ->continueAfterFailure()
            ->run(static fn (): never => throw new RuntimeException('sensitive-failure'));
        $report->assertFailedDeliveries(3);

        $this->expectException(ExpectationFailed::class);
        $this->expectExceptionMessage('has no completed handler delivery');
        $report->assertRecoveredAfterFailure();
    }

    public function test_recovery_assertion_requires_an_actual_failure(): void
    {
        $report = FailureLab::replay('healthy delivery')
            ->times(3)->continueAfterFailure()->run(static fn (): bool => true);
        $report->assertFailedDeliveries(0);

        $this->expectException(ExpectationFailed::class);
        $report->assertRecoveredAfterFailure();
    }

    public function test_asserting_failure_count_is_precise(): void
    {
        $report = FailureLab::replay('bounded failure')
            ->times(2)->continueAfterFailure()->run(
                static fn (int $delivery) => $delivery === 1 ? throw new RuntimeException('secret') : true,
            );

        $this->expectException(ExpectationFailed::class);
        $this->expectExceptionMessage('expected 2 failed deliveries; got 1.');
        $report->assertFailedDeliveries(2);
    }

    public function test_replay_name_rejects_bidirectional_formatting_controls(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FailureLab::replay("report\u{202E}spoof");
    }

    public function test_invalid_delivery_counts_fail_early(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FailureLab::replay('safe')->times(21);
    }
}
