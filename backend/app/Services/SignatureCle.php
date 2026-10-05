<?php

namespace App\Services;

/**
 * SIG-01 : sceau Ed25519 (libsodium) du serveur. La clé privée vient de la configuration ; à défaut, elle est dérivée
 * d'APP_KEY (identifiant « app »). Un hash seul se recalcule par n'importe qui ; le sceau prouve que la preuve a été
 * émise par l'application et n'a pas été modifiée depuis.
 */
class SignatureCle
{
    public function id(): string
    {
        return $this->configuree() !== null ? (string) config('signature.cle_id') : 'app';
    }

    public function sceller(string $message): string
    {
        return base64_encode(sodium_crypto_sign_detached($message, $this->secrete()));
    }

    public function verifier(string $message, string $sceau, string $cleId): bool
    {
        $publique = $cleId === $this->id()
            ? sodium_crypto_sign_publickey_from_secretkey($this->secrete())
            : base64_decode((string) (config('signature.cles_publiques')[$cleId] ?? ''), true);
        $sceau = base64_decode($sceau, true);
        if (! $publique || strlen($publique) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES || ! $sceau || strlen($sceau) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }

        return sodium_crypto_sign_verify_detached($sceau, $message, $publique);
    }

    /** Nouvelle paire de clés (commande `signature:cle`). @return array{privee: string, publique: string} */
    public static function generer(): array
    {
        $paire = sodium_crypto_sign_keypair();

        return [
            'privee' => base64_encode(sodium_crypto_sign_secretkey($paire)),
            'publique' => base64_encode(sodium_crypto_sign_publickey($paire)),
        ];
    }

    private function configuree(): ?string
    {
        $cle = base64_decode((string) config('signature.cle_privee'), true);

        return $cle && strlen($cle) === SODIUM_CRYPTO_SIGN_SECRETKEYBYTES ? $cle : null;
    }

    private function secrete(): string
    {
        if ($cle = $this->configuree()) {
            return $cle;
        }

        $graine = hash_hmac('sha256', 'sig-01-sceau', (string) config('app.key'), true);

        return sodium_crypto_sign_secretkey(sodium_crypto_sign_seed_keypair($graine));
    }
}
