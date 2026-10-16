<?php

declare(strict_types=1);

namespace Mykholy\FailureLab\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mykholy\FailureLab\Exceptions\ExpectationFailed;
use Mykholy\FailureLab\FailureLab;
use Mykholy\FailureLab\FailureLabManager;

final class CheckoutRecoveryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('orders', static function (Blueprint $table): void {
            $table->id();
            $table->string('status');
        });
        DB::table('orders')->insert(['id' => 1, 'status' => 'pending']);
    }

    public function test_replay_after_provider_acceptance_does_not_double_charge(): void
    {
        // This intentionally simulates an idempotent provider: two calls, one charge.
        // It does NOT claim exactly-once transport or real SIGKILL recovery.
        $providerCalls = 0;
        $accepted = [];
        $provider = static function (string $key) use (&$providerCalls, &$accepted): string {
            $providerCalls++;

            return $accepted[$key] ??= 'capture_123';
        };

        $report = FailureLab::scenario('capture accepted then local processing interrupted')
            ->interruptOnceAt('provider.accepted')
            ->attempts(2)
            ->run(function ($point) use ($provider): string {
                $reference = $provider('order:1:EGP:1000');
                $point->hit('provider.accepted');
                DB::table('orders')->where('id', 1)->update(['status' => 'paid']);

                return $reference;
            });

        $report->assertAttempts(2)->assertInterruptedAt('provider.accepted')->assertCompleted();
        self::assertSame('capture_123', $report->value());
        self::assertSame(2, $providerCalls, 'Two attempts contacted the fake provider.');
        self::assertCount(1, $accepted, 'The provider fake accepted one idempotent capture.');
        self::assertSame('paid', DB::table('orders')->where('id', 1)->value('status'));
    }

    public function test_non_idempotent_provider_is_detected_by_a_business_invariant(): void
    {
        $acceptedCaptures = 0;

        $report = FailureLab::scenario('non-idempotent fake provider')
            ->interruptOnceAt('provider.accepted')
            ->attempts(2)
            ->run(static function ($checkpoint) use (&$acceptedCaptures): void {
                $acceptedCaptures++;
                $checkpoint->hit('provider.accepted');
            });

        $report->assertAttempts(2)->assertFaultPlanSatisfied()->assertCompleted();
        self::assertSame(2, $acceptedCaptures, 'This deliberately unsafe fake accepted two captures.');

        $this->expectException(ExpectationFailed::class);
        $this->expectExceptionMessage('Recovery invariant [one accepted provider capture] did not hold.');
        $report->assertInvariant('one accepted provider capture', static fn (): bool => $acceptedCaptures === 1);
    }

    public function test_injectable_manager_is_registered_by_service_provider(): void
    {
        $manager = $this->app->make(FailureLabManager::class);
        $report = $manager->scenario('from container')->run(fn () => 'ok');
        self::assertSame('ok', $report->assertCompleted()->value());
    }
}
