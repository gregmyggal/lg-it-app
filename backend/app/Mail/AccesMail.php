<?php

namespace App\Mail;

use App\Models\User;
use App\Support\FrontendUrl;
use Carbon\CarbonInterface;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * ADMIN-02 : socle des emails d'accès (gabarit commun mail/acces/layout).
 * Les URL sont calculées à la construction, pendant la requête : elles suivent l'environnement d'où vient l'action.
 */
abstract class AccesMail extends Mailable
{
    public readonly string $prenom;

    public readonly string $email;

    public readonly string $ecole;

    public readonly ?string $contact;

    public readonly string $urlConnexion;

    public readonly string $urlOubli;

    public function __construct(User $user)
    {
        $this->prenom = $user->professeur?->prenom ?: $user->name;
        $this->email = $user->email;
        $this->ecole = (string) config('logiscool.ecole.nom');
        $this->contact = config('logiscool.ecole.contact') ?: null;
        $this->urlConnexion = FrontendUrl::lien('/connexion');
        $this->urlOubli = FrontendUrl::lien('/mot-de-passe-oublie');
    }

    abstract protected function objet(): string;

    /** Nom des vues : mail/acces/{vue} (HTML) et mail/acces/{vue}-text. */
    abstract protected function vue(): string;

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->objet(),
            replyTo: $this->contact ? [new Address($this->contact)] : [],
        );
    }

    /** « jeudi 8 octobre à 14 h 30 » (heure belge). */
    public static function dateHeure(CarbonInterface $date, bool $annee = false): string
    {
        return $date->copy()->timezone('Europe/Brussels')->locale('fr')
            ->isoFormat('dddd D MMMM '.($annee ? 'YYYY ' : '').'[à] H [h] mm');
    }

    /** « 15 h 12 » (heure belge). */
    public static function heure(CarbonInterface $date): string
    {
        return $date->copy()->timezone('Europe/Brussels')->isoFormat('H [h] mm');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.acces.'.$this->vue(),
            text: 'mail.acces.'.$this->vue().'-text',
            with: ['objet' => $this->objet()],
        );
    }
}
