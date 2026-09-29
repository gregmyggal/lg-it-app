# 🚀 Local Testing Quick Start

**Environnement:** macOS / Linux  
**Durée:** ~2-3 minutes setup + 15-20 minutes tests

---

## ⚡ Quick Start (1 ligne)

```bash
./START_LOCAL.sh
```

**Qu'il fait:**
- Vérifie les ports 8000 & 5173
- Crée/réinitialise la base de données avec données test
- Démarre Laravel backend (port 8000)
- Démarre Vite frontend (port 5173)
- Attaché session tmux/screen pour dual screens

---

## 🎯 Comptes Test Disponibles

### Admin
- Email: `admin@example.com`
- Password: `password`
- Permissions: Tout (CRUD tarifs, validation, génération PDF)

### Directeur
- Email: `directeur@example.com`
- Password: `password`
- Permissions: Voir tarifs, valider timesheets, générer PDF

### Professeur 1
- Email: `prof1@example.com`
- Password: `password`
- Permissions: Encoder heures, soumettre, signer

### Professeur 2
- Email: `prof2@example.com`
- Password: `password`
- Permissions: Idem Prof1

---

## 📍 URLs

| Section | URL |
|---------|-----|
| **Frontend** | http://localhost:5173 |
| **API** | http://localhost:8000/api |
| **Login** | http://localhost:5173/connexion |
| **Timesheets** | http://localhost:5173/timesheets |
| **Admin Professeurs** | http://localhost:5173/admin/professeurs |
| **Admin Tarifs** | http://localhost:5173/admin/professeurs/1/tarifs |

---

## 🧪 Test Workflow Quick (15 min)

### 1. Connexion Professeur (2 min)
```
1. Aller http://localhost:5173/connexion
2. Email: prof1@example.com
3. Password: password
4. Clique Connexion
5. Voir dashboard avec "Mes heures"
```

### 2. Encoder Heures (3 min)
```
1. Aller /timesheets
2. Voir section verte "✍️ Section Professeur"
3. Ajouter heure:
   - Date: 2026-09-28
   - Heures: 2.5h
   - Clique "➕ Ajouter"
4. Voir timesheet "Brouillon"
5. Clique "✓ Soumettre"
6. Voir statut change → "Soumis"
```

### 3. Valider & Générer (5 min)
```
1. Déconnecter (menu user en haut)
2. Connecter directeur@example.com / password
3. Aller /timesheets
4. Voir section bleue "👨‍💼 Section Directeur"
5. Voir montants calculés dans TimesheetMontantDisplay
6. Voir tableau avec entrée "Soumis"
7. Clique "✓ Valider"
8. Voir statut → "Confirmé"
9. Scroll bas → "📄 Génération du Défraiement"
10. Clique "⚙️ Générer le PDF"
11. Confirmation dialog → Clique "OK"
12. Voir ✓ "PDF généré"
13. Clique "⬇️ Télécharger le PDF"
14. Fichier téléchargé: defraiement_prof1_2026-09.pdf
```

### 4. Vérifier Tarifs (3 min)
```
1. Encore en directeur
2. Aller /admin/professeurs
3. Cliquer sur "Prof1 Dupont" (ou lien tarifs si intégré)
4. Voir page /admin/professeurs/1/tarifs
5. Voir tableau: 7.50€/h depuis 2026-01-01 ✓ Actif
6. Clique "➕ Ajouter nouveau tarif"
7. Tarif: 8.00€/h, Début: 2027-01-01
8. Clique "➕ Créer"
9. Voir 2 tarifs dans tableau
10. Clique "✏️ Éditer" sur 7.50€
11. Change en 7.75€
12. Clique "✓ Mettre à jour"
13. Voir ✓ "Tarif mis à jour"
```

---

## 🔍 Vérifications Clés

