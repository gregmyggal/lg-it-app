# API Phase 2 - Génération PDF de Défraiement

## Contexte

Phase 2 génère un PDF de défraiement professionnellement formaté une fois que:
1. ✅ Tous les timesheets du mois sont en statut `confirmé`
2. ✅ Tous les timesheets sont signés par le professeur (signature_professeur ≠ null)
3. ✅ Aucun dépassement journalier (44.02€ max)

Le PDF contient:
- Infos professeur (nom, email, IBAN)
- Tableau détail des heures par jour + montants
- Synthèse: total heures, total montant, nombre de jours
- Conformité et lissages appliqués

## Endpoints Créés

### 1. Générer le PDF

```
POST /api/timesheets/generate-pdf
Authorization: Bearer {token}
Permissions: directeur, admin

Request Body:
{
  "professeur_id": 5,
  "year": 2026,
  "month": 9
}

Response: 200 OK
{
  "success": true,
  "message": "PDF généré avec succès",
  "pdf_path": "pdfs/defraiement_5_2026_9.pdf",
  "filename": "defraiement_5_2026_9.pdf"
}

# Ou si erreur:
{
  "success": false,
  "error": "Certains timesheets ne sont pas signés"
}
```

**Validations:**
- Tous les timesheets doivent être en statut `confirmé`
- Tous les timesheets doivent avoir `signature_professeur` NOT NULL
- Pas de dépassements journaliers non-lissés

**Effets de bord:**
- Crée le fichier PDF sur disque (`storage/pdfs/`)
- Met à jour tous les timesheets: `statut_validation` = `généré`
- Met à jour: `pdf_generated_at` = now(), `pdf_generated_by` = user_id

---

### 2. Télécharger le PDF

```
GET /api/timesheets/download-pdf
Authorization: Bearer {token}
Permissions: directeur, admin

Query Parameters:
- professeur_id (int, required)
- year (int, required)
- month (int, required)

Response: 200 OK
{Binary PDF file}

# Ou si non trouvé:
{
  "error": "PDF non trouvé"
}
```

**Comportement:**
- Retourne le fichier PDF avec header `Content-Disposition: attachment`
- Télécharge le fichier généré précédemment
- Vérifie que le PDF existe sur disque

---

## Service Backend

### TimesheetPdfService

**Méthodes disponibles:**

```php
// Génère le PDF complet du mois
$result = $service->generateMonthlyPdf($professeurId, $year, $month, $userId);
// Retourne: ['success' => bool, 'message' => string, 'pdf_path' => string, 'filename' => string]

// Récupère le chemin du PDF généré
$path = $service->getPdfPath($professeurId, $year, $month);
// Retourne: string (chemin) ou null
```

