#!/bin/bash

# Couleurs
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}  🚀 Démarrage Local - Phase 1A+1B+2${NC}"
echo -e "${BLUE}========================================${NC}"
echo

# Vérify ports
echo -e "${YELLOW}📡 Vérification des ports...${NC}"
if lsof -Pi :8000 -sTCP:LISTEN -t >/dev/null ; then
    echo -e "${YELLOW}⚠️  Port 8000 déjà en use. Arrêt...${NC}"
    lsof -ti :8000 | xargs kill -9 2>/dev/null || true
    sleep 1
fi

if lsof -Pi :5173 -sTCP:LISTEN -t >/dev/null ; then
    echo -e "${YELLOW}⚠️  Port 5173 déjà en use. Arrêt...${NC}"
    lsof -ti :5173 | xargs kill -9 2>/dev/null || true
    sleep 1
fi

echo -e "${GREEN}✓ Ports libres${NC}"
echo

# Check DB
echo -e "${YELLOW}🗄️  Vérification base de données...${NC}"
if [ ! -f "backend/database/database.sqlite" ]; then
    echo -e "${YELLOW}📦 Création DB et seed${NC}"
    cd backend
    php artisan migrate:fresh --seed
    cd ..
fi
echo -e "${GREEN}✓ DB prête${NC}"
echo

# Start servers
echo -e "${GREEN}🟢 Démarrage serveurs...${NC}"
echo

# Terminal 1: Backend
echo -e "${BLUE}[Terminal 1/2] Démarrage Laravel (backend)${NC}"
echo -e "${BLUE}Commande:${NC} cd backend && php artisan serve"
echo -e "${BLUE}Accès:${NC} http://localhost:8000/api"
echo

# Terminal 2: Frontend
echo -e "${BLUE}[Terminal 2/2] Démarrage Vite (frontend)${NC}"
echo -e "${BLUE}Commande:${NC} cd frontend && npm run dev"
echo -e "${BLUE}Accès:${NC} http://localhost:5173"
echo

# Créer les commandes
BACKEND_CMD="cd backend && php artisan serve"
FRONTEND_CMD="cd frontend && npm run dev"

# Vérifier si tmux ou screen est disponible
if command -v tmux &> /dev/null; then
    echo -e "${GREEN}✓ tmux détecté${NC}"
    echo -e "${YELLOW}Démarrage avec tmux...${NC}"
    echo

    # Créer une nouvelle session tmux
    tmux new-session -d -s lg-app -x 200 -y 50

    # Window 1: Backend
    tmux send-keys -t lg-app "cd /Users/greg/Developments/lg-it-app/backend" Enter
    sleep 0.5
    tmux send-keys -t lg-app "clear && echo '=== BACKEND (Laravel) ===' && php artisan serve" Enter

    sleep 2

    # Window 2: Frontend
    tmux new-window -t lg-app
    tmux send-keys -t lg-app "cd /Users/greg/Developments/lg-it-app/frontend" Enter
    sleep 0.5
    tmux send-keys -t lg-app "clear && echo '=== FRONTEND (Vite) ===' && npm run dev" Enter

    sleep 2

    # Select first window
    tmux select-window -t lg-app:0

    echo -e "${GREEN}✓ Session tmux 'lg-app' créée${NC}"
    echo
    echo -e "${YELLOW}Commandes utiles:${NC}"
    echo "  tmux attach-session -t lg-app        (rejoindre la session)"
    echo "  tmux kill-session -t lg-app          (arrêter tout)"
    echo "  tmux select-window -t lg-app:0       (aller window 1 backend)"
    echo "  tmux select-window -t lg-app:1       (aller window 2 frontend)"
    echo

    sleep 3

    # Attacher à la session
    tmux attach-session -t lg-app

elif command -v screen &> /dev/null; then
    echo -e "${GREEN}✓ screen détecté${NC}"
    echo -e "${YELLOW}Démarrage avec screen...${NC}"
    echo

    # Créer une nouvelle session screen
    screen -dmS lg-app

    # Send commands
    screen -S lg-app -X stuff "cd /Users/greg/Developments/lg-it-app/backend && php artisan serve\n"
    sleep 2
    screen -S lg-app -X screen "cd /Users/greg/Developments/lg-it-app/frontend && npm run dev\n"

    echo -e "${GREEN}✓ Session screen 'lg-app' créée${NC}"
    echo
    echo -e "${YELLOW}Commandes utiles:${NC}"
    echo "  screen -r lg-app                 (rejoindre la session)"
    echo "  screen -X -S lg-app kill         (arrêter tout)"
    echo "  screen -S lg-app -p 0            (afficher screen 1)"
    echo

    sleep 2
    screen -r lg-app

else
    # Fallback: instructions manuelles
    echo -e "${YELLOW}❌ tmux/screen non trouvés${NC}"
    echo
    echo -e "${YELLOW}Démarrage manuel (2 terminaux):${NC}"
    echo
    echo -e "${BLUE}Terminal 1 - Backend:${NC}"
    echo "  cd /Users/greg/Developments/lg-it-app"
    echo "  $BACKEND_CMD"
    echo
    echo -e "${BLUE}Terminal 2 - Frontend:${NC}"
    echo "  cd /Users/greg/Developments/lg-it-app"
    echo "  $FRONTEND_CMD"
    echo
    echo -e "${YELLOW}Attente 5 secondes avant démarrage manuel...${NC}"
    sleep 5

    # Démarrer juste le backend dans ce terminal
    cd /Users/greg/Developments/lg-it-app
    eval "$BACKEND_CMD"
fi
