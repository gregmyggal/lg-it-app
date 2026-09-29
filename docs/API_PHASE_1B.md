# API Phase 1B - Lissage, Signature, Aperçu PDF

## Endpoints Créés

### Lissage (Directeur)

#### 1. Proposer un lissage pour un timesheet en dépassement
```
GET /api/timesheets/{timesheet_id}/propose-lissage?year=2026&month=9
Authorization: Bearer {token}
Permissions: directeur, admin

Response: 200 OK
{
  "success": true,
  "depassement": {
    "date": "2026-09-28",
    "montant_total": 50.00,
    "depassement": 5.98,
    "max_autorise": 44.02
  },
  "timesheets_du_jour": [
    {
      "id": 1,
      "date_prestation": "2026-09-28",
      "nombre_heures": 2.0,
      "type_activite": "animation",
      "montant_brut": 15.00,
      "tarif_horaire": 7.50
    },
    ...
  ],
  "suggestion": {
    "date_cible": "2026-09-27",
    "quantite_a_deplacer_eur": 5.98,
    "note": "Déplacer 5,98€ au 27/09/2026"
  }
}
```

#### 2. Appliquer un lissage (déplacer les heures)
```
POST /api/timesheets/{timesheet_id}/apply-lissage
Authorization: Bearer {token}
Permissions: directeur, admin

Request Body:
{
  "date_to": "2026-09-27",
  "montant_to_move": 5.98
}

Response: 200 OK
{
  "success": true,
  "message": "Lissage effectué",
  "timesheet": { ... }
}
```

---

### Signature (Professeur)

#### 1. Signer un timesheet individuel
```
POST /api/timesheets/{timesheet_id}/sign
Authorization: Bearer {token}
Permissions: professeur propriétaire seulement

Response: 200 OK
{
  "success": true,
  "message": "Timesheet signé",
  "timesheet": {
    "id": 1,
    "signature_professeur": "2026-09-28T14:30:00Z",
    "statut_validation": "confirmé",
    ...
  }
}
```

#### 2. Vérifier si un mois peut être signé
```
GET /api/timesheets/can-sign-month?professeur_id=5&year=2026&month=9
Authorization: Bearer {token}
Permissions: professeur, directeur, admin

Response: 200 OK
{
  "can_sign": true,
  "errors": [],
  "warnings": []
}

# Ou si impossible:
{
  "can_sign": false,
  "errors": [
    "2 entrée(s) non confirmées",
    "Dépassements détectés"
  ],
  "warnings": []
}
```

#### 3. Signer tous les timesheets d'un mois
```
POST /api/timesheets/sign-month
Authorization: Bearer {token}

Request Body:
{
  "professeur_id": 5,
  "year": 2026,
  "month": 9
}

Response: 200 OK
{
  "success": true,
  "message": "Mois signé avec succès"
}
```

---

### Aperçu PDF (Directeur + Professeur)

#### 1. Récupérer les données pour l'aperçu PDF
```
GET /api/timesheets/preview-pdf?professeur_id=5&year=2026&month=9
Authorization: Bearer {token}
Permissions: directeur, admin

Response: 200 OK
{
  "professeur": {
    "nom": "Dupont",
    "prenom": "Jean",
    "email": "jean.dupont@example.com",
    "compte_bancaire": "BE** **** **** ****"
  },
  "periode": {
    "annee": 2026,
    "mois": 9,
    "mois_label": "septembre 2026",
    "date_debut": "2026-09-01",
    "date_fin": "2026-09-30"
  },
  "heures_par_jour": {
    "2026-09-28": {
      "date": "2026-09-28",
      "montant_total": 15.00,
      "activites": [
        {
          "type": "animation",
          "heures": 2.0,
          "tarif": 7.50,
          "montant": 15.00
        }
      ]
    },
    ...
  },
  "synthese": {
    "total_heures": 30.0,
    "total_montant": 225.00,
    "nombre_jours_encodes": 12,
    "depassements": []
  },
  "conformite": {
    "max_par_jour": 44.02,
    "conforme": true,
    "depassements_detectes": 0
  }
}
```

---

## Services Backend

### TimesheetLissingService

**Méthodes disponibles:**

```php
// Calcule les montants pour tous les timesheets d'un mois
$monthData = $service->calculateMonthlyMontants($professeurId, $year, $month);
// Retourne: ['timesheets' => [...], 'depassements' => [...], 'total_montant' => ...]

// Propose un lissage intelligent
$proposal = $service->proposeLissage($professeurId, $dateDepassement, $year, $month);
// Retourne: ['success' => bool, 'depassement' => [...], 'suggestion' => [...]]

// Effectue un lissage
$success = $service->executeLissage($timesheetId, $dateFrom, $dateTo, $montantADeplacer);

// Valide le total annuel
$annual = $service->validateAnnualLimit($professeurId, $year);
// Retourne: ['total_annuel' => float, 'max_autorise' => float, 'conforme' => bool]
```

### TimesheetSignatureService

