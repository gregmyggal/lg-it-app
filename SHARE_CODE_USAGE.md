# Système d'Accès par Codes — Guide d'Utilisation

## Vue d'ensemble

L'application a transition vers un **portail privé uniquement** avec un système d'**accès par codes** pour les élèves. Plus de site public.

### Nouvelle Architecture

```
┌─────────────────────────────────────────────┐
│  Frontend (Portail Privé)                   │
├─────────────────────────────────────────────┤
│  ✓ /connexion           - Page de login     │
│  ✓ /mes-cours           - Mes cours (auth)  │
│  ✓ /timesheets          - Feuilles (auth)   │
│  ✓ /admin/*             - Admin (auth)      │
│  ✓ /share/:code         - Accès par code    │
└─────────────────────────────────────────────┘

┌─────────────────────────────────────────────┐
│  Backend API                                 │
├─────────────────────────────────────────────┤
│  ✓ POST /api/login                          │
│  ✓ GET /api/share/:code       - PUBLIC      │
│  ✓ GET /api/cours, /stages    - AUTH        │
│  ✓ PATCH /api/timesheets      - AUTH        │
│  ✗ GET /api/public/*          - REMOVED     │
└─────────────────────────────────────────────┘
```

---

## Pour les Professeurs: Générer des Codes d'Accès

### Via Tinker (CLI)

```bash
cd backend
php artisan tinker
```

```php
// Générer un code pour un cours
$cours = App\Models\Cours::find(1);
$code = App\Models\ShareCode::create([
    'code' => App\Models\ShareCode::generateCode(),
    'shareable' => $cours,
    'created_by' => Auth::id(), // optionnel
    'max_uses' => 100,           // optionnel
    'expires_at' => now()->addDays(30), // optionnel
]);

echo "Code: " . $code->code;
// Output: Code: ABC123XYZ456
```

### Via une UI Admin (À implémenter)

À ajouter dans le portail admin:
1. `/admin/share-codes` - Liste des codes générés
2. Bouton "Générer code" sur chaque cours/stage/formation
3. Visualiser: code, date de création, usage stats, expiration

---

## Pour les Élèves: Accéder aux Ressources

### URL d'Accès

Les professeurs partagent cette URL avec leurs élèves:

```
https://app.lg-it.com/share/ABC123XYZ456
```

### Flux d'Utilisation

1. **Cliquer sur le lien** (pas de login requis)
2. **Voir le contenu** : titre, description, ressources
3. **Accéder aux ressources** : cliquer sur les liens
4. **Continuer vers Logiscool** : bouton CTA si configuré

### Limitation des Codes

Les codes peuvent avoir des limites:

| Paramètre | Description |
|-----------|-------------|
| `max_uses` | Nombre maximum d'accès (ex: 100) |
| `expires_at` | Date d'expiration (ex: 30 jours) |
| `used_count` | Nombre d'accès actuels (auto-incrémenté) |

Quand un code expire ou atteint sa limite:
```
"Cette code d'accès a atteint sa limite d'utilisation"
```

---

## API Endpoints

### Générer un Code (Backend - Admin uniquement)

**À implémenter.** Pattern suggéré:

```http
POST /api/share-codes
Authorization: Bearer {token}
Content-Type: application/json

{
  "shareable_type": "Cours",
  "shareable_id": 1,
  "max_uses": 100,
  "expires_at": "2026-10-29T23:59:59Z"
}

Response:
{
  "id": 1,
  "code": "ABC123XYZ456",
  "shareable": { "type": "Cours", "titre": "Mon cours" },
  "created_by": 5,
  "max_uses": 100,
  "used_count": 0,
  "expires_at": "2026-10-29T23:59:59Z",
  "created_at": "2026-09-29T10:00:00Z"
}
```

### Accéder au Contenu (Public - sans auth)

