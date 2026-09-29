# Guide de Gestion des Tarifs Horaires Professeurs

**Version:** Phase 1A+
**Dernière MAJ:** 2026-09-29

---

## 📋 Vue d'ensemble

Les tarifs horaires sont utilisés pour calculer les montants (€) des timesheets:
```
montant = nombre_heures × tarif_horaire_eur
```

Chaque professeur peut avoir **plusieurs tarifs** avec **des dates de validité** différentes (historique, augmentations annuelles, etc.).

---

## 🏗️ Architecture

### Base de Données

**Table:** `professeur_tarifs`

```sql
CREATE TABLE professeur_tarifs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    professeur_id BIGINT NOT NULL,
    tarif_horaire_eur DECIMAL(8, 2) NOT NULL,  -- Ex: 7.50
    date_debut DATE NOT NULL,                   -- Quand le tarif commence
    date_fin DATE NULL,                         -- Quand le tarif finit (NULL = toujours actif)
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (professeur_id) REFERENCES professeurs(id) ON DELETE CASCADE,
    INDEX idx_prof_dates (professeur_id, date_debut, date_fin)
);
```

### Model Laravel

```php
// app/Models/ProfesseurTarif.php
class ProfesseurTarif extends Model {
    // Attributs
    $fillable = ['professeur_id', 'tarif_horaire_eur', 'date_debut', 'date_fin'];
    
    // Récupère le tarif valide pour une date donnée
    static function effectiveAt($professeurId, DateTime $date): ?self
}
```

---

## 📡 API Endpoints

### 1. Lister tous les tarifs d'un professeur

```http
GET /api/professeurs/{professeur_id}/tarifs
Authorization: Bearer {token}
Permissions: admin, directeur
```

**Response:** 200 OK
```json
[
  {
    "id": 1,
    "professeur_id": 5,
    "tarif_horaire_eur": "7.50",
    "date_debut": "2026-01-01",
    "date_fin": "2026-08-31",
    "created_at": "2026-09-01T10:00:00Z",
    "updated_at": "2026-09-01T10:00:00Z"
  },
  {
    "id": 2,
    "professeur_id": 5,
    "tarif_horaire_eur": "8.00",
    "date_debut": "2026-09-01",
    "date_fin": null,
    "created_at": "2026-09-01T10:30:00Z",
    "updated_at": "2026-09-01T10:30:00Z"
  }
]
```

---

### 2. Créer un nouveau tarif

```http
POST /api/professeurs/{professeur_id}/tarifs
Authorization: Bearer {token}
Content-Type: application/json
Permissions: admin

Body:
{
  "tarif_horaire_eur": 8.50,
  "date_debut": "2027-01-01",
  "date_fin": null
}
```

**Response:** 201 Created
```json
{
  "id": 3,
  "professeur_id": 5,
  "tarif_horaire_eur": "8.50",
  "date_debut": "2027-01-01",
  "date_fin": null,
  "created_at": "2026-09-29T14:00:00Z",
  "updated_at": "2026-09-29T14:00:00Z"
}
```

---

### 3. Mettre à jour un tarif

```http
PUT /api/professeurs/{professeur_id}/tarifs/{tarif_id}
Authorization: Bearer {token}
Content-Type: application/json
Permissions: admin

Body:
{
  "tarif_horaire_eur": 8.75
  // Autres champs optionnels: date_debut, date_fin
}
```

**Response:** 200 OK
```json
{ id: 3, tarif_horaire_eur: "8.75", ... }
```

---

### 4. Terminer un tarif (définir date_fin)

```http
POST /api/professeurs/{professeur_id}/tarifs/{tarif_id}/terminate
Authorization: Bearer {token}
Content-Type: application/json
Permissions: admin

Body:
{
  "date_fin": "2026-12-31"
}
```

**Response:** 200 OK
```json
{
  "id": 2,
  "tarif_horaire_eur": "8.00",
  "date_debut": "2026-09-01",
  "date_fin": "2026-12-31",
  "updated_at": "2026-09-29T14:00:00Z"
}
```

---

### 5. Supprimer un tarif

