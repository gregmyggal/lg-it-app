<?php

namespace App\Support;

/**
 * Base des liens envoyés par email : l'environnement d'où provient l'action (en-tête Origin du navigateur),
 * à condition qu'il soit l'une des origines connues de l'application. Sinon : FRONTEND_URL, puis APP_URL.
 * Jamais une origine arbitraire : un « mot de passe oublié » forgé ne doit pas pouvoir envoyer
 * à la victime un lien (avec son jeton) pointant vers un autre domaine.
 */
class FrontendUrl
{
    public static function base(): string
    {
        $origine = self::normaliser((string) request()->headers->get('Origin'));

        if ($origine !== null && in_array($origine, self::originesAutorisees(), true)) {
            return $origine;
        }

        return rtrim((string) (config('app.frontend_url') ?: config('app.url')), '/');
    }

    public static function lien(string $chemin): string
    {
        return self::base().'/'.ltrim($chemin, '/');
    }

    /** @return list<string> */
    private static function originesAutorisees(): array
    {
        $urls = array_merge(
            [config('app.frontend_url'), config('app.url')],
            explode(',', (string) config('app.frontend_origins')),
        );

        return array_values(array_filter(array_map(fn ($url) => self::normaliser(trim((string) $url)), $urls)));
    }

    /** « schéma://hôte[:port] » en minuscules, ou null si l'URL n'est pas http(s). */
    private static function normaliser(string $url): ?string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ! in_array($parts['scheme'] ?? null, ['http', 'https'], true) || empty($parts['host'])) {
            return null;
        }

        return strtolower($parts['scheme'].'://'.$parts['host']).(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
