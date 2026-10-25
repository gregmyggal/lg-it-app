# API — UI-M6 : « Aujourd'hui » direction, fiche séance, remplacement atomique

Tous les endpoints exigent un jeton Sanctum ; **direction/admin uniquement** (professeur → 403, sans jeton → 401). Dates `YYYY-MM-DD`, heures `HH:MM`, Europe/Brussels.

| Méthode | Chemin | Rôle |
|---|---|---|
| GET | `/api/accueil-direction` | Séances d'aujourd'hui et de demain (non annulées) avec leurs professeurs non remplacés (`id, nom, telephone, role, origine`), flags `est_aujourdhui`, `en_cours`, `terminee`, et `alertes {sans_professeur (7 prochains jours), timesheets_a_valider (mois courant)}` |
| GET | `/api/sessions/{id}` | Fiche séance (`CourseSessionResource`) : professeurs avec `remplace`, `remplacant_a_trouver`, `telephone` (direction seulement), `can.update/cancel` |
| GET | `/api/sessions/{id}/candidats` | Professeurs actifs absents de la séance : `{id, nom, telephone, conflits[]}` (conflit = séance non annulée qui chevauche) |
| GET | `/api/professeurs/{id}/seances?du=&au=` | Séances non annulées où le professeur n'est pas remplacé (défaut : 7 jours) : `{id, classe_libelle, libelle_complet, date, heure_debut, heure_fin, lieu, heures_encodees}` |
| POST | `/api/professeurs/{id}/remplacements/apercu` | Corps `{session_ids[], remplacant_id}` : mêmes règles que l'application, **aucune écriture** → `{data: {seances, avertissements[]}}` |
| POST | `/api/professeurs/{id}/remplacements` | Même corps : remplacement **atomique** (tout ou rien) puis **un seul** mail + notification au remplaçant |
| POST | `/api/sessions/{id}/remplacer` | (existant) prévient désormais aussi le remplaçant (mail + notification) |

## Erreurs
- 409 : une séance du lot est annulée (rien n'est modifié).
- 422 : remplacé non assigné ou déjà remplacé, remplaçant déjà sur la séance, remplaçant identique/inactif, séance inconnue (rien n'est modifié).
- `avertissements[]` : conflits d'horaire du remplaçant (non bloquants), `{date, session_id, classe, heure_debut, heure_fin}`.

## Notification
`NotificationRemplacement` : canaux base + mail (si le compte a défini son mot de passe). Titre « Vous remplacez {nom} [sur N séances] », lien `/aujourdhui`. Un échec d'envoi est journalisé et n'annule jamais le remplacement. Aucun mail pour un simple ajout ponctuel (CLS-04).
