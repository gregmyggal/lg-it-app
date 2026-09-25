<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Installer\EnvFile;
use App\Support\Installer\InstallToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use PDO;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Throwable;

/**
 * Assistant d'installation / configuration post-déploiement (OVH mutualisé).
 *
 * Routes sans session ni cookies (cf. routes/install.php) : l'app peut ne pas
 * encore avoir de .env, d'APP_KEY ni de base. Chaque étape de finalisation est
 * une requête distincte, pour que les suivantes relisent le .env fraîchement écrit.
 */
class InstallController extends Controller
{
    private const REQUIRED_EXTENSIONS = [
        'ctype', 'curl', 'dom', 'fileinfo', 'filter', 'hash', 'mbstring',
        'openssl', 'pcre', 'pdo', 'pdo_mysql', 'session', 'tokenizer', 'xml',
    ];

    // Pas de lang/fr dans le projet : messages en dur, affichés sous chaque champ.
    private const MESSAGES = [
        'required' => 'Champ obligatoire.',
        'required_if' => 'Champ obligatoire.',
        'string' => 'Valeur invalide.',
        'integer' => 'Nombre entier attendu.',
        'between' => 'Doit être compris entre :min et :max.',
        'max' => ':max caractères maximum.',
        'min' => ':min caractères minimum.',
        'url' => 'URL invalide (ex. https://lgit.be).',
        'email' => 'Adresse e-mail invalide.',
        'in' => 'Valeur non autorisée.',
        'timezone' => 'Fuseau horaire inconnu.',
        'confirmed' => 'La confirmation ne correspond pas.',
        'password.letters' => 'Doit contenir des lettres.',
        'password.mixed' => 'Doit contenir majuscules et minuscules.',
        'password.numbers' => 'Doit contenir au moins un chiffre.',
    ];

