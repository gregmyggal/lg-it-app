<?php

namespace App\Mail;

use App\Models\User;
use Carbon\CarbonInterface;

/** ADMIN-02 : invitation à définir son mot de passe (aucun mot de passe dans l'email). */
class InvitationCompteMail extends AccesMail
{
    public readonly string $expiration;

    /** @param  string  $role  libellé : professeur, direction ou administrateur */
    public function __construct(
        User $user,
        public readonly string $role,
        public readonly string $lien,
        CarbonInterface $expireLe,
        public readonly string $validite,
        public readonly ?string $par = null,
    ) {
        parent::__construct($user);
        $this->expiration = self::dateHeure($expireLe);
    }

    protected function objet(): string
    {
        return $this->role === 'professeur'
            ? 'Votre accès '.$this->ecole.' est prêt – choisissez votre mot de passe'
            : 'Activez votre accès '.$this->role.' – '.$this->ecole;
    }

    protected function vue(): string
    {
        return 'invitation';
    }
}