```http
DELETE /api/professeurs/{professeur_id}/tarifs/{tarif_id}
Authorization: Bearer {token}
Permissions: admin
```

**Response:** 204 No Content

---

### 6. Récupérer le tarif effectif pour une date

```http
GET /api/professeurs/{professeur_id}/tarif-effectif?date=2026-09-28
Authorization: Bearer {token}
Permissions: admin, directeur
```

**Response:** 200 OK
```json
{
  "id": 2,
  "professeur_id": 5,
  "tarif_horaire_eur": "8.00",
  "date_debut": "2026-09-01",
  "date_fin": null
}
```

**Ou si pas de tarif trouvé:** 404 Not Found
```json
{ "error": "No effective tariff found" }
```

---

## 💡 Cas d'Usage

### Cas 1: Augmentation de Salaire (Nouveau Tarif)

**Situation:** Professeur reçoit augmentation à partir du 1er janvier 2027

**Action:**
1. Récupérer tarif courant (2026): 7.50€
2. Créer nouveau tarif:
   ```
   POST /professeurs/5/tarifs
   {
     "tarif_horaire_eur": 8.00,
     "date_debut": "2027-01-01",
     "date_fin": null
   }
   ```
3. Anciens timesheets (avant 2027-01-01) utilisent 7.50€
4. Nouveaux timesheets (après 2027-01-01) utilisent 8.00€

---

### Cas 2: Tarif Temporaire (Remplacement)

**Situation:** Professeur remplaçant pendant 3 mois (tarif réduit)

**Action:**
1. Créer tarif temporaire:
   ```
   POST /professeurs/8/tarifs
   {
     "tarif_horaire_eur": 6.50,
     "date_debut": "2026-10-01",
     "date_fin": "2026-12-31"
   }
   ```
2. Timesheets entre 2026-10-01 et 2026-12-31 utilisent 6.50€
3. Après 2026-12-31: plus aucun tarif = erreur si on encode des heures
4. Le tarif d'avant (avant 2026-10-01) redevient effectif

---

### Cas 3: Arrêt d'un Tarif

**Situation:** Professeur part en congé, arrête les heures à partir du 30/06/2026

**Action:**
1. Terminer le tarif actuel:
   ```
   POST /professeurs/5/tarifs/2/terminate
   {
     "date_fin": "2026-06-30"
   }
   ```
2. Timesheets jusqu'au 2026-06-30 utilisent ce tarif
3. Après le 2026-06-30: pas de tarif valide (erreur si encodage)
4. Quand le professeur revient: créer nouveau tarif

---

### Cas 4: Retroactif (Correction)

**Situation:** Découvrir que tarif 2026-09-01 ne devrait pas être 8.00€ mais 8.50€

**Action:**
1. Mettre à jour tarif existant:
   ```
   PUT /professeurs/5/tarifs/2
   {
     "tarif_horaire_eur": 8.50
   }
   ```
2. ⚠️ Montants antérieurs devront être RECALCULÉS
3. Les timesheets déjà encodés ne se recalculent PAS automatiquement
4. Necessário: faire un script de correction ou recalculer manuellement

---

## 🔍 Logique "Tarif Effectif"

Quand on encode une heure prestée le **28 septembre 2026**, le système:

1. Cherche un tarif valide pour **2026-09-28**
2. Conditions:
   - `date_debut <= 2026-09-28`
   - `date_fin IS NULL OR date_fin > 2026-09-28`
3. S'il y en a plusieurs: prend le **plus récent** (dernier `date_debut`)
4. Utilise ce tarif pour calculer le montant

### Exemple:

**Tarifs pour prof_id=5:**
```
Tarif 1: 7.50€, debut=2026-01-01, fin=2026-08-31
Tarif 2: 8.00€, debut=2026-09-01, fin=NULL
```

**Encodage de heures:**
- 2026-08-31: utilise Tarif 1 (7.50€)
- 2026-09-01: utilise Tarif 2 (8.00€)
- 2026-09-28: utilise Tarif 2 (8.00€)
- 2026-12-31: utilise Tarif 2 (8.00€)
- 2027-02-01: Tarif 2 toujours (pas de fin)