    public function show(Request $request)
    {
        return response()->view('install', [
            'token' => (string) $request->query('token'),
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $env = EnvFile::load();
        $hasEnv = EnvFile::exists();
        $value = fn (string $key, ?string $default = null) => $hasEnv ? ($env->get($key) ?? $default) : $default;

        return response()->json([
            'mode' => is_file($this->installedMarker()) ? 'reconfigure' : 'install',
            'requirements' => $this->requirements($request),
            'admin_exists' => $hasEnv && $this->adminExists(),
            'values' => [
                'app_name' => $value('APP_NAME', 'LG-IT'),
                'app_url' => $value('APP_URL') && $value('APP_URL') !== 'http://localhost'
                    ? $value('APP_URL')
                    : $request->getSchemeAndHttpHost(),
                'app_env' => $value('APP_ENV', 'production') === 'staging' ? 'staging' : 'production',
                'app_timezone' => $value('APP_TIMEZONE', 'Europe/Brussels') === 'UTC' ? 'Europe/Brussels' : $value('APP_TIMEZONE', 'Europe/Brussels'),
                'db_host' => $value('DB_HOST', ''),
                'db_port' => $value('DB_PORT', '3306'),
                'db_database' => $value('DB_DATABASE', ''),
                'db_username' => $value('DB_USERNAME', ''),
                'has_db_password' => $hasEnv && (string) $env->get('DB_PASSWORD') !== '',
                'mail_mailer' => $value('MAIL_MAILER', 'smtp') === 'log' ? 'log' : 'smtp',
                'mail_host' => $value('MAIL_HOST', 'ssl0.ovh.net') === '127.0.0.1' ? 'ssl0.ovh.net' : $value('MAIL_HOST', 'ssl0.ovh.net'),
                'mail_port' => $value('MAIL_PORT', '465') === '2525' ? '465' : $value('MAIL_PORT', '465'),
                'mail_scheme' => in_array($value('MAIL_SCHEME'), ['smtp', 'smtps'], true) ? $value('MAIL_SCHEME') : 'smtps',
                'mail_username' => $value('MAIL_USERNAME') === 'null' ? '' : $value('MAIL_USERNAME', ''),
                'has_mail_password' => $hasEnv && ! in_array((string) $env->get('MAIL_PASSWORD'), ['', 'null'], true),
                'mail_from_address' => $value('MAIL_FROM_ADDRESS') === 'hello@example.com' ? '' : $value('MAIL_FROM_ADDRESS', ''),
                'mail_from_name' => $value('MAIL_FROM_NAME', '${APP_NAME}') === '${APP_NAME}' ? '' : $value('MAIL_FROM_NAME', ''),
            ],
        ]);
    }

    public function testDatabase(Request $request): JsonResponse
    {
        $data = $this->validateDatabase($request);

        try {
            $pdo = $this->connect($data);
            $version = $pdo->query('SELECT VERSION()')->fetchColumn();
            $tables = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
            $hasMigrations = (bool) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'migrations'")->fetchColumn();
        } catch (Throwable $e) {
            return $this->fail('Connexion impossible : '.$e->getMessage(), 422);
        }

        $message = "Connexion réussie (MySQL {$version}). ";
        $message .= match (true) {
            $tables === 0 => 'Base vide : les tables seront créées.',
            $hasMigrations => "{$tables} tables existantes : seules les migrations manquantes seront appliquées.",
            default => "Attention : {$tables} tables d'une autre application sont présentes dans cette base.",
        };

        return response()->json(['ok' => true, 'message' => $message, 'warning' => $tables > 0 && ! $hasMigrations]);
    }

    public function testMail(Request $request): JsonResponse
    {
        $data = $this->check($request, [
            'mail_host' => ['required', 'string', 'max:255'],
            'mail_port' => ['required', 'integer', 'between:1,65535'],
            'mail_scheme' => ['required', Rule::in(['smtp', 'smtps'])],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
        ]);

        $password = $this->keepExisting($data['mail_password'] ?? null, 'MAIL_PASSWORD');

        try {
            $transport = new EsmtpTransport($data['mail_host'], (int) $data['mail_port'], $data['mail_scheme'] === 'smtps');
            $transport->getStream()->setTimeout(10);
            if (! empty($data['mail_username'])) {
                $transport->setUsername($data['mail_username']);
                $transport->setPassword((string) $password);
            }
            $transport->start();
            $transport->stop();
        } catch (Throwable $e) {
            return $this->fail('Échec SMTP : '.$e->getMessage(), 422);
        }

        return response()->json(['ok' => true, 'message' => 'Serveur SMTP joignable, authentification acceptée.']);
    }

    public function run(Request $request, string $step): JsonResponse
    {
        @set_time_limit(300);

        try {
            return match ($step) {
                'env' => $this->writeEnv($request),
                'migrate' => $this->migrate(),
                'admin' => $this->createAdmin($request),
                'optimize' => $this->optimize(),
                'finish' => $this->finish(),
                default => abort(404),
            };
        } catch (ValidationException|HttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return $this->fail($e->getMessage());
        }
    }

    // ------------------------------------------------------------------
    // Étapes de finalisation
    // ------------------------------------------------------------------

    private function writeEnv(Request $request): JsonResponse
    {
        $db = $this->validateDatabase($request);

        $data = $this->check($request, [
            'app_name' => ['required', 'string', 'max:100'],
            'app_url' => ['required', 'url', 'max:255'],
            'app_env' => ['required', Rule::in(['production', 'staging'])],
            'app_timezone' => ['required', 'timezone'],
            'mail_mailer' => ['required', Rule::in(['smtp', 'log'])],
            'mail_host' => ['required_if:mail_mailer,smtp', 'nullable', 'string', 'max:255'],
            'mail_port' => ['required_if:mail_mailer,smtp', 'nullable', 'integer', 'between:1,65535'],
            'mail_scheme' => ['required_if:mail_mailer,smtp', 'nullable', Rule::in(['smtp', 'smtps'])],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_from_address' => ['required', 'email', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:100'],
        ]);

        // On refuse d'écrire un .env qui casserait le site : la base doit répondre.
        try {
            $this->connect($db);
        } catch (Throwable $e) {
            return $this->fail('Connexion à la base impossible : '.$e->getMessage(), 422);
        }

        $env = EnvFile::load();
        $appKey = $env->get('APP_KEY');
        $isNewKey = ! $appKey;
        $smtp = $data['mail_mailer'] === 'smtp';

        $env->setMany([
            'APP_NAME' => $data['app_name'],
            'APP_ENV' => $data['app_env'],
            // Une clé existante est conservée : la changer invaliderait les données chiffrées.
            'APP_KEY' => $appKey ?: 'base64:'.base64_encode(random_bytes(32)),
            'APP_DEBUG' => 'false',
            'APP_TIMEZONE' => $data['app_timezone'],
            'APP_URL' => rtrim($data['app_url'], '/'),
            'APP_LOCALE' => 'fr',
            'APP_FALLBACK_LOCALE' => 'fr',
            'APP_FAKER_LOCALE' => 'fr_BE',
            'LOG_STACK' => 'daily',
            'LOG_LEVEL' => $data['app_env'] === 'production' ? 'warning' : 'debug',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $db['db_host'],
            'DB_PORT' => (string) $db['db_port'],
            'DB_DATABASE' => $db['db_database'],
            'DB_USERNAME' => $db['db_username'],
            'DB_PASSWORD' => $db['db_password'],
            'SESSION_DRIVER' => 'database',
            'SESSION_SECURE_COOKIE' => str_starts_with($data['app_url'], 'https://') ? 'true' : 'false',
            'CACHE_STORE' => 'database',
            'QUEUE_CONNECTION' => 'database',
            'MAIL_MAILER' => $data['mail_mailer'],
            'MAIL_SCHEME' => $smtp ? $data['mail_scheme'] : 'null',
            'MAIL_HOST' => $smtp ? $data['mail_host'] : '127.0.0.1',
            'MAIL_PORT' => $smtp ? (string) $data['mail_port'] : '2525',
            'MAIL_USERNAME' => $smtp && ! empty($data['mail_username']) ? $data['mail_username'] : 'null',
            'MAIL_PASSWORD' => $smtp ? ($this->keepExisting($data['mail_password'] ?? null, 'MAIL_PASSWORD') ?: 'null') : 'null',
            'MAIL_FROM_ADDRESS' => $data['mail_from_address'],
            'MAIL_FROM_NAME' => ($data['mail_from_name'] ?? null) ?: $data['app_name'],
        ]);
        $env->save();

        // Un cache de config d'un déploiement précédent masquerait le nouveau .env.
        Artisan::call('config:clear');

        return response()->json([
            'ok' => true,
            'message' => $isNewKey ? '.env créé, clé APP_KEY générée.' : '.env mis à jour (APP_KEY conservée).',
        ]);
    }

    private function migrate(): JsonResponse
    {
        Artisan::call('migrate', ['--force' => true]);

        return response()->json([
            'ok' => true,
            'message' => 'Base de données à jour.',
            'output' => trim(Artisan::output()),
        ]);
    }

    private function createAdmin(Request $request): JsonResponse
    {
        if ($request->boolean('skip')) {
            abort_unless($this->adminExists(), 422, 'Aucun administrateur en base : ce compte est obligatoire.');

            return response()->json(['ok' => true, 'message' => 'Administrateur existant conservé.']);
        }

        $data = $this->check($request, [
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()],
        ]);

        $user = User::firstOrNew(['email' => $data['admin_email']]);
        $existed = $user->exists;
        $user->fill([
            'name' => $data['admin_name'],
            'password' => $data['admin_password'],
            'role' => 'admin',
        ])->save();

        return response()->json([
            'ok' => true,
            'message' => $existed
                ? "Compte {$user->email} mis à jour (rôle admin, nouveau mot de passe)."
                : "Administrateur {$user->email} créé.",
        ]);
    }

