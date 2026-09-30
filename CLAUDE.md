# Logiscool Pays Vert

Backend Laravel 11 (Sanctum) dans `backend/`, frontend React 19 + Vite dans `frontend/`. Rôles : `admin` > `staff` (directeur) > `professeur`.

## Règles obligatoires pour toute évolution

- Standards de développement : `docs/DEVELOPMENT_STANDARDS.md` (Definition of Ready / Done, checklist « tranche verticale » §7).
- Avant de coder une feature : remplir le Canvas `docs/REQUIREMENTS_CANVAS.md` (copie dans `docs/requirements/`).
- Une évolution est livrée seulement si DB → API → UI → tests fonctionnent ensemble pour l'utilisateur final.
- Backend = source de vérité (règles, statuts, permissions) ; le front utilise uniquement `frontend/src/api/client.js` (jamais `axios` brut).
- Commandes : `scripts/test-backend.sh` (démarre la base MySQL de test `db_test` puis lance `php artisan test`) · `cd frontend && npm run lint && npm run build`.
- Tests back-end : **uniquement** sur la base MySQL dédiée `lgit_test` (Docker `db_test`, port 3309). Un garde-fou dans `backend/tests/TestCase.php` refuse toute autre base ; ne jamais contourner.
- Avant de coder une feature demande un sub-agent UX Expert de définir le workflow efficace et pertinent s'intégrant dans les trajets actuels de l'application
- Utilise des mock-ups pour demander la validation avant de pouvoir développer
