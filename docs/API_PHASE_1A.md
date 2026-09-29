# API Phase 1A - Timesheets + Tarifs

## Endpoints Créés

### Tarifs Professeurs

#### 1. Lister les tarifs d'un professeur
```
GET /api/professeurs/{professeur_id}/tarifs
Authorization: Bearer {token}
Permissions: admin, directeur

Response:
[
  {
    "id": 1,
    "professeur_id": 5,
    "tarif_horaire_eur": 7.50,
    "date_debut": "2026-09-28",
    "date_fin": null,
    "created_at": "2026-09-28T10:00:00Z",
    "updated_at": "2026-09-28T10:00:00Z"
  },
  ...
]
```

#### 2. Créer un tarif
```
POST /api/professeurs/{professeur_id}/tarifs
Authorization: Bearer {token}
Permissions: admin seulement

Request Body:
{
  "tarif_horaire_eur": 7.50,
  "date_debut": "2026-09-28",
  "date_fin": null
}

Response: 201 Created
{
  "id": 1,
  "professeur_id": 5,
  "tarif_horaire_eur": 7.50,
  "date_debut": "2026-09-28",
  "date_fin": null,
  "created_at": "2026-09-28T10:00:00Z",
  "updated_at": "2026-09-28T10:00:00Z"
}
```

#### 3. Mettre à jour un tarif
```
PUT /api/professeurs/{professeur_id}/tarifs/{tarif_id}
Authorization: Bearer {token}
Permissions: admin seulement

Request Body:
{
  "tarif_horaire_eur": 8.00,
  "date_debut": "2026-10-01"
}

Response: 200 OK
{
  "id": 1,
  "professeur_id": 5,
  "tarif_horaire_eur": 8.00,
  "date_debut": "2026-10-01",
  "date_fin": null,
  ...
}
```

#### 4. Terminer un tarif (définir date_fin)
```
POST /api/professeurs/{professeur_id}/tarifs/{tarif_id}/terminate
Authorization: Bearer {token}
Permissions: admin seulement

Request Body:
{
  "date_fin": "2026-09-30"
}

Response: 200 OK
{
  "id": 1,
  "date_fin": "2026-09-30",
  ...
}
```

#### 5. Récupérer le tarif effectif à une date donnée
```
GET /api/professeurs/{professeur_id}/tarif-effectif?date=2026-09-28
Authorization: Bearer {token}
Permissions: admin, directeur

Response: 200 OK
{
  "id": 1,
  "professeur_id": 5,
  "tarif_horaire_eur": 7.50,
  "date_debut": "2026-09-28",
  "date_fin": null
}

# Si pas de tarif valide:
Response: 404 Not Found
{
  "error": "No effective tariff found"
}
```

#### 6. Supprimer un tarif
```
DELETE /api/professeurs/{professeur_id}/tarifs/{tarif_id}
Authorization: Bearer {token}
Permissions: admin seulement

Response: 204 No Content
```

---

### Timesheets (Modifications)

Endpoints existants modifiés pour supporter le nouveau workflow 4-étapes.

#### Champs Modifiés dans Responses

```
{
  "id": 1,
  "professeur_id": 5,
  "date_prestation": "2026-09-28",
  "nombre_heures": 2.0,
  
  // NOUVEAU: Type d'activité
  "type_activite": "animation",  // ou "preparation"
  
  // MODIFIÉ: Nouvel enum
  "statut_validation": "brouillon",  // "brouillon" | "soumis" | "confirmé" | "généré"
  
  "cours_id": null,
  "commentaire": "Cours d'animation 3D",
  
  // NOUVEAU: Lissage et signature
  "lissage_applique": false,
  "signature_professeur": null,  // Timestamp quand prof signe
  
  // NOUVEAU: Génération PDF
  "pdf_generated_at": null,
  "pdf_generated_by": null,
  
  // Champs existants
  "validated_at": "2026-09-28T10:00:00Z",
  "validated_by": 3,
  "created_at": "2026-09-28T09:00:00Z",
  "updated_at": "2026-09-28T10:00:00Z",
  
  // Relations
  "professeur": { ... },
  "cours": null,
  "validateur": { ... }
}
```

#### 1. Créer un timesheet
```
POST /api/timesheets
Authorization: Bearer {token}

Request Body:
{
  "professeur_id": 5,
  "date_prestation": "2026-09-28",
  "nombre_heures": 2.0,
  "type_activite": "animation",  // NOUVEAU
  "cours_id": null,
  "commentaire": "Cours d'animation"
}

Response: 201 Created
```

#### 2. Soumettre un timesheet (brouillon → soumis)
```
POST /api/timesheets/{timesheet_id}/submit
Authorization: Bearer {token}
Permissions: professeur propriétaire seulement

Response: 200 OK
{
  "statut_validation": "soumis",
  ...
}
```

