<?php

declare(strict_types=1);

namespace Mykholy\FailureLab\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mykholy\FailureLab\FailureLab;

final class RecoveryIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('checkout_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('gateway_reference')->nullable();
        });

        Schema::create('webhook_receipts', function (Blueprint $table): void {
            $table->id();
            $table->string('event_id')->unique();
        });

        Schema::create('webhook_effects', function (Blueprint $table): void {
            $table->id();
            $table->string('event_id');
        });
    }

    public function test_payment_replay_uses_provider_idempotency_after_injected_ack_failure(): void
    {
        // Disposable local fake, NOT an actual payment provider. It shows the
        // application must supply durable provider-side idempotency semantics.
        $gateway = new class
        {
            public int $requests = 0;

            public int $charges = 0;

            private array $references = [];

            public function capture(string $idempotencyKey): string
            {
                $this->requests++;

                if (! isset($this->references[$idempotencyKey])) {
                    $this->charges++;
                    $this->references[$idempotencyKey] = 'fake-ref-'.$this->charges;
                }

                return $this->references[$idempotencyKey];
            }
        };

        DB::table('checkout_orders')->insert(['id' => 10, 'gateway_reference' => null]);

        $lab = app(FailureLab::class);
        $checkout = static function () use ($lab, $gateway): string {
            $reference = $gateway->capture('order-10');

            // The external fake has accepted the charge, but the local order
            // has not been marked as paid yet.
            $lab->checkpoint('checkout.after_gateway_acceptance');
            DB::table('checkout_orders')->where('id', 10)->update(['gateway_reference' => $reference]);

            return $reference;
        };

        $scenario = $lab->scenario('checkout provider acknowledgement lost')
            ->failOnceAt('checkout.after_gateway_acceptance');

        $scenario->attempt($checkout)->assertInjectedAt('checkout.after_gateway_acceptance');

        self::assertNull(DB::table('checkout_orders')->where('id', 10)->value('gateway_reference'));
        $scenario->attempt($checkout)->assertSucceeded();

        self::assertSame(2, $gateway->requests, 'The retry really invoked the fake provider.');
        self::assertSame(1, $gateway->charges, 'The fake provider deduplicated via the caller key.');
        self::assertSame('fake-ref-1', DB::table('checkout_orders')->where('id', 10)->value('gateway_reference'));
        $scenario->assertHits('checkout.after_gateway_acceptance', 2)
            ->assertInjectedCount(1)
            ->assertInvariant('exactly one provider charge', fn (): bool => $gateway->charges === 1)
            ->assertInvariant('order reference persisted', static fn (): bool => DB::table('checkout_orders')->where('id', 10)->value('gateway_reference') === 'fake-ref-1');
    }

    public function test_acknowledgement_loss_after_db_commit_does_not_duplicate_local_webhook_effect(): void
    {
        $lab = app(FailureLab::class);
        $handler = static function () use ($lab): string {
            $status = DB::transaction(static function (): string {
                if (DB::table('webhook_receipts')->where('event_id', 'evt-1')->exists()) {
                    return 'duplicate';
                }

                DB::table('webhook_receipts')->insert(['event_id' => 'evt-1']);
                DB::table('webhook_effects')->insert(['event_id' => 'evt-1']);

                return 'processed';
            });

            $lab->checkpoint('webhook.after_commit_before_ack');

            return $status;
        };

        $scenario = $lab->scenario('webhook acknowledgement lost')
            ->failOnceAt('webhook.after_commit_before_ack');

        $scenario->attempt($handler)->assertInjectedAt('webhook.after_commit_before_ack');
        self::assertSame(1, DB::table('webhook_effects')->count());

        $result = $scenario->attempt($handler);
        $result->assertSucceeded();
        self::assertSame('duplicate', $result->value());
        self::assertSame(1, DB::table('webhook_effects')->count());
        self::assertSame(1, DB::table('webhook_receipts')->count());
        $scenario->assertHits('webhook.after_commit_before_ack', 2)
            ->assertInjectedCount(1)
            ->assertInvariant('one persisted webhook effect', static fn (): bool => DB::table('webhook_effects')->count() === 1);
    }
}
