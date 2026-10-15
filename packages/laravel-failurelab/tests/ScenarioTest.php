<?php

declare(strict_types=1);

namespace Mykholy\FailureLab\Tests;

use InvalidArgumentException;
use LogicException;
use Mykholy\FailureLab\Exceptions\ExpectationFailed;
use Mykholy\FailureLab\Exceptions\InjectedFault;
use Mykholy\FailureLab\Exceptions\NestedScenario;
use Mykholy\FailureLab\Exceptions\UnsafeEnvironment;
use Mykholy\FailureLab\FailureLab;
use RuntimeException;

final class ScenarioTest extends TestCase
{
    public function test_provider_registers_lab_and_checkpoint_is_inert_without_scenario(): void
    {
        $lab = app(FailureLab::class);
        self::assertSame($lab, app(FailureLab::class));
        $lab->checkpoint('payments.after_capture');
        self::assertTrue(true);
    }

    public function test_simulated_failure_occurs_only_on_first_hit(): void
    {
        $lab = app(FailureLab::class);
        $scenario = $lab->scenario('checkout')->failOnceAt('payments.after_capture');

        $first = $scenario->attempt(function () use ($lab): void {
            $lab->checkpoint('payments.after_capture');
        });
        $second = $scenario->attempt(function () use ($lab): string {
            $lab->checkpoint('payments.after_capture');

            return 'settled';
        });

        self::assertTrue($first->wasInjected());
        $first->assertInjectedAt('payments.after_capture');
        self::assertFalse($second->wasInjected());
        $second->assertSucceeded();
        self::assertSame('settled', $second->value());
        $scenario->assertHits('payments.after_capture', 2)->assertInjectedCount(1);
        self::assertCount(2, $scenario->outcomes());
    }

    public function test_fault_can_be_selected_on_second_hit(): void
    {
        $lab = app(FailureLab::class);
        $scenario = $lab->scenario('second attempt')->failOnHit('checkout.after_save', 2);
        $run = fn () => $lab->checkpoint('checkout.after_save');

        $scenario->attempt($run)->assertSucceeded();
        $scenario->attempt($run)->assertInjectedAt('checkout.after_save');
        $scenario->attempt($run)->assertSucceeded();

        $scenario->assertHits('checkout.after_save', 3)->assertInjectedCount(1);
    }

    public function test_domain_exceptions_propagate_and_active_scope_is_cleared(): void
    {
        $lab = app(FailureLab::class);
        $scenario = $lab->scenario('domain failure')->failOnceAt('a');
        $domainException = new RuntimeException('real application failure');

        try {
            $scenario->attempt(static function () use ($domainException): never {
                throw $domainException;
            });
            self::fail('Expected real exception to propagate');
        } catch (RuntimeException $caught) {
            self::assertSame($domainException, $caught);
        }

        self::assertCount(0, $scenario->outcomes());
        $scenario->attempt(fn () => $lab->checkpoint('a'))->assertInjectedAt('a');
    }

    public function test_injected_result_does_not_expose_a_success_value(): void
    {
        $lab = app(FailureLab::class);
        $result = $lab->scenario('no value')->failOnceAt('a')->attempt(fn () => $lab->checkpoint('a'));

        $this->expectException(LogicException::class);
        $result->value();
    }

    public function test_nested_scenarios_are_refused_and_outer_is_restored(): void
    {
        $lab = app(FailureLab::class);
        $outer = $lab->scenario('outer')->failOnceAt('outer');
        $inner = $lab->scenario('inner');

        try {
            $outer->attempt(fn () => $inner->attempt(fn () => true));
            self::fail('Expected nested execution to throw');
        } catch (NestedScenario) {
            // the inner attempt must not replace the active outer scenario
        }

        $outer->attempt(fn () => $lab->checkpoint('outer'))->assertInjectedAt('outer');
    }

    public function test_invalid_names_are_rejected(): void
    {
        $lab = app(FailureLab::class);

        foreach (['', ' ', str_repeat('x', 121)] as $name) {
            try {
                $lab->scenario($name);
                self::fail('Expected invalid scenario name');
            } catch (InvalidArgumentException) {
            }
        }

        foreach (['', '../escape', '@everyone', str_repeat('a', 121)] as $checkpoint) {
            try {
                $lab->scenario('validation')->failOnceAt($checkpoint);
                self::fail('Expected invalid checkpoint name');
            } catch (InvalidArgumentException) {
            }
        }

        $this->expectException(InvalidArgumentException::class);
        $lab->scenario('wrong index')->failOnHit('safe', 0);
    }

    public function test_plan_cannot_mutate_after_execution(): void
    {
        $lab = app(FailureLab::class);
        $scenario = $lab->scenario('freeze');
        $scenario->attempt(static fn () => 42)->assertSucceeded();

        $this->expectException(InvalidArgumentException::class);
        $scenario->failOnceAt('too.late');
    }

    public function test_assertions_fail_with_actionable_messages(): void
    {
        $lab = app(FailureLab::class);
        $scenario = $lab->scenario('assertions');
        $scenario->attempt(static fn () => null)->assertSucceeded();

        try {
            $scenario->assertInjectedCount(1);
            self::fail('Expected assertion error');
        } catch (ExpectationFailed $e) {
            self::assertStringContainsString('expected 1', $e->getMessage());
        }

        $this->expectException(ExpectationFailed::class);
        $scenario->assertHits('never.hit', 2);
    }

    public function test_scenarios_are_blocked_outside_testing_environment(): void
    {
        $this->app->instance('env', 'production');

        $this->expectException(UnsafeEnvironment::class);
        app(FailureLab::class)->scenario('forbidden');
    }

    public function test_manual_injected_fault_is_not_a_real_worker_crash(): void
    {
        $fault = new InjectedFault('point', 1);
        self::assertStringContainsString('not a process crash', $fault->getMessage());
    }
}
