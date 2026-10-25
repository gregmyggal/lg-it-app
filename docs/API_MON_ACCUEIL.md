# API — `GET /api/mon-accueil` (UI-M3)

Page d'arrivée « Aujourd'hui » du professeur, en une requête. Lecture seule.

- **Auth :** Sanctum. **Rôle :** professeur uniquement (`Gate create Timesheet`) ; directeur/admin → 403 ; sans jeton → 401.
- **Fuseau :** « aujourd'hui », « commencée » et « mois courant » se calculent en Europe/Brussels.

## Réponse 200

```json
{
  "date": "2026-10-25", "annee": 2026, "mois": 10, "mois_soumis": false,
  "prochaine_seance": {
    "id": 17, "libelle": "Séance 3", "libelle_complet": "P1 · Séance 3",
    "classe_id": 4, "classe_libelle": "React", "cours_id": 9,
    "date": "2026-11-04", "heure_debut": "14:00", "heure_fin": "17:00",
    "duree_par_defaut": 2, "duree_seance": 3, "peut_encoder": true,
    "lieu": "Salle A", "est_aujourdhui": false, "en_cours": false,
    "remplace": null, "co_professeurs": ["Bob Martin"]
  },
  "a_encoder": [
    { "id": 16, "libelle": "Séance 2", "libelle_complet": "P1 · Séance 2", "classe_id": 4, "classe_libelle": "React",
      "date": "2026-10-14", "heure_debut": "14:00", "heure_fin": "17:00", "duree_par_defaut": 2, "duree_seance": 3, "peut_encoder": true }
  ],
  "progression": { "seances_total": 3, "seances_encodees": 1, "heures": 2, "montant": 20 }
}
```

- `prochaine_seance` : première séance non annulée où le professeur n'est pas remplacé et qui n'est pas terminée (à venir, ou aujourd'hui avec heure de fin non atteinte) ; `null` sinon. `remplace` = nom du professeur remplacé quand il est remplaçant.
- `a_encoder` : séances du **mois courant**, commencées, non annulées, sans saisie du professeur, de la plus récente à la plus ancienne ; vide si le mois est soumis.
- `progression` : séances du mois (non annulées, non remplacées) encodées / total ; `heures` et `montant` identiques à `synthese` de `GET /timesheets/mon-mois`.

## Encoder / annuler (routes existantes)

- Encoder en 1 tap : `POST /api/timesheets {course_session_id, nombre_heures: duree_par_defaut}` → 201 (brouillon). Mêmes règles que « Mon mois » (mois courant, mois non soumis, doublon, séance annulée → 422).
- Annuler dans les 5 s : `DELETE /api/timesheets/{id}`.
