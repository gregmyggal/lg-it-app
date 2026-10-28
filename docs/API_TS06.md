# API — TS-06 Notes internes (tranches T1 et T2)

Contexte : `docs/requirements/TS-06-notes-internes.md`. **Réservé à la direction et à l'admin** : 403 pour le professeur, 401 sans authentification. Les notes ne sont jamais renvoyées par une route accessible au professeur (testé sur Mon mois, la confirmation, les versions, la liste et le détail des saisies, les notifications et la signature).

## Routes
- `PUT /api/professeurs/{professeur}/timesheets-mois/notes` `{ annee, mois, timesheet_id?, texte }` — crée la note, ou remplace son texte (l'ancienne version va dans l'historique). `timesheet_id` nul = note du mois ; sinon la saisie doit appartenir au professeur et au mois (422). `texte` : 1 à 1000 caractères. Réponse : `{ id, updated_at, mois, lignes }` (voir ci-dessous).
- `DELETE /api/professeurs/{professeur}/timesheets-mois/notes?annee=&mois=&timesheet_id=` — supprime la note et son historique (404 si absente).
- `GET /api/professeurs/{professeur}/timesheets-mois/notes/{note}/historique` — versions remplacées, de la plus récente à la plus ancienne : `{ data: [{ texte, remplace_par, remplace_le }] }`.

Modifier ou supprimer la note d'un autre auteur : **403** sauf pour l'admin (modération).

## Lecture
`GET /api/professeurs/{professeur}/timesheets-mois` renvoie en plus :
```
notes_internes: {
  mois:   { id, texte, auteur: {id, nom, role}, created_at, updated_at, modifications, peut_modifier } | null,
  lignes: { "<timesheet_id>": { …même forme… } }
}
```

## Règles
- Une seule note par mois et une seule par saisie ; stockage séparé de `timesheets` (`timesheet_notes`, `timesheet_note_historique`), car `TimesheetResource` expose tous les attributs d'une saisie.
- Aucune note dans les versions (empreinte et snapshot inchangés), le PDF, la signature, les notifications et e-mails.
- Suppression d'une saisie ou d'un professeur : ses notes disparaissent (clés étrangères en cascade).
- **Rétention** : `php artisan timesheets:purger-notes [--simulation]`, planifiée à 04:15 ; supprime (historique compris) les notes dont le mois s'est terminé il y a plus de 24 mois.

## Interface (écran directeur)
Carte repliable « Note interne du mois » ; icône cadenas par ligne (colonne Actions) ouvrant la note sous la ligne (feuille modale sur smartphone) ; mention « Note interne — visible uniquement par la direction et l'administration » à chaque zone de note ; suppression annulable pendant 8 secondes ; événement « Note interne ajoutée » dans la frise (sans le texte).

## T2 — modale de validation
- `GET /api/timesheets-mois/notes-presence?annee=&mois=&professeur_ids[]=…` (staff, 200 professeurs au plus) → `{ data: [{ professeur_id, nom, mois: <note du mois> | null, lignes: <nombre de notes de ligne> }] }`.
- `ValiderLotModal` : pour **un seul professeur**, champ facultatif replié « Ajouter une note interne (optionnel) » (la note existante s'affiche en lecture seule avec « Modifier »). La note est enregistrée **après** la validation : elle ne conditionne jamais la validation, et un échec d'enregistrement est signalé sans l'annuler. Pour **plusieurs professeurs** : aucun champ, rappel « N professeurs ont une note interne ».
