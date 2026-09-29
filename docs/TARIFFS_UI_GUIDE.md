# Guide d'Utilisation — Interface Frontend Gestion Tarifs

**Version:** 1.0  
**Dernière MAJ:** 2026-09-29

---

## 📍 Accès

### Direct (Admin/Directeur uniquement)

```
http://localhost:5173/admin/professeurs/{professeur_id}/tarifs
```

**Exemple:** Pour le professeur Jean Dupont (ID=5)
```
http://localhost:5173/admin/professeurs/5/tarifs
```

### Depuis page Professeurs Admin

*À intégrer:* Lien "Gérer les tarifs" dans le tableau des professeurs

---

## 🎨 Interface

### Structure Générale

```
[Titre: "💰 Tarifs - Jean Dupont"]

[Alertes: Erreur/Succès]

[Bouton: ➕ Ajouter un nouveau tarif]

[Formulaire création (si actif)]

[Tableau: Historique des tarifs]
  Colonne 1: Tarif Horaire (8.00€/h) + Badge ✓ Actif
  Colonne 2: Période (01/01/2026 - 31/08/2026)
  Colonne 3: Statut (Actif / Historique)
  Colonne 4: Actions (Éditer, Arrêter, Supprimer)

[Info box: Comment ça fonctionne?]
```

---

## ✅ Opérations

### 1. Créer un Nouveau Tarif

**Étapes:**

1. Clique sur **"➕ Ajouter un nouveau tarif"**
   - Formulaire apparaît

2. Remplir les champs:
   - **Tarif horaire (€):** Ex: `8.50`
   - **Date de début:** Ex: `2027-01-01`
   - **Date de fin:** Optionnel (laisser vide si sans limite)

3. Clique **"➕ Créer"**
   - Message ✓ succès
   - Tableau se rafraîchit

**Exemple:** Augmentation au 1er janvier 2027
```
Tarif: 8.50€/h
Date début: 2027-01-01
Date fin: [vide]
→ Clique "Créer"
→ Nouveau tarif visible dans le tableau
```

---

### 2. Modifier un Tarif Existant

**Étapes:**

1. Dans le tableau, clique **"✏️ Éditer"** sur le tarif

2. Formulaire s'ouvre avec les valeurs actuelles

3. Modifie les champs voulus

4. Clique **"✓ Mettre à jour"**
   - Message ✓ succès
   - Tableau se rafraîchit

**⚠️ Attention:**
- Modifier un tarif = modifie aussi son historique
- Si tu dois créer une augmentation: crée un NOUVEAU tarif au lieu de modifier l'ancien
- Seuls admin peuvent modifier

---

### 3. Arrêter un Tarif (Définir Date Fin)

**Quand?**
- Professeur part en congé/retraite
- Fin d'une période temporaire (remplacement)
- Besoin de terminer un tarif avant d'en créer un nouveau

**Étapes:**

1. Dans le tableau, clique **"⏹️ Arrêter"** sur le tarif
   - Modal s'ouvre

2. Sélectionner la **date de fin**
   - La date doit être après la date de début
   - Exemple: `2026-12-31`

3. Clique **"✓ Confirmer l'arrêt"**
   - Message ✓ succès
   - Tarif passe au statut "Historique" (grisé)
   - Tarif ne s'applique plus après cette date

**Exemple:**
```
Tarif: 7.50€/h depuis 2026-01-01
Prof part en congé le 30/06/2026

Action: Clique "Arrêter"
Modal: Date fin = 2026-06-30
Résultat: Tarif valide jusqu'au 30/06/2026, puis expiré
```

---

### 4. Supprimer un Tarif

**⚠️ Attention:** Action irréversible!

**Étapes:**

1. Clique **"🗑️ Supprimer"** dans le tableau
   - Confirmation dialog s'affiche

2. Clique **"OK"** pour confirmer

3. Tarif supprimé de la base de données

