<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** ADMIN-02 : notification après définition du mot de passe (détection d'abus, sans lien d'action). */
class MotDePasseModifieMail extends Mailable
{
    public function __construct(public readonly string $nom) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre mot de passe a été modifié');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.acces.modifie', text: 'mail.acces.modifie-text');
    }
}