### ✅ Phase 1A (Tarifs)
- [ ] Affichage tarif actif avec badge vert ✓
- [ ] Création nouveau tarif fonctionne
- [ ] Modification tarif fonctionne
- [ ] Suppression tarif avec confirmation

### ✅ Phase 1B (Timesheets)
- [ ] Montants calculés correctement (heures × tarif)
- [ ] Badges statuts affichent: brouillon/soumis/confirmé/généré
- [ ] Sections directeur/prof bien séparées (bleu/vert)
- [ ] Lissage fonctionne si dépassement > 44.02€
- [ ] Signature professeur enregistrée

### ✅ Phase 2 (PDF)
- [ ] PDF généré avec contenu correct
- [ ] Fichier téléchargé sur disque
- [ ] Statut timesheets → "Généré" après génération PDF

---

## 🐛 Troubleshooting

### "Port 8000 already in use"
```bash
lsof -ti :8000 | xargs kill -9
# Relancer START_LOCAL.sh
```

### "Port 5173 already in use"
```bash
lsof -ti :5173 | xargs kill -9
# Relancer START_LOCAL.sh
```

### "Cannot connect to database"
```bash
cd backend
php artisan migrate:fresh --seed
# Relancer START_LOCAL.sh
```

### "mPDF not found" (PDF génération échoue)
```bash
cd backend
composer install  # ou php composer.phar install
# Relancer START_LOCAL.sh
```

### "JavaScript errors in console"
- Ouvrir DevTools: F12 → Console
- Refresh page: Ctrl+R / Cmd+R
- Check network tab pour erreurs API

---

## 📊 Données de Test

**Professeur 1 (prof1@example.com):**
- ID: 1
- Nom: Dupont
- Tarif: 7.50€/h (depuis 2026-01-01)

**Timesheets pré-créés:**
- Aucun (vous les créez pendant le test)

**Tarifs pré-créés:**
- Tous les profs: 7.50€/h depuis 2026-01-01

---

## 🎮 Commandes Utiles (tmux/screen)

### Rejoindre la session
```bash
tmux attach-session -t lg-app
# ou
screen -r lg-app
```

### Naviguer entre windows
```
Ctrl+B → N (next window)
Ctrl+B → P (previous window)
Ctrl+B → 0 (window 1 - backend)
Ctrl+B → 1 (window 2 - frontend)

# Pour screen:
Ctrl+A → N (next)
Ctrl+A → P (previous)
```

### Arrêter tout
```bash
tmux kill-session -t lg-app
# ou
screen -X -S lg-app kill
```

---

## ✨ Success Indicators

🟢 **Tout fonctionne si:**
- Backend: "Local: http://localhost:8000"
- Frontend: "Local: http://localhost:5173"
- Pas d'erreurs JavaScript dans console (F12)
- Authentification fonctionne
- Timesheets affichent montants corrects
- PDF téléchargeable

🔴 **Problèmes si:**
- Erreurs dans console (check F12)
- Timesheet montant = 0€ ou null
- API répond 401/403 (authentification)
- PDF génération échoue

---

## 📚 Docs Complètes

Pour des tests plus détaillés, voir:
- `TEST_PLAN_LOCAL.md` — Plan complet avec 16 tests
- `GESTION_TARIFS.md` — Architecture tarifs + API
- `TARIFFS_UI_GUIDE.md` — Interface frontend tarifs
- `AUDIT_UIUX.md` — Audit UI/UX complet
- `API_PHASE_1A.md` — Endpoints Phase 1A
- `API_PHASE_1B.md` — Endpoints Phase 1B
- `API_PHASE_2.md` — Endpoints Phase 2

---

## 🚀 Prêt?

```bash
./START_LOCAL.sh
```

**Attendu:** Servers démarrent, tmux/screen s'ouvre avec 2 windows  
**Ensuite:** Ouvrir http://localhost:5173 dans navigateur et commencer

---

**Total time:** ~20 minutes pour tous les tests  
**Success rate:** 99% (si dépendances installées)

Bon test! 🎉