---

## 🛡️ Permissions & Sécurité

| Action | Admin | Directeur | Professeur |
|--------|-------|-----------|-----------|
| Voir tarifs | ✅ | ✅ | ❌ |
| Créer tarif | ✅ | ❌ | ❌ |
| Modifier tarif | ✅ | ❌ | ❌ |
| Supprimer tarif | ✅ | ❌ | ❌ |
| Terminer tarif | ✅ | ❌ | ❌ |
| Voir tarif effectif | ✅ | ✅ | ❌ |

**Policy:** `app/Policies/ProfesseurTarifPolicy.php`
- Seul **admin** peut créer/modifier/supprimer
- Directeur peut **visu** uniquement
- Professeur: **aucun accès** (tarif = privé admin)

---

## 🧪 Tests avec curl

### Test 1: Créer un tarif initial

```bash
curl -X POST "http://localhost:8000/api/professeurs/5/tarifs" \
  -H "Authorization: Bearer {token_admin}" \
  -H "Content-Type: application/json" \
  -d '{
    "tarif_horaire_eur": 7.50,
    "date_debut": "2026-01-01",
    "date_fin": null
  }'
```

### Test 2: Créer un tarif d'augmentation (2027)

```bash
curl -X POST "http://localhost:8000/api/professeurs/5/tarifs" \
  -H "Authorization: Bearer {token_admin}" \
  -H "Content-Type: application/json" \
  -d '{
    "tarif_horaire_eur": 8.50,
    "date_debut": "2027-01-01",
    "date_fin": null
  }'
```

### Test 3: Lister tous les tarifs

```bash
curl -X GET "http://localhost:8000/api/professeurs/5/tarifs" \
  -H "Authorization: Bearer {token_admin}"
```

### Test 4: Récupérer tarif effectif pour date

```bash
curl -X GET "http://localhost:8000/api/professeurs/5/tarif-effectif?date=2026-09-28" \
  -H "Authorization: Bearer {token_admin}"

# Doit retourner le tarif 8.00€ (debut=2026-09-01, fin=NULL)
```

### Test 5: Terminer un tarif

```bash
curl -X POST "http://localhost:8000/api/professeurs/5/tarifs/2/terminate" \
  -H "Authorization: Bearer {token_admin}" \
  -H "Content-Type: application/json" \
  -d '{"date_fin": "2026-12-31"}'
```

---

## 📊 Intégration avec Timesheets

### Quand les tarifs sont utilisés:

1. **Création timesheet:** Ne touche pas au tarif
   ```php
   POST /timesheets
   { "professeur_id": 5, "date_prestation": "2026-09-28", "nombre_heures": 2.5 }
   // Créé timesheet, montant = NULL (calculé après)
   ```

2. **Affichage montants:** Récupère tarif effectif
   ```php
   GET /timesheets/preview-pdf?professeur_id=5&year=2026&month=9
   // Calcule montant = 2.5h × 8.00€ = 20.00€
   ```

3. **Validation conformité:** Utilise montants
   ```php
   // Si montant > 44.02€ par jour → détecte dépassement
   // Lissage peut redistribuer basé sur tarif
   ```

4. **Génération PDF:** Inclut tarif appliqué par jour
   ```
   Date | Heures | Tarif | Montant
   28/9 |   2.5h | 8.00€ |  20.00€
   ```

---

## ⚠️ Pièges Courants

### 1. Oublier `date_fin` lors d'une augmentation

❌ **Mauvais:**
```json
{
  "tarif_horaire_eur": 8.00,
  "date_debut": "2026-09-01"
  // date_fin manquant (NULL implicite)
}
```
→ Ancien tarif 7.50€ reste actif car pas de `date_fin`

✅ **Correct:**
```json
{
  "tarif_horaire_eur": 8.00,
  "date_debut": "2026-09-01",
  "date_fin": null  // Explicitement NULL = actif pour toujours
}
// AVANT: mettre date_fin du tarif précédent à "2026-08-31"
```

---

### 2. Cron jobs ou migrations sans mettre à jour tarifs

