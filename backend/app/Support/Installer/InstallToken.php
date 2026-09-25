<?php

namespace App\Support\Installer;

/**
 * Jeton d'accès à l'assistant /install.
 *
 * Seul le hash est stocké (storage/app/installer.json) : le jeton en clair
 * n'est affiché qu'une fois, par `php artisan app:installer` en SSH. Sans
 * fichier valide, /install répond 404 — c'est ce qui verrouille l'assistant.
 */
class InstallToken
{
    public static function path(): string
    {
        return storage_path('app/installer.json');
    }

    public static function issue(int $ttlHours = 24): string
    {
        $token = bin2hex(random_bytes(24));

        $dir = dirname(self::path());
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(self::path(), json_encode([
            'hash' => hash('sha256', $token),
            'expires_at' => time() + $ttlHours * 3600,
        ]));
        @chmod(self::path(), 0600);

        return $token;
    }

    public static function isValid(?string $token): bool
    {
        if (! $token || ! is_file(self::path())) {
            return false;
        }

        $data = json_decode((string) file_get_contents(self::path()), true);

        if (! is_array($data) || ($data['expires_at'] ?? 0) < time()) {
            return false;
        }

        return hash_equals((string) ($data['hash'] ?? ''), hash('sha256', $token));
    }

    public static function revoke(): void
    {
        if (is_file(self::path())) {
            unlink(self::path());
        }
    }
}