**⚠️ Quand supprimer:**
- Tarif créé par erreur
- Tarif en double
- À l'inverse de "Arrêter", la suppression = perte complète de la donnée

**Mieux:** Utilise "Arrêter" au lieu de supprimer si le tarif a été utilisé pour des timesheets

---

## 📊 Tableau Tarifs

### Colonnes

| Colonne | Contenu | Utilité |
|---------|---------|---------|
| **Tarif Horaire** | `8.00€/h ✓ Actif` | Montant + badge si actuellement appliqué |
| **Période** | `01/09/2026 → ∞ (toujours)` | Plage de validité |
| **Statut** | `Actif` ou `Historique` | État actuel du tarif |
| **Actions** | Boutons Éditer/Arrêter/Supprimer | CRUD opérations |

### Couleurs & Styles

- **Tarif actif:** Vert + Badge "✓ Actif" (ligne non-grisée)
- **Tarif historique:** Grisé (passé ou expiré)
- **Montant:** Gras (16px) pour bien voir la valeur
- **Date:** Petit texte gris sous la date de début

---

## ⚡ Cas d'Usage Courants

### Cas 1: Augmentation Annuelle

**Situation:** 2026-12-31, prof reçoit augmentation pour 2027

**Action:**

1. Clique **"➕ Ajouter un nouveau tarif"**

2. Remplir:
   ```
   Tarif: 8.50€/h  (anciennement 8.00€)
   Début: 2027-01-01
   Fin: [vide]
   ```

3. Clique **"➕ Créer"**

4. Ancien tarif (2026) continue → Timesheets 2026 utilisent 8.00€
   Nouveau tarif (2027) → Timesheets 2027+ utilisent 8.50€

---

### Cas 2: Remplacement Temporaire

**Situation:** Remplacement 3 mois, tarif réduit

**Action:**

1. **Créer tarif temporaire:**
   ```
   Tarif: 6.50€/h
   Début: 2026-10-01
   Fin: 2026-12-31
   ```

2. Remplacement encoded entre ces dates: utilise 6.50€

