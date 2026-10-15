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

    public function test_invalid_delivery_counts_fail_early(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FailureLab::replay('safe')->times(21);
    }
}
