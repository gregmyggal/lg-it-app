<?php

namespace App\Notifications;

use App\Support\FrontendUrl;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * UI-M6 : le remplaçant est prévenu (cloche + mail) quand la direction le met à la place d'un professeur.
 * Un remplacement en lot = une seule notification qui liste les séances.
 */
class NotificationRemplacement extends Notification
{
    /**
     * @param  list<array{date: string, heure_debut: string, heure_fin: string, cours: string, libelle: string}>  $seances
     */
    public function __construct(
        public readonly string $remplace,
        public readonly array $seances,
    ) {}

    public function via(object $notifiable): array
    {
        return $notifiable instanceof \App\Models\User && ! $notifiable->peutRecevoirEmailNotification()
            ? ['database']
            : ['database', 'mail'];
    }

    private function titre(): string
    {
        return count($this->seances) > 1 ? 'Vous remplacez '.$this->remplace.' sur '.count($this->seances).' séances' : 'Vous remplacez '.$this->remplace;
    }

    /** @return list<string> */
    private function lignes(): array
    {
        return array_map(
            fn (array $s) => $this->date($s['date']).', '.$s['heure_debut'].'–'.$s['heure_fin'].' : '.$s['cours'].' ('.$s['libelle'].')',
            $this->seances
        );
    }

    private function date(string $iso): string
    {
        return \Illuminate\Support\Carbon::parse($iso)->locale('fr')->isoFormat('dddd D MMMM');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'code' => 'remplacement',
            'titre' => $this->titre(),
            'message' => implode(' · ', $this->lignes()),
            'url' => '/aujourdhui',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->titre())
            ->greeting('Bonjour,')
            ->line('La direction vous a assigné '.(count($this->seances) > 1 ? 'les séances suivantes' : 'la séance suivante').', à la place de '.$this->remplace.' :');

        foreach ($this->lignes() as $ligne) {
            $mail->line('• '.$ligne);
        }

        return $mail->action('Voir ma journée', FrontendUrl::lien('/aujourdhui'));
    }
}