    private function optimize(): JsonResponse
    {
        $warnings = [];

        Artisan::call('optimize:clear');
        Artisan::call('config:cache');
        Artisan::call('route:cache');
        Artisan::call('view:cache');

        if (! file_exists(public_path('storage'))) {
            try {
                Artisan::call('storage:link');
            } catch (Throwable $e) {
                $warnings[] = 'Lien public/storage non créé ('.$e->getMessage().') : lancez `php artisan storage:link` en SSH.';
            }
        }

        return response()->json([
            'ok' => true,
            'message' => 'Caches de configuration, routes et vues générés.',
            'warnings' => $warnings,
        ]);
    }

    private function finish(): JsonResponse
    {
        file_put_contents($this->installedMarker(), json_encode([
            'installed_at' => now()->toIso8601String(),
        ]));

        InstallToken::revoke();

        return response()->json([
            'ok' => true,
            'message' => 'Installation terminée. Le lien de l\'assistant est désormais désactivé.',
            'login_url' => rtrim((string) config('app.url'), '/').'/connexion',
        ]);
    }

    // ------------------------------------------------------------------
    // Outils
    // ------------------------------------------------------------------

    /**
     * @return list<array{label: string, ok: bool, level: string, detail: string}>
     */
    private function requirements(Request $request): array
    {
        $checks = [];
        $add = function (string $label, bool $ok, string $detail, string $level = 'error') use (&$checks) {
            $checks[] = compact('label', 'ok', 'level', 'detail');
        };

        $add('PHP ≥ 8.2', version_compare(PHP_VERSION, '8.2.0', '>='),
            'Version '.PHP_VERSION.'. Sur OVH, réglez « app.engine.version » dans .ovhconfig.');

        $missing = array_values(array_filter(self::REQUIRED_EXTENSIONS, fn ($ext) => ! extension_loaded($ext)));
        $add('Extensions PHP', $missing === [],
            $missing === [] ? implode(', ', self::REQUIRED_EXTENSIONS) : 'Manquantes : '.implode(', ', $missing));

        $envTarget = EnvFile::exists() ? base_path('.env') : base_path();
        $add('.env modifiable', is_writable($envTarget),
            EnvFile::exists() ? 'backend/.env existe et sera mis à jour.' : 'backend/.env sera créé.');

        foreach (['storage', 'storage/framework/cache', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'] as $dir) {
            $path = base_path($dir);
            $add("{$dir}/ en écriture", is_dir($path) && is_writable($path), match (true) {
                ! is_dir($path) => 'Dossier absent : relancez le déploiement.',
                ! is_writable($path) => "Non inscriptible : chmod -R 755 {$dir} en SSH.",
                default => 'Inscriptible.',
            });
        }

        $add('Build React déployé', is_file(public_path('index.html')),
            is_file(public_path('index.html')) ? 'public/index.html présent.' : 'public/index.html absent : relancez scripts/deploy.sh.', 'warning');

        $add('HTTPS', $request->isSecure(),
            $request->isSecure() ? 'Connexion chiffrée.' : 'Activez le certificat SSL (Manager OVH > Multisite) avant la mise en production.', 'warning');

        return $checks;
    }

    /**
     * @return array{db_host: string, db_port: int, db_database: string, db_username: string, db_password: string}
     */
    private function validateDatabase(Request $request): array
    {
        $data = $this->check($request, [
            'db_host' => ['required', 'string', 'max:255'],
            'db_port' => ['required', 'integer', 'between:1,65535'],
            'db_database' => ['required', 'string', 'max:64'],
            'db_username' => ['required', 'string', 'max:64'],
            'db_password' => ['nullable', 'string', 'max:255'],
        ]);

        $data['db_password'] = (string) $this->keepExisting($data['db_password'] ?? null, 'DB_PASSWORD');

        return $data;
    }

    private function check(Request $request, array $rules): array
    {
        return Validator::make($request->all(), $rules, self::MESSAGES)->validate();
    }

    private function connect(array $db): PDO
    {
        return new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['db_host'], $db['db_port'], $db['db_database']),
            $db['db_username'],
            $db['db_password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5],
        );
    }

    /**
     * Champ mot de passe laissé vide = conserver la valeur du .env actuel
     * (les secrets ne sont jamais renvoyés au navigateur).
     */
    private function keepExisting(?string $submitted, string $key): ?string
    {
        if ($submitted !== null && $submitted !== '') {
            return $submitted;
        }

        if (! EnvFile::exists()) {
            return $submitted;
        }

        $existing = EnvFile::load()->get($key);

        return $existing === 'null' ? null : $existing;
    }

    private function adminExists(): bool
    {
        try {
            return User::where('role', 'admin')->exists();
        } catch (Throwable) {
            return false;
        }
    }

    private function installedMarker(): string
    {
        return storage_path('app/installed.json');
    }

    private function fail(string $message, int $status = 500): JsonResponse
    {
        return response()->json(['ok' => false, 'message' => $message], $status);
    }
}
