<?php

namespace App\Mail;

use App\Models\DepotPartage;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** DEP-01 T3 : lien parent. Aucune donnée d'enfant dans l'objet ; le corps ne cite que le stage (cours et dates). */
class DepotLienMail extends Mailable
{
    public readonly string $ecole;

    public readonly ?string $contact;

    public readonly string $objet;

    public function __construct(
        public readonly string $lien,
        public readonly string $expiration,
        public readonly int $nbFichiers,
        public readonly ?string $stage,
    ) {
        $this->ecole = (string) config('logiscool.ecole.nom');
        $this->contact = config('logiscool.ecole.contact') ?: null;
        $this->objet = 'Les créations de votre enfant — '.$this->ecole;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->objet,
            replyTo: $this->contact ? [new Address($this->contact)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.depot.lien', text: 'mail.depot.lien-text');
    }

    /** « Scratch, du 17 au 21 août 2026 » à partir du cours et des séances du stage. */
    public static function descriptionStage(DepotPartage $partage): ?string
    {
        $classe = $partage->classe;
        $cours = $classe->periodes()->with('cours')->first()?->cours?->titre;
        $debut = $classe->sessionsActives()->min('date');
        $fin = $classe->sessionsActives()->max('date');
        if ($debut === null) {
            return $cours;
        }

        $d = \Illuminate\Support\Carbon::parse($debut)->locale('fr');
        $f = \Illuminate\Support\Carbon::parse($fin)->locale('fr');
        $dates = match (true) {
            $d->isSameDay($f) => $d->isoFormat('D MMMM YYYY'),
            $d->isSameMonth($f) => 'du '.$d->isoFormat('D').' au '.$f->isoFormat('D MMMM YYYY'),
            default => 'du '.$d->isoFormat('D MMMM').' au '.$f->isoFormat('D MMMM YYYY'),
        };

        return $cours ? "$cours, $dates" : $dates;
    }
}
