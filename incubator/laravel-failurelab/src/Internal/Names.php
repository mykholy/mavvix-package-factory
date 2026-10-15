<?php

declare(strict_types=1);

namespace Mykholy\FailureLab\Internal;

use InvalidArgumentException;

final class Names
{
    public static function checkpoint(string $name): string
    {
        if (strlen($name) > 120 || preg_match('/\A[a-z][a-z0-9._:-]*\z/D', $name) !== 1) {
            throw new InvalidArgumentException('Checkpoint must be 1–120 lowercase ASCII characters: a-z, 0-9, ., _, :, -.');
        }

        return $name;
    }

    public static function scenario(string $name): string
    {
        $name = trim($name);

        if ($name === '' || strlen($name) > 120 || preg_match('/[\x00-\x1F\x7F]/', $name) === 1) {
            throw new InvalidArgumentException('Scenario name must be 1–120 characters with no control characters.');
        }

        return $name;
    }
}
