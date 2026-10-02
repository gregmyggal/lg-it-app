<?php

namespace App\Services;

use App\Models\Professeur;
use App\Models\User;
use App\Notifications\NotificationTimesheet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * TS-01 T4 : envoi des notifications (cloche + email). Un échec d'envoi n'interrompt jamais l'action métier ;
 * une notification identique encore non lue n'est pas répétée (pas de spam lors d'adaptations successives).
 */
class TimesheetNotifier
{
    public function __construct(private readonly TimesheetConfirmationService $confirmation) {}

    /** Le mois du professeur attend sa signature (heures validées, ou ajustées après signature). */
    public function siMoisAConfirmer(Professeur $prof, int $annee, int $mois): void
    {
        if (! $prof->user || $this->confirmation->statutMois($prof, $annee, $mois) !== TimesheetSyntheseMoisService::STATUT_ATTENTE_PROF) {
            return;
        }
        $this->envoyer($prof->user, new NotificationTimesheet(
            NotificationTimesheet::MOIS_A_CONFIRMER,
            'Votre mois attend votre confirmation',
            'Vos heures de '.$this->libelle($annee, $mois).' ont été validées ou ajustées par la direction. Merci de les confirmer et signer, ou de les contester.',
            '/timesheets',
            $prof->id, $annee, $mois,
        ));
    }

    public function contestation(Professeur $prof, int $annee, int $mois, string $motif): void
    {
        $staff = User::whereIn('role', ['admin', 'directeur'])->where('statut', 'actif')->get();
        foreach ($staff as $user) {
            $this->envoyer($user, new NotificationTimesheet(
                NotificationTimesheet::CONTESTATION,
                'Contestation d\'un professeur',
                trim($prof->prenom.' '.$prof->nom).' conteste ses heures de '.$this->libelle($annee, $mois).' : « '.$motif.' »',
                "/admin/timesheets?professeur={$prof->id}&mois=".sprintf('%04d-%02d', $annee, $mois),
                $prof->id, $annee, $mois,
            ));
        }
    }

    public function contestationTraitee(Professeur $prof, int $annee, int $mois, string $reponse): void
    {
        if (! $prof->user) {
            return;
        }
        $this->envoyer($prof->user, new NotificationTimesheet(
            NotificationTimesheet::CONTESTATION_TRAITEE,
            'Votre contestation a été examinée',
            'La direction a répondu pour '.$this->libelle($annee, $mois).' : « '.$reponse.' ». Vos heures repassent en revue ; vous serez notifié pour les confirmer.',
            '/timesheets',
            $prof->id, $annee, $mois,
        ));
    }

    private function envoyer(User $destinataire, NotificationTimesheet $notification): void
    {
        $doublon = $destinataire->unreadNotifications()
            ->where('type', NotificationTimesheet::class)
            ->get()
            ->contains(fn ($n) => ($n->data['code'] ?? null) === $notification->code
                && ($n->data['professeur_id'] ?? null) === $notification->professeurId
                && ($n->data['annee'] ?? null) === $notification->annee
                && ($n->data['mois'] ?? null) === $notification->mois);
        if ($doublon) {
            return;
        }
        try {
            $destinataire->notify($notification);
        } catch (\Throwable $e) {
            Log::warning('Notification timesheet non envoyée : '.$e->getMessage());
        }
    }

    private function libelle(int $annee, int $mois): string
    {
        return Carbon::create($annee, $mois, 1)->locale('fr')->translatedFormat('F Y');
    }
}
