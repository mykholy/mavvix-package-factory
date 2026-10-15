<?php

declare(strict_types=1);

namespace Mykholy\FailureLab\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mykholy\FailureLab\FailureLab;

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
}
