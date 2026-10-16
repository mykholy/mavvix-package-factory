<?php

declare(strict_types=1);

namespace Mykholy\FailureLab\Tests\Unit;

use Mykholy\FailureLab\Exceptions\ExpectationFailed;
use Mykholy\FailureLab\FailureLab;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ReportAssertionsTest extends TestCase
{
    public function test_successful_replay_can_assert_business_invariant_and_safe_summary(): void
    {
        $effectCount = 0;
        $report = FailureLab::replay('same webhook')->times(3)->run(
            static function () use (&$effectCount): void {
                $effectCount = 1;
            },
        );

        $report->assertAllCompleted()->assertInvariant('one local effect', static fn (): bool => $effectCount === 1);
        self::assertSame(['scenario' => 'same webhook', 'deliveries' => 3, 'completed' => 3, 'failed' => 0], $report->summary());
    }

    public function test_failed_invariant_reports_name_not_private_data(): void
    {
        $report = FailureLab::replay('webhook redaction')->times(1)->run(static fn (): int => 1);

        try {
            $report->assertInvariant('one local effect', static fn (): bool => false);
            self::fail('Expected invariant failure');
        } catch (ExpectationFailed $e) {
            self::assertSame('Recovery invariant [one local effect] did not hold.', $e->getMessage());
        }
    }

    public function test_scenario_error_does_not_leak_original_message(): void
    {
        $report = FailureLab::scenario('secret guarding')->run(static function (): never {
            throw new RuntimeException('private-test-secret-value');
        });

        self::assertSame(1, $report->summary()['attempts']);

        try {
            $report->assertCompleted();
            self::fail('Expected failure');
        } catch (ExpectationFailed $e) {
            self::assertStringNotContainsString('private-test-secret-value', $e->getMessage());
            self::assertStringContainsString('RuntimeException', $e->getMessage());
        }
    }

    public function test_replay_error_does_not_leak_original_message(): void
    {
        $report = FailureLab::replay('safe failure')->times(2)->run(static function (): never {
            throw new RuntimeException('sensitive-provider-error');
        });

        try {
            $report->assertAllCompleted();
            self::fail('Expected failure');
        } catch (ExpectationFailed $e) {
            self::assertStringNotContainsString('sensitive-provider-error', $e->getMessage());
            self::assertStringContainsString('RuntimeException', $e->getMessage());
        }
    }

    public function test_scenario_metadata_records_checkpoint_hit_counts_only(): void
    {
        $report = FailureLab::scenario('checkpoint report')->interruptOnceAt('received')->attempts(2)
            ->run(static fn ($checkpoint) => $checkpoint->hit('received'));
        $report->assertAttempts(2)->assertCompleted();

        self::assertSame(2, $report->summary()['checkpoint_hits']['received']);
        self::assertSame(1, $report->summary()['interrupted']);
        self::assertSame(1, $report->summary()['completed']);
    }
}
