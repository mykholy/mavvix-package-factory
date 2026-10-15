<?php

declare(strict_types=1);

namespace Mykholy\FailureLab;

use LogicException;
use Mykholy\FailureLab\Exceptions\ExpectationFailed;
use Mykholy\FailureLab\Exceptions\InjectedFault;

final class AttemptOutcome
{
    private function __construct(
        private readonly bool $success,
        private readonly mixed $result,
        private readonly ?InjectedFault $fault,
    ) {
    }

    public static function succeeded(mixed $result): self
    {
        return new self(true, $result, null);
    }

    public static function injected(InjectedFault $fault): self
    {
        return new self(false, null, $fault);
    }

    public function succeededNormally(): bool
    {
        return $this->success;
    }

    public function wasInjected(): bool
    {
        return $this->fault !== null;
    }

    public function injectedAt(): ?string
    {
        return $this->fault?->checkpoint;
    }

    public function value(): mixed
    {
        if (! $this->success) {
            throw new LogicException('The attempt was interrupted by an injected fault.');
        }

        return $this->result;
    }

    public function assertSucceeded(): self
    {
        if (! $this->success) {
            throw new ExpectationFailed(
                sprintf('Expected a successful attempt; fault injected at "%s".', $this->fault?->checkpoint)
            );
        }

        return $this;
    }

    public function assertInjectedAt(string $checkpoint): self
    {
        if ($this->fault?->checkpoint !== $checkpoint) {
            throw new ExpectationFailed(
                sprintf('Expected injected fault at "%s"; got "%s".', $checkpoint, $this->fault?->checkpoint ?? 'none')
            );
        }

        return $this;
    }
}
