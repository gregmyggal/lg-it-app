# API — UI-M7 : aperçu d'impact du créneau/lieu d'une classe

## `POST /api/classes/{classe}/apercu-modification`

Direction/admin uniquement (même autorisation que `PUT /api/classes/{classe}` : professeur → 403, sans jeton → 401). **Aucune écriture.**

Corps (mêmes règles que `PUT /classes/{id}`) : `heure_debut`, `heure_fin` (`HH:MM`, fin > début, sinon 422), `lieu` (nullable).

Réponse 200 :

```json
{ "data": { "seances_a_mettre_a_jour": 12, "seances_inchangees": 2 } }
```

- `seances_a_mettre_a_jour` : séances de la classe `planifiee`, `date >= aujourd'hui (Europe/Brussels)`, dont au moins un des champs modifiés égale l'ancienne valeur de la classe (mêmes critères que `ClasseService::modifier`).
- `seances_inchangees` : autres séances actives (passées, déjà ajustées à la main…) ; les séances annulées ne sont comptées nulle part.

L'enregistrement reste `PUT /api/classes/{classe}` (inchangé), qui répercute la modification sur ces séances.

## Rappel : `PUT /api/professeurs/{id}`
Utilisé par la nouvelle modale « Modifier les informations » (prénom, nom, email de contact, téléphone, type de contrat, date d'entrée). L'email de connexion (`/compte`) et l'IBAN gardent leurs écrans dédiés.
