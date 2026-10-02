<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\Timesheet;
use App\Models\TimesheetAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * TS-01 T1 : adaptation d'une saisie soumise ou confirmée par le directeur/admin, avec motif obligatoire et trace.
 * Le total du mois n'est pas contraint ici (c'est le lissage, TS-01 T3) ; la date reste dans le mois d'origine.
 * Une saisie confirmée perd la signature du professeur : il devra reconfirmer (statuts dédiés : TS-01 T4).
 */
class TimesheetAdaptationService
{
    public const CHAMPS = ['nombre_heures', 'date_prestation', 'type_activite'];

    /** @param array<string, mixed> $changements sous-ensemble de CHAMPS */
    public function adapter(Timesheet $timesheet, User $auteur, array $changements, string $motif): Timesheet
    {
        return DB::transaction(function () use ($timesheet, $auteur, $changements, $motif) {
            $timesheet = Timesheet::lockForUpdate()->findOrFail($timesheet->id);

            if (! in_array($timesheet->statut_validation, [Timesheet::STATUT_SOUMIS, Timesheet::STATUT_CONFIRME, Timesheet::STATUT_CONTESTE], true)) {
                throw RegleMetierException::invalide('Seules les saisies soumises, confirmées ou contestées peuvent être adaptées.');
            }

            if (isset($changements['date_prestation'])) {
                if ($timesheet->course_session_id !== null) {
                    throw RegleMetierException::invalide('La date d\'une saisie liée à une session ne peut pas être modifiée.', ['date_prestation' => ['Date liée à la session.']]);
                }
                if (substr((string) $changements['date_prestation'], 0, 7) !== $timesheet->date_prestation->format('Y-m')) {
                    throw RegleMetierException::invalide('La date doit rester dans le mois de la saisie.', ['date_prestation' => ['Hors du mois.']]);
                }
            }

            $avant = $apres = [];
            foreach (self::CHAMPS as $champ) {
                if (! array_key_exists($champ, $changements)) {
                    continue;
                }
                $ancienne = $this->normaliser($champ, $timesheet->{$champ});
                $nouvelle = $this->normaliser($champ, $changements[$champ]);
                if ($ancienne !== $nouvelle) {
                    $avant[$champ] = $ancienne;
                    $apres[$champ] = $nouvelle;
                }
            }

            if ($apres === []) {
                throw RegleMetierException::invalide('Aucune modification à enregistrer.');
            }

            $timesheet->fill($apres);
            if ($timesheet->statut_validation === Timesheet::STATUT_CONFIRME) {
                $timesheet->signature_professeur = null;
            }
            $timesheet->save();

            TimesheetAudit::create([
                'timesheet_id' => $timesheet->id,
                'professeur_id' => $timesheet->professeur_id,
                'user_id' => $auteur->id,
                'action' => TimesheetAudit::ACTION_ADAPTATION,
                'avant' => $avant,
                'apres' => $apres,
                'motif' => $motif,
            ]);

            return $timesheet;
        });
    }

    private function normaliser(string $champ, mixed $valeur): mixed
    {
        return match ($champ) {
            'nombre_heures' => round((float) $valeur, 2),
            'date_prestation' => substr((string) ($valeur instanceof \DateTimeInterface ? $valeur->format('Y-m-d') : $valeur), 0, 10),
            default => (string) $valeur,
        };
    }
}
