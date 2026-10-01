<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** ADMIN-02 : invitation à définir son mot de passe (aucun mot de passe dans l'email). */
class InvitationCompteMail extends Mailable
{
    public function __construct(
        public readonly string $nom,
        public readonly string $role,
        public readonly string $lien,
        public readonly string $validite,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Activez votre compte Logiscool Pays Vert');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.acces.invitation', text: 'mail.acces.invitation-text');
    }
}
