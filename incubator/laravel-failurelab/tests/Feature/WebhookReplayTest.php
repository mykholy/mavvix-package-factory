<?php

declare(strict_types=1);

namespace Mykholy\FailureLab\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mykholy\FailureLab\FailureLab;
use RuntimeException;

final class WebhookReplayTest extends TestCase
{
    public function test_three_sequential_deliveries_cause_one_local_side_effect(): void
    {
        Schema::create('processed_events', static function (Blueprint $table): void {
            $table->string('event_id')->primary();
        });
        Schema::create('ledgers', static function (Blueprint $table): void {
            $table->id();
            $table->string('event_id');
        });

        $delivery = static function (int $sequence): void {
            DB::transaction(static function (): void {
                // insertOrIgnore returns 1 only for the first delivery.
                if (DB::table('processed_events')->insertOrIgnore(['event_id' => 'evt_123']) === 1) {
                    DB::table('ledgers')->insert(['event_id' => 'evt_123']);
                }
            });
        };

        FailureLab::replay('duplicate verified webhook event')
            ->times(3)->run($delivery)->assertDeliveries(3)->assertAllCompleted();

        self::assertSame(1, DB::table('processed_events')->count());
        self::assertSame(1, DB::table('ledgers')->count());
    }

    public function test_transaction_rollback_then_sequential_redelivery_recovers_once(): void
    {
        Schema::create('recovery_events', static function (Blueprint $table): void {
            $table->string('event_id')->primary();
        });
        Schema::create('recovery_ledger', static function (Blueprint $table): void {
            $table->id();
            $table->string('event_id')->unique();
        });

        $report = FailureLab::replay('db rollback then retry')
            ->times(3)
            ->continueAfterFailure()
            ->run(static function (int $delivery): void {
                DB::transaction(static function () use ($delivery): void {
                    if (DB::table('recovery_events')->insertOrIgnore(['event_id' => 'evt-99']) === 1) {
                        DB::table('recovery_ledger')->insert(['event_id' => 'evt-99']);
                    }

                    if ($delivery === 1) {
                        // The first attempt rolls back both writes, just like an app exception.
                        throw new RuntimeException('synthetic database or handler exception');
                    }
                });
            });

        $report->assertDeliveries(3)
            ->assertFailedDeliveries(1)
            ->assertRecoveredAfterFailure()
            ->assertInvariant('exactly one committed ledger effect', static fn (): bool => DB::table('recovery_ledger')->count() === 1);

        self::assertSame(1, DB::table('recovery_events')->count());
        self::assertSame(1, DB::table('recovery_ledger')->count());
    }
}
