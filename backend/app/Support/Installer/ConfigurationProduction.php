<?php

namespace App\Support\Installer;

/**
 * RGPD-01 : garde-fous de configuration pour un environnement de production. Fonction pure (testable) :
 * reçoit les valeurs du .env (ou celles que l'installeur s'apprête à écrire) et liste les violations.
 */
class ConfigurationProduction
{
    /**
     * @param  array<string, string|null>  $env  APP_ENV, APP_DEBUG, MAIL_MAILER, SESSION_ENCRYPT
     * @return list<string> messages en français ; vide = conforme
     */
    public static function violations(array $env): array
    {
        if (($env['APP_ENV'] ?? 'production') !== 'production') {
            return [];
        }

        $v = [];
        if (filter_var($env['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN)) {
            $v[] = 'APP_DEBUG=true est interdit en production (traces d\'erreur exposées).';
        }
        if (strtolower((string) ($env['MAIL_MAILER'] ?? '')) === 'log') {
            $v[] = 'MAIL_MAILER=log est interdit en production : les liens d\'invitation et de réinitialisation seraient écrits dans les logs. Configurez un serveur SMTP.';
        }
        if (! filter_var($env['SESSION_ENCRYPT'] ?? 'true', FILTER_VALIDATE_BOOLEAN)) {
            $v[] = 'SESSION_ENCRYPT doit valoir true en production.';
        }

        return $v;
    }

    /** Violations du .env actuellement en place (affichées par l'assistant en mode reconfiguration). */
    public static function violationsDe(EnvFile $env): array
    {
        return self::violations([
            'APP_ENV' => $env->get('APP_ENV'),
            'APP_DEBUG' => $env->get('APP_DEBUG'),
            'MAIL_MAILER' => $env->get('MAIL_MAILER'),
            'SESSION_ENCRYPT' => $env->get('SESSION_ENCRYPT'),
        ]);
    }
}
