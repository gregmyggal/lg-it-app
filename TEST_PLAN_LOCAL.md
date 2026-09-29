# Plan de Test Local — Phase 1A + 1B + 2 Complet

**Date:** 2026-09-29  
**Environnement:** Local (localhost:8000 backend, localhost:5173 frontend)

---

## 🚀 Démarrage Serveurs

### Backend (Laravel)
```bash
cd backend
php artisan serve
# Écoute sur http://localhost:8000
```

### Frontend (Vite)
```bash
cd frontend
npm run dev
# Écoute sur http://localhost:5173
```

### Base de Données
```bash
cd backend
php artisan migrate --refresh --seed
# Réinitialise DB avec données test
```

---

## 🧪 Test Workflow Complet

### A. AUTHENTIFICATION

**Comptes test:**
- Admin: admin@example.com / password
- Directeur: directeur@example.com / password
- Professeur 1: prof1@example.com / password
- Professeur 2: prof2@example.com / password

**Actions:**
1. [ ] Aller sur http://localhost:5173/connexion
2. [ ] Connecter en tant que Professeur
3. [ ] Voir dashboard avec "Mes heures" visible
4. [ ] Déconnecter
5. [ ] Connecter en tant que Directeur
6. [ ] Voir section admin accessible

---

### B. PHASE 1A — GESTION TARIFS

#### Test 1: Voir Tarifs Existants
1. [ ] Admin → http://localhost:5173/admin/professeurs
2. [ ] Trouver "Prof1 Dupont"
3. [ ] Clique lien "💰 Gérer tarifs" (à ajouter)
4. [ ] Voir tableau: 7.50€/h depuis 01/01/2026 ✓ Actif

#### Test 2: Créer Nouveau Tarif
1. [ ] Clique "➕ Ajouter un nouveau tarif"
2. [ ] Remplir:
   ```
   Tarif: 8.50€/h
   Début: 2027-01-01
   Fin: [vide]
   ```
3. [ ] Clique "➕ Créer"
4. [ ] Voir message ✓ "Tarif créé avec succès"
5. [ ] Voir 2 lignes dans tableau (7.50€ et 8.50€)

#### Test 3: Modifier Tarif
1. [ ] Clique "✏️ Éditer" sur tarif 7.50€
2. [ ] Change en 7.75€
3. [ ] Clique "✓ Mettre à jour"
4. [ ] Voir ✓ "Tarif mis à jour avec succès"
5. [ ] Tableau montre 7.75€

#### Test 4: Arrêter Tarif
1. [ ] Clique "⏹️ Arrêter" sur tarif 7.75€
2. [ ] Modal: Date fin = 2026-12-31
3. [ ] Clique "✓ Confirmer l'arrêt"
4. [ ] Voir ✓ "Tarif arrêté avec succès"
5. [ ] Tarif 7.75€ → "Historique" (grisé)

#### Test 5: Supprimer Tarif
1. [ ] Clique "🗑️ Supprimer" sur tarif 8.50€
2. [ ] Confirmation dialog
3. [ ] Clique "OK"
4. [ ] Voir ✓ "Tarif supprimé avec succès"
5. [ ] Tarif 8.50€ n'est plus dans le tableau

---

### C. PHASE 1B — TIMESHEETS WORKFLOW

#### Test 6: Professeur Encode Heures
1. [ ] Connecter en tant que Prof1
2. [ ] Aller /timesheets
3. [ ] Voir section verte "✍️ Section Professeur"
4. [ ] Voir formulaire "Ajouter heure"
5. [ ] Remplir:
   ```
   Date: 2026-09-28
   Heures: 2.5h
   ```
6. [ ] Clique "➕ Ajouter"
7. [ ] Voir ✓ "Succès"
8. [ ] Voir entrée dans tableau: "Brouillon" (orange)

#### Test 7: Professeur Soumet Heures
1. [ ] Clique "✓ Soumettre" sur l'entrée
2. [ ] Voir ✓ "Timesheet soumis"
3. [ ] Statut change: "Soumis" (bleu)

#### Test 8: Ajouter Plusieurs Heures (Même Jour)
1. [ ] Ajouter 2ème heure même jour (2026-09-28): 3h
2. [ ] Ajouter 3ème heure même jour: 1.5h
3. [ ] Total jour: 2.5 + 3 + 1.5 = 7h
4. [ ] Montant jour: 7h × 7.50€ = 52.50€ (> 44.02€ MAX!)
5. [ ] Voir 3 entrées "Soumis" dans tableau

