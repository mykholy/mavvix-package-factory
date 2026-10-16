<?php

declare(strict_types=1);

namespace Mykholy\FailureLab\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mykholy\FailureLab\FailureLab;
use RuntimeException;

/**
 * Real MySQL/PostgreSQL job runs these tests without PHPUnit transaction wrapping.
 * The default Testbench matrix also executes them using disposable in-memory SQLite.
 */
final class DatabaseBoundaryTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $driver = getenv('FAILURELAB_TEST_DB');
        if (! in_array($driver, ['mysql', 'pgsql'], true)) {
            return;
        }

        $app['config']->set('database.default', $driver);
        $app['config']->set('database.connections.'.$driver, [
            'driver' => $driver,
            'host' => '127.0.0.1',
            'port' => $driver === 'mysql' ? 3306 : 5432,
            'database' => 'failurelab_test',
            'username' => 'failurelab',
            'password' => 'failurelab_ci_only',
            'charset' => $driver === 'mysql' ? 'utf8mb4' : 'utf8',
            'collation' => $driver === 'mysql' ? 'utf8mb4_unicode_ci' : null,
            'prefix' => '',
            'schema' => 'public',
            'sslmode' => 'prefer',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('failurelab_effects');
        Schema::dropIfExists('failurelab_events');
        Schema::create('failurelab_events', static function (Blueprint $table): void {
            $table->string('event_id')->primary();
        });
        Schema::create('failurelab_effects', static function (Blueprint $table): void {
            $table->id();
            $table->string('event_id')->unique();
        });
    }

    protected function tearDown(): void
    {
        try {
            Schema::dropIfExists('failurelab_effects');
            Schema::dropIfExists('failurelab_events');
        } finally {
            parent::tearDown();
        }
    }

    public function test_fault_inside_transaction_rolls_back_then_retry_commits_once(): void
    {
        $report = FailureLab::scenario('rollback before commit on real database')
            ->interruptOnceAt('transaction.before_commit')
            ->attempts(2)
            ->run(static function ($checkpoint): void {
                DB::transaction(static function () use ($checkpoint): void {
                    DB::table('failurelab_events')->insertOrIgnore(['event_id' => 'event-rollback']);
                    DB::table('failurelab_effects')->insertOrIgnore(['event_id' => 'event-rollback']);
                    $checkpoint->hit('transaction.before_commit');
                });
            });

        $report->assertAttempts(2)->assertFaultPlanSatisfied()->assertCompleted()
            ->assertInvariant('one committed event', static fn (): bool => DB::table('failurelab_events')->count() === 1)
            ->assertInvariant('one committed effect', static fn (): bool => DB::table('failurelab_effects')->count() === 1);

        self::assertSame(1, DB::table('failurelab_events')->count());
        self::assertSame(1, DB::table('failurelab_effects')->count());
    }

    public function test_fault_after_commit_preserves_first_effect_and_idempotent_retry(): void
    {
        $report = FailureLab::scenario('interruption after committed local effect')
            ->interruptOnceAt('transaction.after_commit')
            ->attempts(2)
            ->run(static function ($checkpoint): void {
                DB::transaction(static function (): void {
                    if (DB::table('failurelab_events')->insertOrIgnore(['event_id' => 'event-committed']) === 1) {
                        DB::table('failurelab_effects')->insert(['event_id' => 'event-committed']);
                    }
                });

                $checkpoint->hit('transaction.after_commit');
            });

        $report->assertAttempts(2)->assertFaultPlanSatisfied()->assertCompleted()
            ->assertInvariant('one persistent event', static fn (): bool => DB::table('failurelab_events')->count() === 1)
            ->assertInvariant('no second ledger effect', static fn (): bool => DB::table('failurelab_effects')->count() === 1);
        self::assertSame(1, DB::table('failurelab_events')->count());
        self::assertSame(1, DB::table('failurelab_effects')->count());
    }

    public function test_sequential_webhook_redelivery_recovers_after_transaction_rollback(): void
    {
        $report = FailureLab::replay('database rollback and two repeated deliveries')
            ->times(3)
            ->continueAfterFailure()
            ->run(static function (int $delivery): void {
                DB::transaction(static function () use ($delivery): void {
                    if (DB::table('failurelab_events')->insertOrIgnore(['event_id' => 'event-replay']) === 1) {
                        DB::table('failurelab_effects')->insert(['event_id' => 'event-replay']);
                    }

                    if ($delivery === 1) {
                        throw new RuntimeException('synthetic transient handler exception');
                    }
                });
            });

        $report->assertDeliveries(3)->assertFailedDeliveries(1)->assertRecoveredAfterFailure()
            ->assertInvariant('one durable replay effect', static fn (): bool => DB::table('failurelab_effects')->count() === 1);

        self::assertSame(1, DB::table('failurelab_events')->count());
        self::assertSame(1, DB::table('failurelab_effects')->count());
    }
}
