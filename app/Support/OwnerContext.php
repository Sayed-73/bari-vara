<?php

namespace App\Support;

/**
 * Trusted server-side code (public bill page, payment callbacks, scheduler) has no logged-in landlord.
 * Wrap it in OwnerContext::run() to switch the per-landlord data scope off for that block only.
 * Never use this around anything that shows data to a logged-in landlord.
 */
final class OwnerContext
{
    private static int $depth = 0;

    public static function bypassed(): bool
    {
        return self::$depth > 0;
    }

    public static function run(callable $callback): mixed
    {
        self::$depth++;

        try {
            return $callback();
        } finally {
            self::$depth--;
        }
    }
}
