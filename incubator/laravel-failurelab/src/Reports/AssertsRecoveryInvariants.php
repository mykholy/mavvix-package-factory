<?php

declare(strict_types=1);

namespace Mykholy\FailureLab\Reports;

use Mykholy\FailureLab\Exceptions\ExpectationFailed;
use Mykholy\FailureLab\Internal\Names;

/**
 * Assert application-specific post-replay invariants.
 *
 * Intentionally never interpolates callback values into failure messages.
 */
trait AssertsRecoveryInvariants
{
    public function assertInvariant(string $name, callable $predicate): static
    {
        $label = Names::scenario($name);

        if ($predicate() !== true) {
            throw new ExpectationFailed("Recovery invariant [{$label}] did not hold.");
        }

        return $this;
    }
}
