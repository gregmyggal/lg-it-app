<?php

namespace App\Support\Installer;

use RuntimeException;

/**
 * Lecture / écriture de backend/.env en conservant les lignes existantes
 * (commentaires, clés non gérées par l'installeur).
 */
class EnvFile
{
    /** @var list<string> */
    private array $lines;

    private function __construct(private string $path, string $contents)
    {
        $this->lines = preg_split('/\R/', rtrim($contents, "\r\n"));
    }

    /**
     * Charge le .env existant, ou à défaut .env.example comme gabarit.
     */
    public static function load(?string $path = null): self
    {
        $path ??= base_path('.env');
        $source = is_file($path) ? $path : base_path('.env.example');

        return new self($path, is_file($source) ? (string) file_get_contents($source) : '');
    }

    public static function exists(): bool
    {
        return is_file(base_path('.env'));
    }

    public function get(string $key): ?string
    {
        foreach ($this->lines as $line) {
            if (preg_match('/^\s*'.preg_quote($key, '/').'\s*=(.*)$/', $line, $m)) {
                return self::decode(trim($m[1]));
            }
        }

        return null;
    }

    /**
     * Remplace la ligne active `KEY=`, sinon réactive une ligne commentée
     * `# KEY=` (cas des DB_* de .env.example), sinon ajoute en fin de fichier.
     */
    public function set(string $key, ?string $value): self
    {
        $line = $key.'='.self::encode($value ?? '');

        foreach (['/^\s*', '/^\s*#\s*'] as $prefix) {
            foreach ($this->lines as $i => $existing) {
                if (preg_match($prefix.preg_quote($key, '/').'\s*=/', $existing)) {
                    $this->lines[$i] = $line;

                    return $this;
                }
            }
        }

        $this->lines[] = $line;

        return $this;
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public function setMany(array $values): self
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }

        return $this;
    }

    /**
     * Écriture atomique (fichier temporaire + rename), lisible par l'utilisateur seul.
     */
    public function save(): void
    {
        $tmp = $this->path.'.tmp-'.bin2hex(random_bytes(4));

        if (file_put_contents($tmp, implode("\n", $this->lines)."\n") === false) {
            throw new RuntimeException("Impossible d'écrire dans ".dirname($this->path).'.');
        }

        @chmod($tmp, 0600);

        if (! rename($tmp, $this->path)) {
            @unlink($tmp);
            throw new RuntimeException("Impossible de remplacer {$this->path}.");
        }
    }

    private static function encode(string $value): string
    {
        if (preg_match('/[\r\n]/', $value)) {
            throw new RuntimeException('Les valeurs du .env ne peuvent pas contenir de retour à la ligne.');
        }

        if ($value === '' || preg_match('/^[A-Za-z0-9_.:\/@+=,-]+$/', $value)) {
            return $value;
        }

        // Guillemets doubles : phpdotenv accepte \" \\ et \$ (le $ seul serait interpolé).
        return '"'.addcslashes($value, '"\\$').'"';
    }

    private static function decode(string $raw): string
    {
        if (strlen($raw) >= 2 && $raw[0] === '"' && str_ends_with($raw, '"')) {
            return stripcslashes(substr($raw, 1, -1));
        }

        if (strlen($raw) >= 2 && $raw[0] === "'" && str_ends_with($raw, "'")) {
            return substr($raw, 1, -1);
        }

        // Valeur non quotée : un commentaire en fin de ligne n'en fait pas partie.
        return trim(preg_replace('/\s+#.*$/', '', $raw));
    }
}
