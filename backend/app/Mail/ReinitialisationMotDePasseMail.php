<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** ADMIN-02 : lien de réinitialisation du mot de passe. */
class ReinitialisationMotDePasseMail extends Mailable
{
    public function __construct(
        public readonly string $nom,
        public readonly string $lien,
        public readonly string $validite,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Réinitialisation de votre mot de passe Logiscool');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.acces.reinitialisation', text: 'mail.acces.reinitialisation-text');
    }
}