#### Test 9: Directeur Valide
1. [ ] Connecter en tant que Directeur
2. [ ] Aller /timesheets
3. [ ] Voir section bleue "👨‍💼 Section Directeur"
4. [ ] Voir sélecteur "Mois à traiter" = septembre 2026
5. [ ] Voir TimesheetMontantDisplay:
   - Total heures: 7h
   - Total montant: 52.50€
   - Jours encodés: 1
   - ⚠️ Dépassements: 1 jour en dépassement (+8.48€)
6. [ ] Voir tableau avec 3 entrées "Soumis"
7. [ ] Clique "✓ Valider" sur 1ère entrée
8. [ ] Voir ✓ "Timesheet validé"
9. [ ] Statut change: "Confirmé" (indigo)

#### Test 10: Directeur Applique Lissage
1. [ ] Voir "⚠️ 1 jour(s) en dépassement" en rouge
2. [ ] Voir bouton "Lissage" sur entrée restante en "Soumis"
3. [ ] Clique "🔄 Lissage"
4. [ ] Modal s'ouvre
5. [ ] Clique "Calculer la suggestion"
6. [ ] Voir:
   ```
   Suggestion: Déplacer 8.48€ au 27/09/2026
   Date cible: 27/09/2026
   ```
7. [ ] Clique "Appliquer"
8. [ ] Voir ✓ "Lissage appliqué avec succès"
9. [ ] Tableau: nouvel entrée apparaît (27/09 avec heures déplacées)

#### Test 11: Valider Reste des Heures
1. [ ] Clique "✓ Valider" sur 2ème et 3ème entrée
2. [ ] Voir tous les timesheets → "Confirmé"
3. [ ] Voir plus d'alerte dépassement
4. [ ] Total montant: toujours 52.50€ (juste redistribué)

---

### D. PHASE 1B — SIGNATURE

#### Test 12: Professeur Confirme & Signe
1. [ ] Connecter Prof1
2. [ ] Aller /timesheets
3. [ ] Voir bouton bleu "✍️ Confirmer et signer ce mois"
4. [ ] Clique
5. [ ] Voir page TimesheetConfirmationPage:
   - Titre: "✍️ Confirmation et Signature"
   - Voir TimesheetMontantDisplay (montants)
   - Voir breakdown par jour
   - Voir statut: "✓ Vous pouvez signer ce mois"
   - Voir bouton "Je confirme et signe"
6. [ ] Clique "Je confirme et signe"
7. [ ] Voir ✓ "Signature effectuée!"
8. [ ] Voir message: "Vos heures ont été signées"

#### Test 13: Vérifier Signature
1. [ ] Retour tableau
2. [ ] Voir tous les timesheets → "Confirmé" (indigo)
3. [ ] Lire la signature_professeur dans DB (timestamp noté)
   ```bash
   # Vérifier signature en DB:
   sqlite3 backend/database/database.sqlite
   SELECT id, statut_validation, signature_professeur FROM timesheets 
   WHERE professeur_id=1 AND DATE(date_prestation)='2026-09-28';
   ```

---

### E. PHASE 2 — GÉNÉRER PDF

#### Test 14: Directeur Génère PDF
1. [ ] Connecter Directeur
2. [ ] Aller /timesheets
3. [ ] Voir sélecteur "Mois à traiter" = septembre
4. [ ] Scroll bas → voir "📄 Génération du Défraiement"
5. [ ] Clique "⚙️ Générer le PDF"
6. [ ] Confirmation dialog:
   ```
   "Êtes-vous sûr de vouloir générer le PDF?
   Tous les timesheets signés seront marqués comme "généré".
   Cette action ne peut pas être annulée."
   ```
7. [ ] Clique "OK"
8. [ ] Voir ✓ "PDF généré avec succès"
9. [ ] Bouton change: "✓ PDF généré" + "⬇️ Télécharger le PDF"

#### Test 15: Télécharger PDF
1. [ ] Clique "⬇️ Télécharger le PDF"
2. [ ] Fichier `defraiement_Prof1_2026-09.pdf` téléchargé
3. [ ] Vérifier fichier existe:
   ```bash
   ls -lh backend/storage/pdfs/defraiement_1_2026_9.pdf
   ```
