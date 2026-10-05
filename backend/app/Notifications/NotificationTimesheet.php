<?php

namespace App\Notifications;

use App\Support\FrontendUrl;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** TS-01 T4 : notification de la validation mensuelle (cloche + email), avec lien vers l'écran concerné. */
class NotificationTimesheet extends Notification
{
    public const MOIS_A_CONFIRMER = 'mois_a_confirmer';

    public const CONTESTATION = 'contestation';

    public const CONTESTATION_TRAITEE = 'contestation_traitee';

    public function __construct(
        public readonly string $code,
        public readonly string $titre,
        public readonly string $message,
        public readonly string $chemin,
        public readonly int $professeurId,
        public readonly int $annee,
        public readonly int $mois,
    ) {}

    public function via(object $notifiable): array
    {
        return $notifiable instanceof \App\Models\User && ! $notifiable->peutRecevoirEmailNotification()
            ? ['database']
            : ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'code' => $this->code,
            'titre' => $this->titre,
            'message' => $this->message,
            'url' => $this->chemin,
            'professeur_id' => $this->professeurId,
            'annee' => $this->annee,
            'mois' => $this->mois,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->titre)
            ->greeting('Bonjour,')
            ->line($this->message)
            ->action('Ouvrir', FrontendUrl::lien($this->chemin));
    }
}