**Template HTML:**
- Format A4, marges 20mm
- Font: DejaVu (compatible mPDF)
- Couleurs: bleu (#3b82f6), gris, vert (#10b981)
- Responsive layout table avec totaux

---

## Composant Frontend

### TimesheetPdfGenerator

```jsx
<TimesheetPdfGenerator
  professeurId={5}
  year={2026}
  month={9}
  professeurName="Dupont"
  onGenerateSuccess={() => reloadData()}
/>
```

**Props:**
- `professeurId` (int) — ID du professeur
- `year` (int) — Année du défraiement
- `month` (int) — Mois du défraiement
- `professeurName` (string) — Nom pour le téléchargement (ex: "Dupont")
- `onGenerateSuccess` (fn) — Callback après génération réussie

**Fonctionnalités:**
- Bouton "Générer le PDF" (disabled jusqu'à générération)
- Affiche state: "Génération…", puis "PDF généré" ✓
- Bouton "Télécharger le PDF" après génération
- Affiche error si génération échoue

**Workflow:**
1. Utilisateur clique "Générer le PDF"
2. Appel POST /timesheets/generate-pdf
3. Affiche "Génération…"
4. Si succès: affiche "PDF généré" + bouton Télécharger
5. Si erreur: affiche message d'erreur rouge
6. Clic Télécharger → GET /timesheets/download-pdf (blob)
7. Navigateur télécharge `defraiement_Dupont_2026-09.pdf`

---

## Workflow Complet

```
ÉTAT INITIAL (à la fin de Phase 1B)
├─ Professeur a signé tous les timesheets (signature_professeur = now())
├─ Tous les timesheets sont en statut "confirmé"
└─ Directeur voit page Timesheets avec:
   ├─ Montants affichés (TimesheetMontantDisplay)
   ├─ Tableau heures/jours
   └─ NOUVEAU: Bloc "Génération du Défraiement"

PHASE 2 - DIRECTEUR GÉNÈRE PDF
├─ 1. Clique "Générer le PDF"
├─ 2. Backend:
│  ├─ Valide que tous les timesheets sont confirmés + signés
│  ├─ Récupère tarifs effectifs pour chaque jour
│  ├─ Calcule montants (nombre_heures × tarif)
│  ├─ Génère HTML + PDF avec mPDF
│  ├─ Sauvegarde sur disque (storage/pdfs/defraiement_X_Y_M.pdf)
│  └─ Met à jour timesheets: statut_validation = "généré"
├─ 3. Frontend affiche:
│  ├─ "✓ PDF généré avec succès"
│  └─ Bouton "Télécharger le PDF"
└─ 4. Directeur clique "Télécharger"
   └─ Navigateur télécharge defraiement_Dupont_2026-09.pdf

ÉTAT FINAL
├─ PDF généré et stocké localement
├─ Tous les timesheets en statut "généré"
├─ pdf_generated_at = timestamp
└─ pdf_generated_by = directeur_id
```

---

## Limites et Notes

| Aspect | Détail |
|--------|--------|
| **Format PDF** | mPDF 8.2 (léger, pas de dépendances externes) |
| **Stockage** | Disque local (storage/pdfs/) — à archiver manuellement |
| **Signature** | Simple timestamp (pas de signature numérique DocuSign/SignRequest) |
| **Audit** | pdf_generated_by permet de tracer qui a généré |
| **Fréquence** | Un PDF par mois par professeur |
| **Archivage** | À implémenter en Phase 3 (S3, archivage DB) |

---

## Tests Locaux

### Tester la génération complète

```bash
# 1. Vérifier que les timesheets sont tous signés
curl -X GET "http://localhost:8000/api/timesheets/preview-pdf?professeur_id=1&year=2026&month=9" \
  -H "Authorization: Bearer {token}"

# 2. Générer le PDF
curl -X POST http://localhost:8000/api/timesheets/generate-pdf \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "professeur_id": 1,
    "year": 2026,
    "month": 9
  }'

# Réponse attendue:
# {
#   "success": true,
#   "message": "PDF généré avec succès",
#   "pdf_path": "pdfs/defraiement_1_2026_9.pdf",
#   "filename": "defraiement_1_2026_9.pdf"
# }

# 3. Vérifier que le statut est maintenant "généré"
curl -X GET "http://localhost:8000/api/timesheets" \
  -H "Authorization: Bearer {token}"

# 4. Télécharger le PDF
curl -X GET "http://localhost:8000/api/timesheets/download-pdf?professeur_id=1&year=2026&month=9" \
  -H "Authorization: Bearer {token}" \
  -o defraiement.pdf

# 5. Vérifier que le PDF est bien sur disque
ls -lh backend/storage/pdfs/defraiement_1_2026_9.pdf
```

---

## Prochaines Étapes (Phase 3 Optional)

1. **Archivage cloud** — Stocker les PDFs sur S3 au lieu du disque local
2. **Signature numérique** — Intégrer DocuSign ou SignRequest API
3. **Génération programmée** — Cron job pour générer automatiquement à fin de mois
4. **Email** — Envoyer le PDF au professeur après génération
5. **Historique** — Tracker les versions multiples d'un même PDF (révisions)

---

## Dépendances

- **Backend:** `mpdf/mpdf` ^8.2
- **Frontend:** aucune (utilise fetch natif)

Ajouter à composer.json:
```json
"mpdf/mpdf": "^8.2"
```

Puis: `composer install`

