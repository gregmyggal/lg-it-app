<?php

namespace App\Mail;

use App\Models\User;
use Carbon\CarbonInterface;

/** ADMIN-02 : lien de réinitialisation du mot de passe (demandé par la direction ou en libre-service). */
class ReinitialisationMotDePasseMail extends AccesMail
{
    public readonly string $expiration;

    public function __construct(
        User $user,
        public readonly string $lien,
        CarbonInterface $expireLe,
        public readonly string $validite,
        public readonly bool $parDirection = false,
        public readonly ?string $par = null,
    ) {
        parent::__construct($user);
        // Lien court (libre-service) : l'heure suffit ; sinon la date complète.
        $this->expiration = $parDirection ? self::dateHeure($expireLe) : self::heure($expireLe);
    }

    protected function objet(): string
    {
        return $this->parDirection
            ? ($this->par ?? 'La direction de votre école').' vous envoie un lien pour choisir un nouveau mot de passe'
            : 'Votre lien pour réinitialiser votre mot de passe (valable '.$this->validite.')';
    }

    protected function vue(): string
    {
        return 'reinitialisation';
    }
}