**Méthodes disponibles:**

```php
// Signe un timesheet
$success = $service->signTimesheet($timesheet, $user);

// Prépare les données PDF
$pdfData = $service->preparePdfData($professeurId, $year, $month);

// Vérifie si un mois peut être signé
$result = $service->canSignMonth($professeurId, $year, $month);
// Retourne: ['can_sign' => bool, 'errors' => [...]]

// Signe un mois entier
$success = $service->signMonth($professeurId, $year, $month, $user);
```

---

## Composants Frontend

### TimesheetMontantDisplay
Affiche un résumé des montants du mois avec alertes dépassement.

```jsx
<TimesheetMontantDisplay
  timesheets={[]}
  professeurId={5}
  year={2026}
  month={9}
/>
```

### TimesheetLissingModal
Modal pour appliquer un lissage (directeur).

```jsx
<TimesheetLissingModal
  timesheetId={1}
  datePrestation="2026-09-28"
  montantActuel={50.00}
  isOpen={showModal}
  onClose={() => setShowModal(false)}
  onSuccess={() => loadData()}
  year={2026}
  month={9}
/>
```

### TimesheetConfirmationPage
Page de confirmation et signature (professeur).

```jsx
<TimesheetConfirmationPage
  professeurId={5}
  year={2026}
  month={9}
/>
```

---

## Logique Métier

### Workflow Complet

```
1. INTRODUCTION (Professeur encode)
   statut_validation = "brouillon"

2. SOUMISSION (Professeur soumet)
   statut_validation = "brouillon" → "soumis"

3. VALIDATION (Directeur valide + lisse si nécessaire)
   statut_validation = "soumis" → "confirmé"
   Si dépassement > 44,02€/jour:
     - Directeur clique "Lissage"
     - Système propose jour cible
     - Directeur approuve ou ajuste
     - Heures déplacées, flag lissage_applique = true

4. CONFIRMATION & SIGNATURE (Professeur)
   Professeur voit:
     - Tableau heures × tarif = montants
     - Aperçu PDF
     - Alertes conformité
   Clique: "Je confirme et signe"
     - signature_professeur = now()
     - statut_validation reste "confirmé" (prêt pour PDF)

5. GÉNÉRATION PDF (Directeur)
   [Phase 2]
   POST /timesheets/generate-pdf
     - Collecte heures confirmées + signées
     - Génère PDF au format exact
     - statut_validation = "généré"
```

### Validation Conformité

**Limites:**
- Max 44,02€/jour (sans lissage = rejet)
- Max 1.760,83€/an

**Vérification:**
- Avant signature: pas de dépassement journalier
- Avant PDF: vérification annuelle

**Lissage automatique:**
1. Détecte les jours > 44,02€
2. Propose un jour proche avec capacité
3. Directeur approuve/ajuste
4. Heures déplacées (création nouvelle entrée + réduction originale)
5. Flag `lissage_applique = true` pour audit

---

## Tests Locaux

### Tester le Lissage

```bash
# 1. Créer des timesheets > 44,02€ un même jour
curl -X POST http://localhost:8000/api/timesheets \
  -H "Authorization: Bearer {token}" \
  -d '{
    "professeur_id": 1,
    "date_prestation": "2026-09-28",
    "nombre_heures": 6.0,
    "type_activite": "animation"
  }'

# 2. Récupérer la proposition
curl -X GET "http://localhost:8000/api/timesheets/1/propose-lissage?year=2026&month=9" \
  -H "Authorization: Bearer {token}"

# 3. Appliquer le lissage
curl -X POST http://localhost:8000/api/timesheets/1/apply-lissage \
  -H "Authorization: Bearer {token}" \
  -d '{
    "date_to": "2026-09-27",
    "montant_to_move": 10.50
  }'

# 4. Vérifier les montants recalculés
curl -X GET "http://localhost:8000/api/timesheets/preview-pdf?professeur_id=1&year=2026&month=9" \
  -H "Authorization: Bearer {token}"
```

### Tester la Signature

```bash
# 1. Vérifier si un mois peut être signé
curl -X GET "http://localhost:8000/api/timesheets/can-sign-month?professeur_id=1&year=2026&month=9" \
  -H "Authorization: Bearer {token}"

# 2. Signer le mois
curl -X POST http://localhost:8000/api/timesheets/sign-month \
  -H "Authorization: Bearer {token}" \
  -d '{
    "professeur_id": 1,
    "year": 2026,
    "month": 9
  }'

# 3. Récupérer l'aperçu PDF
curl -X GET "http://localhost:8000/api/timesheets/preview-pdf?professeur_id=1&year=2026&month=9" \
  -H "Authorization: Bearer {token}"
```

---

## Remarques

- Lissage crée une NOUVELLE entrée (pas de modification de l'existante)
- Signature ne met pas à jour `statut_validation` (reste "confirmé")
- `lissage_applique` est un flag (pas de tracking des mouvements individuels)
- Signature est atomique (tout ou rien pour un mois)