❌ **Problème:** Migration de semestre, mais tarifs pas recalculés
- Timesheets avant/après migrations utilisent tarifs différents
- Montants peuvent être incorrects

✅ **Solution:** Ajouter migration/seeder pour tarifs
```php
// database/seeders/ProfesseurTarifSeeder.php
foreach (Professeur::all() as $prof) {
    $prof->tarifs()->create([
        'tarif_horaire_eur' => 7.50,
        'date_debut' => '2026-01-01',
        'date_fin' => null,
    ]);
}
```

---

### 3. Modifier un tarif au lieu de créer un nouveau

❌ **Mauvais:**
```php
$tarif = ProfesseurTarif::find(1);
$tarif->update(['tarif_horaire_eur' => 8.00]); // Modifie historique!
```
→ Timesheets anciens auront le NOUVEAU tarif (erroné)

✅ **Correct:**
```php
// 1. Terminer ancien tarif
$ancien = ProfesseurTarif::find(1);
$ancien->update(['date_fin' => '2026-08-31']);

// 2. Créer nouveau tarif
Professeur::find(5)->tarifs()->create([
    'tarif_horaire_eur' => 8.00,
    'date_debut' => '2026-09-01',
    'date_fin' => null,
]);
```

---

## 🚀 Checklist Déploiement

- [ ] Tous les professeurs ont au moins un tarif actif
- [ ] Tarifs `date_debut` et `date_fin` cohérents (pas de trous)
- [ ] Tarifs n'sont modifiés que via API (pas de SQL direct)
- [ ] Admin a accès au endpoint gestion tarifs
- [ ] Test: tarif effectif pour diverses dates
- [ ] Test: augmentation tarif en retro-actif
- [ ] Documentation: comment augmenter tarifs annuellement
- [ ] Backup: copier tarifs avant mise à jour

---

## 📞 FAQ

### Q: Comment appliquer une augmentation à tous les professeurs?

```bash
# 1. Pour chaque prof, terminer tarif actuel
# 2. Créer nouveau tarif avec nouveau montant
# 3. Ou: script Laravel

php artisan tinker
Professeur::all()->each(function($prof) {
  $ancien = $prof->tarifs()->latest()->first();
  $ancien->update(['date_fin' => '2026-12-31']);
  $prof->tarifs()->create([
    'tarif_horaire_eur' => 8.50,  // +0.50€
    'date_debut' => '2027-01-01',
    'date_fin' => null,
  ]);
});
```

---

### Q: Un tarif sans date_fin affecte les futurs timesheets?

**Oui.** Si `date_fin IS NULL`, le tarif est valide POUR TOUJOURS (sauf si un nouveau tarif le remplace).

```
Tarif 1: 7.50€, debut=2026-01-01, fin=NULL
Tarif 2: 8.00€, debut=2026-09-01, fin=NULL

2026-08-31: Tarif 1 (7.50€)
2026-09-01: Tarif 2 (8.00€) ← Tarif 1 ignore car Tarif 2 plus récent
2026-12-31: Tarif 2 (8.00€)
2027-06-15: Tarif 2 (8.00€)
```

---

### Q: Peut-on avoir un tarif 0€?

**Techniquement oui** (validation: `min:0`), mais **déconseillé** car:
- Logiquement illogique (travail non rémunéré)
- Peut faire bugger des calculs

Utiliser `date_fin` pour arrêter un tarif au lieu de le mettre à 0€.

---

### Q: Où sont stockés les montants calculés?

**Nulle part** — les montants sont **calculés à la demande** via l'endpoint `/preview-pdf`:
1. Récupère timesheets du mois
2. Pour chaque jour: récupère tarif effectif
3. Calcule montant = heures × tarif
4. Affiche et valide conformité

Cela signifie: si vous modifiez un tarif APRÈS avoir fait des timesheets, les montants se recalculent **automatiquement**. Mais c'est **seulement pour l'affichage** — les timesheets eux-mêmes ne stockent pas le montant (sauf dans le PDF généré).

---

**Version:** 1.0  
**Statut:** Production Ready

