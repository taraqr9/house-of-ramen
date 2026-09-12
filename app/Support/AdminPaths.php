<?php

namespace App\Support;

/**
 * The single list of top-level path prefixes that belong to the admin
 * panel/auth flows rather than the public House of Ramen site - see
 * routes/web.php's non-"public."-named route groups. Shared by
 * RobotsController (what to Disallow) and bootstrap/app.php's exception
 * handler (which error-page branding to use) so the two can't drift
 * apart as routes are added.
 */
class AdminPaths
{
    /**
     * @return list<string>
     */
    public static function prefixes(): array
    {
        return [
            'admin',
            'login',
            'forgot-password',
            'reset-password',
            'password/setup',
            'change-password',
            'profile',
            'logout',
            'notifications',
            'restaurant',
            'roles',
            'users',
            'menus',
            'logs',
            'permissions',
        ];
    }

    public static function matches(string $path): bool
    {
        $path = ltrim($path, '/');

        foreach (self::prefixes() as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }
}
