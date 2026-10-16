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
        // Validate the original bytes. Trimming first could erase a leading NUL,
        // newline or escape sequence while leaving it in the public report name.
        if (
            trim($name) === ''
            || strlen($name) > 120
            || preg_match('/[\x00-\x1F\x7F]/', $name) !== 0
            || preg_match('/[\x{061C}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', $name) !== 0
        ) {
            throw new InvalidArgumentException('Scenario name must be 1–120 UTF-8 bytes with no control characters.');
        }

        return $name;
    }
}