```http
GET /api/share/ABC123XYZ456
X-Share-Code: ABC123XYZ456

Response:
{
  "type": "Cours",
  "content": {
    "id": 1,
    "titre": "Mon cours",
    "contenu": "...",
    "extrait": "...",
    "statut": "publish",
    ...
  },
  "ressources": [
    {
      "id": 1,
      "titre": "PDF Ressource",
      "url": "https://...",
      "description": "..."
    }
  ],
  "types": [
    { "id": 1, "nom": "Enfants" }
  ]
}
```

**Réponses d'erreur:**

```json
// Code invalide
{
  "error": "Invalid share code"
}

// Code expiré / limite atteinte
{
  "error": "Share code is expired or has reached its usage limit"
}

// Contenu non publié
{
  "error": "Content not found or not published"
}
```

---

## Modèle de Données

### Table `share_codes`

```sql
CREATE TABLE share_codes (
  id BIGINT PRIMARY KEY,
  code VARCHAR(12) UNIQUE,           -- ex: ABC123XYZ456
  shareable_type VARCHAR(255),       -- Cours, Stage, Formation, Anniversaire
  shareable_id BIGINT,               -- ID du contenu
  created_by BIGINT NULLABLE,        -- Professeur qui a généré
  expires_at TIMESTAMP NULLABLE,     -- Date d'expiration
  max_uses INT NULLABLE,             -- Max d'accès
  used_count INT DEFAULT 0,          -- Accès actuels
  created_at TIMESTAMP,
  updated_at TIMESTAMP
);
```

### Relations

```php
$shareCode->shareable();      // Polymorphic: Cours|Stage|Formation|Anniversaire
$shareCode->creator();        // User qui a généré le code
```

---

## Migration et Déploiement

### Base de Données

La migration `2026_09_29_100000_create_share_codes_table.php` a été exécutée.

Vérifier dans Tinker:

```php
\Illuminate\Support\Facades\DB::table('share_codes')->count()
// 0 rows initialement
```

### Frontend Build

```bash
cd frontend
npm run build
# Vérify: dist/ contient SharePage (pas HomePage)
```

### Variables d'Environnement

Frontend `.env`:
```
VITE_API_URL=http://localhost:8000
```

Pas de changement backend requis.

---

## Support & Maintenance

### Générer un Nouveau Code

```bash
php artisan tinker
$code = App\Models\ShareCode::generateCode();
```

### Lister Tous les Codes

```bash
php artisan tinker
App\Models\ShareCode::all();
```

### Révoquer un Code

```bash
php artisan tinker
$sc = App\Models\ShareCode::where('code', 'ABC123XYZ456')->first();
$sc->delete();
```

### Debugging

Vérifier le middleware:
```php
// Intercepte les requêtes à /api/share/:code
// Valide le code, incrémente used_count
// Retourne 404/403 si invalide
```

Vérifier le contrôleur:
```php
// Récupère le contenu polymorphe
// Retourne contenu + relations (ressources, types, dates)
```

---

## Next Steps (Futur)

### À Implémenter

1. **UI Admin pour Générer des Codes**
   - Page `/admin/share-codes`
   - Formulaire de création avec options (max_uses, expires_at)
   - Liste des codes actifs + statistiques d'usage

2. **Email Sharing**
   - Bouton "Partager par email" sur chaque cours
   - Génère code + envoie URL aux adresses

3. **Tracking Avancé**
   - Log des accès (qui, quand, depuis où)
   - Analytics par code

4. **Branding Personnalisé**
   - Logo/couleurs de l'école sur /share/:code
   - Emails personnalisés

---

## Questions Fréquentes

**Q: Un élève peut-il utiliser le même code plusieurs fois?**  
A: Oui, sauf si `max_uses` est atteint. Le code incrémente `used_count` à chaque accès.

**Q: Le code expire-t-il automatiquement?**  
A: Oui, si `expires_at` est configuré. Sinon, le code ne expire jamais (valable indéfiniment).

**Q: Peut-on voir qui a accédé?**  
A: Pour l'instant, non. C'est sur la roadmap (logging).

**Q: Et pour les stages/formations/anniversaires?**  
A: Même système polymorphe. Génère un code de la même façon.

**Q: Que se passe-t-il si le contenu est passé en draft?**  
A: Le code retourne 404 ("Content not found or not published").