4. [ ] Ouvrir PDF dans lecteur
5. [ ] Vérifier contenu:
   - Titre: "📄 Défraiement des Heures Prestées"
   - Mois: "septembre 2026"
   - Infos prof: nom, email, IBAN
   - Tableau:
     ```
     Date | Heures | Tarif | Montant
     28/9 |   2.5h | 7.50€ |  18.75€
     27/9 |   4.5h | 7.50€ |  33.75€
     ```
   - Total: 7h × 7.50€ = 52.50€
   - Conformité: ✓ Pas dépassement après lissage

#### Test 16: Vérifier Statut Final
1. [ ] Aller /timesheets (directeur)
2. [ ] Voir tous les timesheets → "Généré" (vert)
3. [ ] Tableau: statut affiche "Généré"
4. [ ] Vérifier DB:
   ```bash
   SELECT id, statut_validation, pdf_generated_at FROM timesheets 
   WHERE professeur_id=1;
   # Tous doivent avoir: statut_validation='généré', pdf_generated_at NOT NULL
   ```

---

## 🎯 Checklist Finale

### Tarifs (Phase 1A)
- [ ] Lister tarifs
- [ ] Créer tarif
- [ ] Modifier tarif
- [ ] Arrêter tarif
- [ ] Supprimer tarif
- [ ] Tarif actif affiché (badge vert)

### Timesheets (Phase 1B)
- [ ] Professeur encode heures
- [ ] Professeur soumet heures
- [ ] Directeur voit montants calculés
- [ ] Directeur détecte dépassement (> 44.02€)
- [ ] Directeur applique lissage
- [ ] Professeur signe heures
- [ ] Signature enregistrée (timestamp)

### PDF (Phase 2)
- [ ] Directeur génère PDF
- [ ] PDF téléchargeable
- [ ] PDF contient bonnes infos
- [ ] Statuts → "Généré"
- [ ] Fichier stocké sur disque

### UI/UX
- [ ] Badges statuts corrects (brouillon/soumis/confirmé/généré)
- [ ] Sections prof/directeur visiblement séparées (bleu/vert)
- [ ] Messages success/error affichés
- [ ] Boutons actions visibles et fonctionnels
- [ ] Responsive: desktop OK
- [ ] Confirmations avant actions críticas

---

## 📊 Logs Attendus

### Backend (php artisan serve)
```
[timestamp] Local:   http://localhost:8000
[timestamp] Press Ctrl+C to stop the server
POST /api/timesheets 201
POST /timesheets/{id}/submit 200
POST /timesheets/{id}/validate 200
GET /timesheets/preview-pdf 200
POST /timesheets/sign-month 200
POST /timesheets/generate-pdf 200
GET /timesheets/download-pdf 200
```

### Frontend (npm run dev)
```
VITE v... dev server running at:
> Local:   http://localhost:5173
> Network: use --host to expose
ready in ...ms
✓ built in ...ms
```

---

## ⚡ Shortcuts Utiles

### Vérifier DB SQLite
```bash
cd backend
php artisan tinker
Timesheet::latest()->first()
ProfesseurTarif::latest()->first()
```

### Vérifier API directement
```bash
curl -X GET "http://localhost:8000/api/timesheets" \
  -H "Authorization: Bearer {token}"
```

### Vérifier PDF généré
```bash
ls -lh backend/storage/pdfs/
file backend/storage/pdfs/defraiement_*.pdf
pdftotext backend/storage/pdfs/defraiement_1_2026_9.pdf - | head -20
```

---

## 🐛 Troubleshooting

| Problème | Cause | Solution |
|----------|-------|----------|
| "Port 8000 in use" | Laravel server déjà en cours | `lsof -i :8000` puis `kill -9 PID` |
| "Port 5173 in use" | Vite server déjà en cours | `lsof -i :5173` puis `kill -9 PID` |
| "CORS error" | API et Frontend sur ports diff | Normal, CORS configuré backend |
| "Tarif pas chargé" | Cache Vite | Clear: `rm -rf frontend/dist` |
| "Timesheets vides" | Pas de seeder exécuté | `php artisan migrate:fresh --seed` |
| "PDF vide/erreur" | mPDF pas installé | `cd backend && composer install` |

---

**Status:** Ready for local testing 🚀