#### 3. Valider un timesheet (soumis → confirmé)
```
POST /api/timesheets/{timesheet_id}/validate
Authorization: Bearer {token}
Permissions: directeur, admin

Request Body: (optionnel)
{
  "lissage_applique": false  // true si directeur a lissé
}

Response: 200 OK
{
  "statut_validation": "confirmé",
  "validated_at": "2026-09-28T11:00:00Z",
  "validated_by": 3,
  ...
}
```

#### 4. Nouveaux Endpoints (Phase 1B)

Ces endpoints seront ajoutés dans Phase 1B:

```
# Signer un timesheet (confirmé → signé)
POST /api/timesheets/{timesheet_id}/sign
Permissions: professeur propriétaire

# Générer PDF de défraiement
POST /api/timesheets/generate-pdf
Permissions: directeur, admin
Body: { professeur_id, year_month }

# Récupérer le PDF généré
GET /api/timesheets/{timesheet_id}/pdf
Permissions: directeur, admin
```

---

## Logique Métier

### Calcul du Montant Défraiement

**Formula:**
```
montant_brut = nombre_heures × tarif_horaire_eur
```

**Exemple:**
```
Professeur: Jean Dupont (ID 5)
Date: 2026-09-28
Heures: 2.0 h
Type: "animation"
Tarif (depuis 2026-09-28): 7.50 €/h

Calcul: 2.0 × 7.50 = 15.00 €
```

### Validation Conformité

**Limites par jour:**
- Max 44,02 €/jour
- Calcul: somme tous les montants du même jour ≤ 44,02

**Limites par an:**
- Max 1.760,83 €/an
- Calcul: somme tous les montants de l'année ≤ 1.760,83

**Lissage (si dépassement):**
1. Directeur clique "Lissage" sur un jour > 44,02 €
2. Système détermine: combien d'heures doivent être déplacées
3. Système cherche un jour proche avec capacité disponible
4. Directeur confirme ou ajuste manuellement
5. Migration effectuée, flag `lissage_applique = true`

---

## Workflow d'Encodage Complet (Phase 1A + 1B)

```
1. INTRODUCTION (Professeur encode)
   POST /timesheets
   statut_validation = "brouillon"
   Professeur peut: créer, modifier, supprimer (tant qu'en brouillon)

2. SOUMISSION (Professeur soumet)
   POST /timesheets/{id}/submit
   statut_validation = "brouillon" → "soumis"
   Professeur ne peut plus modifier

3. VALIDATION (Directeur valide + lisse si nécessaire)
   POST /timesheets/{id}/validate
   statut_validation = "soumis" → "confirmé"
   Directeur peut appliquer lissage si dépassement

4. CONFIRMATION & SIGNATURE (Professeur confirme)
   [Phase 1B] POST /timesheets/{id}/sign
   Voit: tableau heures × tarif = montants
   Voit: aperçu PDF
   Clique: "Je confirme et signe"
   signature_professeur = now()

5. GÉNÉRATION PDF (Directeur génère)
   [Phase 1B] POST /timesheets/generate-pdf
   statut_validation = "confirmé" → "généré"
   PDF généré, téléchargement automatique
   pdf_generated_at = now()
   pdf_generated_by = directeur_id
```

---

## Tests Locaux

### Setup

```bash
cd backend

# Appliquer les migrations
php artisan migrate

# Seeder les tarifs
php artisan db:seed --class=ProfesseurTarifSeeder

# Lancer le serveur
php artisan serve
```

### Test Création Tarif

```bash
curl -X POST http://localhost:8000/api/professeurs/1/tarifs \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "tarif_horaire_eur": 7.50,
    "date_debut": "2026-09-28"
  }'
```

### Test Créer Timesheet

```bash
curl -X POST http://localhost:8000/api/timesheets \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "professeur_id": 1,
    "date_prestation": "2026-09-28",
    "nombre_heures": 2.0,
    "type_activite": "animation",
    "commentaire": "Cours test"
  }'
```

### Vérifier Montant Calculé

```bash
# À partir du timesheet créé, calculer: 2.0 h × 7.50 €/h = 15.00 €
# Frontend affichera ce montant après récupération du tarif effectif
```

---

## Statuts Codes HTTP

| Code | Signification |
|------|---------------|
| 200 | OK |
| 201 | Created |
| 204 | No Content |
| 400 | Bad Request (validation error) |
| 401 | Unauthorized |
| 403 | Forbidden (permissions) |
| 404 | Not Found |
| 422 | Unprocessable Entity |

---

## Notes

- Tous les endpoints sont paginés sauf mention contraire
- Les relations (professeur, cours, validateur) sont incluses par défaut
- Format date: ISO 8601 (YYYY-MM-DD)
- Format datetime: ISO 8601 (YYYY-MM-DDTHH:mm:ssZ)
- Tous les montants sont en euros (EUR)
