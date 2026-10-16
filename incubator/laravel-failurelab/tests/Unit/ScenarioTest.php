<?php

declare(strict_types=1);

namespace Mykholy\FailureLab\Tests\Unit;

use InvalidArgumentException;
use Mykholy\FailureLab\Exceptions\ExpectationFailed;
use Mykholy\FailureLab\Exceptions\InjectedFailure;
use Mykholy\FailureLab\Exceptions\UnsafeEnvironment;
use Mykholy\FailureLab\FailureLab;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ScenarioTest extends TestCase
{
    public function test_controlled_interruption_and_successful_retry_are_deterministic(): void
    {
        $calls = 0;

        $scenario = FailureLab::scenario('capture after provider acceptance')
            ->interruptOnceAt('provider.accepted')
            ->attempts(2);

        $report = $scenario->run(function ($checkpoints, int $attempt) use (&$calls) {
            $calls++;
            $checkpoints->hit('provider.accepted');

            return 'completed-'.$attempt;
        });

        $report->assertAttempts(2)
            ->assertCheckpointHit('provider.accepted', 2)
            ->assertInterruptedAt('provider.accepted')
            ->assertCompleted();

        self::assertSame('completed-2', $report->value());
        self::assertSame(2, $calls);
        self::assertSame('interrupted', $report->attempts[0]->status);
        self::assertInstanceOf(InjectedFailure::class, $report->attempts[0]->error);
        self::assertSame('completed', $report->attempts[1]->status);
    }

    public function test_failing_checkpoint_not_reached_is_not_reported_as_triggered(): void
    {
        $result = FailureLab::scenario('checkpoint skipped')
            ->interruptOnceAt('after.accepted')
            ->run(fn ($points) => $points->hit('before.accepted'));

        $result->assertCompleted();
        $result->assertInterruptedAt('after.accepted', 0);
        $this->expectException(ExpectationFailed::class);
        $result->assertInterruptedAt('after.accepted');
    }

    public function test_injected_failure_never_retries_beyond_configured_bound(): void
    {
        $report = FailureLab::scenario('one allowed execution')
            ->interruptOnceAt('checkpoint')
            ->run(fn ($points) => $points->hit('checkpoint'));

        $report->assertAttempts(1)->assertInterruptedAt('checkpoint');
        $this->expectException(ExpectationFailed::class);
        $report->assertCompleted();
    }

    public function test_unexpected_exception_is_captured_but_never_automatically_retried(): void
    {
        $executions = 0;
        $report = FailureLab::scenario('unexpected provider exception')
            ->attempts(3)
            ->run(static function () use (&$executions): void {
                $executions++;
                throw new RuntimeException('provider is down');
            });

        self::assertSame(1, $executions);
        $report->assertAttempts(1);
        self::assertSame('failed', $report->attempts[0]->status);
        $this->expectException(ExpectationFailed::class);
        $report->assertCompleted();
    }

    public function test_fluent_options_are_immutable(): void
    {
        $base = FailureLab::scenario('immutability');
        $altered = $base->interruptOnceAt('step')->attempts(2);
        self::assertNotSame($base, $altered);
        $base->run(fn ($point) => $point->hit('step'))->assertCompleted();
        $altered->run(fn ($point) => $point->hit('step'))->assertInterruptedAt('step');
    }

    public function test_rule_may_target_second_attempt(): void
    {
        $report = FailureLab::scenario('second attempt')
            ->interruptOnceAt('point', onAttempt: 2)
            ->attempts(3)
            ->run(fn ($point) => $point->hit('point'));
        // First attempt completes: retrying cannot be assumed without an interruption.
        $report->assertAttempts(1)->assertCompleted()->assertInterruptedAt('point', 0);
        self::assertSame('completed', $report->attempts[0]->status);
        self::assertCount(0, $report->interruptions);
    }

    public function test_all_configured_faults_must_be_observed(): void
    {
        $report = FailureLab::scenario('multi-fault handshake')
            ->interruptOnceAt('before.persist', onAttempt: 1)
            ->interruptOnceAt('after.persist', onAttempt: 2)
            ->attempts(3)
            ->run(static function ($checkpoint): string {
                $checkpoint->hit('before.persist');
                $checkpoint->hit('after.persist');

                return 'ok';
            });

        $report->assertAttempts(3)
            ->assertFaultPlanSatisfied()
            ->assertInterruptedAt('before.persist')
            ->assertInterruptedAt('after.persist')
            ->assertCompleted();
        self::assertSame(['before.persist@1', 'after.persist@2'], $report->scheduledInterruptions);
    }

    public function test_configured_fault_can_be_skipped_by_early_completion_and_must_fail_assertion(): void
    {
        $report = FailureLab::scenario('unreachable later fault')
            ->interruptOnceAt('will.not.run', onAttempt: 2)
            ->attempts(3)
            ->run(static function (): string {
                return 'completed on first attempt';
            });

        $report->assertAttempts(1)->assertCompleted();
        $this->expectException(ExpectationFailed::class);
        $this->expectExceptionMessage('will.not.run@2');
        $report->assertFaultPlanSatisfied();
    }

    public function test_empty_fault_plan_is_not_accepted_as_verified(): void
    {
        $report = FailureLab::scenario('no injection configured')
            ->run(static fn (): bool => true);

        $report->assertCompleted();
        $this->expectException(ExpectationFailed::class);
        $this->expectExceptionMessage('has no configured fault rules');
        $report->assertFaultPlanSatisfied();
    }

    public function test_satisfied_fault_plan_does_not_claim_scenario_completed(): void
    {
        $report = FailureLab::scenario('one attempt stopped')
            ->interruptOnceAt('before.remote')
            ->run(static fn ($checkpoint) => $checkpoint->hit('before.remote'));

        $report->assertFaultPlanSatisfied();
        $this->expectException(ExpectationFailed::class);
        $report->assertCompleted();
    }

    public function test_swallowed_fault_is_not_counted_as_a_verified_interruption(): void
    {
        $report = FailureLab::scenario('handler swallowed test interruption')
            ->interruptOnceAt('after.provider')
            ->attempts(2)
            ->run(static function ($checkpoint): string {
                try {
                    $checkpoint->hit('after.provider');
                } catch (InjectedFailure) {
                    return 'swallowed';
                }

                return 'unreachable';
            });

        $report->assertAttempts(1)->assertCompleted()->assertInterruptedAt('after.provider', 0);

        $this->expectException(ExpectationFailed::class);
        $this->expectExceptionMessage('after.provider@1');
        $report->assertFaultPlanSatisfied();
    }

    public function test_callback_thrown_lookalike_exception_is_not_retried(): void
    {
        $executions = 0;

        $report = FailureLab::scenario('forged exception must be treated as application failure')
            ->interruptOnceAt('after.provider')
            ->attempts(3)
            ->run(static function () use (&$executions): never {
                $executions++;

                throw new InjectedFailure('after.provider', 1);
            });

        self::assertSame(1, $executions);
        $report->assertAttempts(1)->assertInterruptedAt('after.provider', 0);
        self::assertSame('failed', $report->attempts[0]->status);

        $this->expectException(ExpectationFailed::class);
        $report->assertFaultPlanSatisfied();
    }

    public function test_rethrowing_the_original_checkpoint_exception_still_retries(): void
    {
        $report = FailureLab::scenario('original exception identity')
            ->interruptOnceAt('after.provider')
            ->attempts(2)
            ->run(static function ($checkpoint): string {
                try {
                    $checkpoint->hit('after.provider');
                } catch (InjectedFailure $failure) {
                    throw $failure;
                }

                return 'completed';
            });

        $report->assertAttempts(2)->assertFaultPlanSatisfied()->assertCompleted();
        self::assertSame('completed', $report->attempts[1]->status);
    }

    public function test_invalid_names_and_duplicate_fault_rules_fail_early(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FailureLab::scenario('safe')->interruptOnceAt('bad checkpoint name');
    }

    public function test_duplicate_fault_rule_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FailureLab::scenario('safe')->interruptOnceAt('point')->interruptOnceAt('point');
    }

    public function test_scenario_name_rejects_a_leading_nul_instead_of_trimming_it_away(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FailureLab::scenario("\0apparently-valid");
    }

    public function test_scenario_name_rejects_invalid_utf8(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FailureLab::scenario("invalid-\xFF");
    }

    public function test_scenario_name_preserves_safe_unicode_labels(): void
    {
        $name = 'اختبار إعادة المحاولة';
        $report = FailureLab::scenario($name)->run(static fn (): bool => true);

        self::assertSame($name, $report->assertCompleted()->name);
    }

    public function test_wrong_environment_is_denied_before_running_handler(): void
    {
        $previous = getenv('APP_ENV');
        putenv('APP_ENV=production');
        try {
            $executed = false;
            try {
                FailureLab::scenario('unsafe')->run(function () use (&$executed) {
                    $executed = true;
                });
                self::fail('Expected environment guard to reject production.');
            } catch (UnsafeEnvironment) {
                self::assertFalse($executed);
            }
        } finally {
            if ($previous === false) {
                putenv('APP_ENV');
            } else {
                putenv('APP_ENV='.$previous);
            }
        }
    }
}
