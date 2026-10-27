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
        if (! $prof->user) {
            return;
        }
        if ($this->confirmation->statutMois($prof, $annee, $mois) !== TimesheetSyntheseMoisService::STATUT_ATTENTE_PROF) {
            // Le mois n'est pas encore complet côté direction : le professeur est tout de même prévenu du changement.
            $this->envoyer($prof->user, new NotificationTimesheet(
                NotificationTimesheet::HEURES_TRAITEES,
                'Vos heures ont été traitées',
                'La direction a validé ou ajusté une partie de vos heures de '.$this->libelle($annee, $mois).'. Vous serez prévenu quand le mois entier attendra votre confirmation.',
                '/timesheets',
                $prof->id, $annee, $mois,
            ));

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

    /** Le professeur a soumis son mois : la direction et l'admin sont prévenus (pas de dédoublonnage : acte rare et distinct). */
    public function moisSoumis(Professeur $prof, int $annee, int $mois): void
    {
        $this->prevenirDirection(NotificationTimesheet::MOIS_SOUMIS, 'Un professeur a soumis son mois', ' a soumis ses heures de ', ' : elles attendent votre validation.', $prof, $annee, $mois);
    }

    /** Le professeur a signé son mois : la fiche est prête pour la génération du PDF. */
    public function moisSigne(Professeur $prof, int $annee, int $mois): void
    {
        $this->prevenirDirection(NotificationTimesheet::MOIS_SIGNE, 'Un professeur a signé son mois', ' a confirmé et signé ses heures de ', ' : la fiche peut être générée.', $prof, $annee, $mois);
    }

    /** La direction a généré la fiche PDF du mois (les heures passent en « généré »). */
    public function pdfGenere(Professeur $prof, int $annee, int $mois): void
    {
        if (! $prof->user) {
            return;
        }
        $this->envoyer($prof->user, new NotificationTimesheet(
            NotificationTimesheet::PDF_GENERE,
            'Votre fiche de défraiement est prête',
            'La fiche de '.$this->libelle($annee, $mois).' a été générée par la direction : votre mois est clôturé.',
            '/timesheets',
            $prof->id, $annee, $mois,
        ), dedoublonner: false);
    }

    private function prevenirDirection(string $code, string $titre, string $milieu, string $fin, Professeur $prof, int $annee, int $mois): void
    {
        $staff = User::whereIn('role', ['admin', 'directeur'])->where('statut', 'actif')->get();
        foreach ($staff as $user) {
            $this->envoyer($user, new NotificationTimesheet(
                $code,
                $titre,
                trim($prof->prenom.' '.$prof->nom).$milieu.$this->libelle($annee, $mois).$fin,
                "/admin/timesheets?professeur={$prof->id}&mois=".sprintf('%04d-%02d', $annee, $mois),
                $prof->id, $annee, $mois,
            ), dedoublonner: false);
        }
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

    /** TS-02 : la direction a renvoyé le mois en brouillon (pas de dédoublonnage : chaque motif est distinct). */
    public function remiseBrouillon(Professeur $prof, int $annee, int $mois, string $motif): void
    {
        if (! $prof->user) {
            return;
        }
        $this->envoyer($prof->user, new NotificationTimesheet(
            NotificationTimesheet::REMISE_BROUILLON,
            'Vos heures ont été rouvertes',
            'La direction a remis vos heures de '.$this->libelle($annee, $mois).' en brouillon : « '.$motif.' ». Merci de les corriger puis de les soumettre à nouveau.',
            '/timesheets',
            $prof->id, $annee, $mois,
        ), dedoublonner: false);
    }

    /** TS-05 : la direction a soumis le mois à la place du professeur (pas de dédoublonnage : chaque message est distinct). */
    public function soumissionForcee(Professeur $prof, int $annee, int $mois, User $auteur, array $resume, string $message): void
    {
        if (! $prof->user) {
            return;
        }
        $libelle = $this->libelle($annee, $mois);
        $this->envoyer($prof->user, new NotificationTimesheet(
            NotificationTimesheet::SOUMISSION_FORCEE,
            ucfirst($libelle).' : vos heures ont été soumises par la direction',
            $auteur->name.' a soumis vos heures de '.$libelle.' en l\'état : '.$resume['lignes'].' ligne(s), '.str_replace('.', ',', (string) $resume['heures']).' h, '
                .number_format($resume['total_eur'], 2, ',', ' ').' €. Son message : « '.$message.' ». Vous serez prévenu quand le mois aura été validé ; en cas d\'erreur, contactez la direction.',
            '/timesheets',
            $prof->id, $annee, $mois,
        ), dedoublonner: false);
    }

    private function envoyer(User $destinataire, NotificationTimesheet $notification, bool $dedoublonner = true): void
    {
        $doublon = $dedoublonner && $destinataire->unreadNotifications()
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
            Log::warning('Notification timesheet non envoyée', ['exception' => get_class($e)]);
        }
    }

    private function libelle(int $annee, int $mois): string
    {
        return Carbon::create($annee, $mois, 1)->locale('fr')->translatedFormat('F Y');
    }
}
