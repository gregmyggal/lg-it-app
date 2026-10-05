<?php

namespace App\Mail;

use App\Models\User;
use Carbon\CarbonInterface;

/** ADMIN-02 : confirmation après définition du mot de passe (activation ou modification ; détection d'abus). */
class MotDePasseModifieMail extends AccesMail
{
    public readonly string $date;

    public function __construct(User $user, public readonly bool $activation, CarbonInterface $modifieLe)
    {
        parent::__construct($user);
        $this->date = self::dateHeure($modifieLe, annee: true);
    }

    protected function objet(): string
    {
        return $this->activation
            ? 'Votre compte '.$this->ecole.' est activé'
            : 'Votre mot de passe '.$this->ecole.' a été modifié';
    }

    protected function vue(): string
    {
        return 'modifie';
    }
}