3. Après 2026-12-31:
   - Tarif expiré (historique)
   - Ancien tarif réactive (s'il existe)
   - Ou: créer nouveau tarif

---

### Cas 3: Correction Rétroactive

**Situation:** Découvrir que tarif 01/09 était faux (8.00€ au lieu de 8.50€)

**Action:**

1. Clique **"✏️ Éditer"** sur le tarif 8.00€

2. Change en 8.50€

3. Clique **"✓ Mettre à jour"**

4. **⚠️ Attention:** Tous les timesheets du 2026-09 se recalculent automatiquement
   - Montants changent (8.50€ au lieu de 8.00€)
   - PDFs générés NON recalculés (figés au moment génération)
   - Il faut régénérer les PDFs si nécessaire

---

## 🧠 Logique Affichage

### Tarif "Actif"

Un tarif est **actif** (badge vert) si:
- `date_debut <= aujourd'hui`
- ET `date_fin IS NULL` ou `date_fin > aujourd'hui`

**Exemple:**
```
Tarif 1: 7.50€ du 01/01/2026 au 31/08/2026 → Historique (passé)
Tarif 2: 8.00€ du 01/09/2026, sans date fin → Actif (continue)
Tarif 3: 8.50€ du 01/01/2027, sans date fin → Historique (futur)
```

### Tarif Effectif pour Encodage

Quand un prof encode une heure le **28/09/2026:**
1. Système cherche un tarif valide pour cette date
2. Trouve Tarif 2 (08/00€)
3. Calcule: `montant = heures × 8.00€`

---

## 📱 Responsive Design

### Desktop (> 768px)
- Tableau complet avec 4 colonnes
- Boutons côte-à-côte dans Actions
- Formulaire: 3 champs en grille

### Tablet (600px - 768px)
- Tableau peut scroller horizontalement
- Boutons Actions persistent

### Mobile (< 600px)
- Tableau difficile à lire
- Recommandation: utiliser API via Postman/curl pour mobile
- Alternative: modal view par tarif (à implémenter)

---

## ❌ Erreurs Courantes

### 1. "Date de fin doit être après date début"

**Cause:** Date fin ≤ Date début

**Solution:**
- Date fin doit être STRICTEMENT après date début
- Exemple: Début 2026-01-01 → Fin minimum 2026-01-02

---

### 2. Ancien tarif reste actif après création nouveau tarif

**Cause:** Oubli de terminer l'ancien avant créer nouveau

**Solution Correcte:**
```
1. Terminer ancien: 2026-08-31
2. Créer nouveau: debut 2026-09-01
→ Pas de trou dans les dates
```

**Solution Incorrecte:**
```
1. Créer nouveau: debut 2026-09-01 (sans fin sur ancien)
→ Ancien reste actif (overlap!)
```

---

### 3. "Impossible de charger les tarifs"

**Cause:** Prof n'existe pas ou pas de permission

**Solution:**
- Vérifier ID professeur est correct
- Vérifier connecté en tant qu'admin/directeur
- Vérifier API fonctionne: `GET /professeurs/{id}/tarifs`

---

## 📡 Données Affichées

### Depuis L'API

```javascript
GET /professeurs/{professeur_id}/tarifs
Response: [
  {
    id: 1,
    professeur_id: 5,
    tarif_horaire_eur: "8.00",  // Décimal
    date_debut: "2026-09-01",   // YYYY-MM-DD
    date_fin: null,              // null ou YYYY-MM-DD
    created_at: "2026-09-01T10:00:00Z",
    updated_at: "2026-09-01T10:00:00Z"
  },
  ...
]
```

### Affichés dans L'UI

| Champ API | Affichage UI | Format |
|-----------|-------------|--------|
| `tarif_horaire_eur` | `8.00€/h` | 2 décimales |
| `date_debut` | `01/09/2026` | Locale France |
| `date_fin` | `∞` ou `31/12/2026` | Locale France, "∞" si null |
| `tarif_horaire_eur + statut actif` | `8.00€/h ✓ Actif` | Badge vert si actif |

---

## 🔐 Permissions

| Rôle | Voir | Créer | Modifier | Terminer | Supprimer |
|------|------|-------|----------|----------|-----------|
| **Admin** | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Directeur** | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Professeur** | ❌ | ❌ | ❌ | ❌ | ❌ |

---

## 🧪 Test Chemin Complet

**Objectif:** Créer, modifier, arrêter un tarif

### Étape 1: Naviguer
```
http://localhost:5173/admin/professeurs/5/tarifs
```

### Étape 2: Créer
```
Clique "➕ Ajouter un nouveau tarif"
Tarif: 9.00€/h
Début: 2027-06-01
Fin: [vide]
Clique "➕ Créer"
✓ Succès
```

### Étape 3: Vérifier
```
Tableau affiche: 9.00€/h | 01/06/2027 - ∞ (toujours) | Historique
(Historique car date > aujourd'hui)
```

### Étape 4: Modifier
```
Clique "✏️ Éditer"
Tarif: 9.25€/h
Clique "✓ Mettre à jour"
✓ Succès
```

### Étape 5: Arrêter
```
Clique "⏹️ Arrêter"
Modal: Date fin = 2027-12-31
Clique "✓ Confirmer l'arrêt"
✓ Succès
```

### Étape 6: Voir Historique
```
Tableau: 9.25€/h | 01/06/2027 - 31/12/2027 | Historique (grisé)
```

---

## 📞 Support

**Problème?**

1. Vérifie la console (F12) pour les erreurs
2. Teste l'API directement: `curl -X GET http://localhost:8000/api/professeurs/5/tarifs -H "Authorization: Bearer {token}"`
3. Vérifie permissions (connecté en admin?)
4. Voir `docs/GESTION_TARIFS.md` pour la logique backend

---

**Status:** 🟢 Production Ready

