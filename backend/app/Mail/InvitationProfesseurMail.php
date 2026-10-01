<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** PROF-01 : invitation (création, réactivation, réinitialisation) avec mot de passe provisoire. */
class InvitationProfesseurMail extends Mailable
{
    public function __construct(
        public readonly string $prenom,
        public readonly string $loginEmail,
        public readonly string $motDePasse,
        public readonly string $motif,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: match ($this->motif) {
            'reactivation' => 'Votre accès à la plateforme est réactivé',
            'reinitialisation' => 'Votre mot de passe a été réinitialisé',
            default => 'Bienvenue sur la plateforme Logiscool',
        });
    }

    public function content(): Content
    {
        return new Content(text: 'mail.invitation-professeur');
    }
}
