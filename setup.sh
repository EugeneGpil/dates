#!/bin/bash
set -e

GREEN='\033[0;32m'
NC='\033[0m'

echo ""
echo "=========================="
echo "  Dates — Project Setup   "
echo "=========================="
echo ""

# Check Docker
if ! docker info > /dev/null 2>&1; then
    echo "Docker is not running. Please start Docker first."
    exit 1
fi

# Root .env
if [ ! -f .env ]; then
    cp .env.example .env
    echo -e "${GREEN}Created .env from .env.example${NC}"
fi

# Compose reads .env by itself; this shell does not, and the psql calls below need the same
# database name and user that the container was given.
set -a
. ./.env
set +a

# Build images
echo "Building Docker images..."
docker compose build

# Start postgres first
docker compose up -d postgres
echo "Waiting for PostgreSQL to be ready..."
RETRIES=30
until docker compose exec -T postgres pg_isready -U "${DB_USERNAME:-dates}" > /dev/null 2>&1; do
    RETRIES=$((RETRIES - 1))
    if [ $RETRIES -le 0 ]; then
        echo "PostgreSQL failed to start. Check logs: docker compose logs postgres"
        exit 1
    fi
    sleep 2
done
echo -e "${GREEN}PostgreSQL ready.${NC}"

# The suite runs on Postgres rather than an in-memory sqlite (back/phpunit.xml says why), and
# `RefreshDatabase` truncates whatever it is pointed at — so it gets a database of its own.
# Created here rather than through /docker-entrypoint-initdb.d, which only runs on the very
# first init of the data directory and so would miss every existing checkout.
if ! docker compose exec -T postgres psql -U "${DB_USERNAME:-dates}" -tAc \
    "SELECT 1 FROM pg_database WHERE datname='${DB_DATABASE:-dates}_testing'" | grep -q 1; then
    docker compose exec -T postgres createdb -U "${DB_USERNAME:-dates}" "${DB_DATABASE:-dates}_testing"
    echo -e "${GREEN}Created the test database.${NC}"
fi

# Configure back/.env
if [ ! -f back/.env ]; then
    cp back/.env.example back/.env
fi

# Configure front/.env.local
if [ ! -f front/.env.local ]; then
    cp front/.env.example front/.env.local
    echo -e "${GREEN}Created front/.env.local from front/.env.example${NC}"
fi

# The API's own origin, because locally it does not share one with the front: the PWA is served
# from :8084 (nginx) or :9201 (the dev server) and Laravel answers on :8001. In production the
# two are same-origin — host nginx routes /api to Laravel — which is why .env.example leaves
# this empty and only the local file is given a value.
#
# The Firebase settings next to it stay empty and have to be filled by hand; docs/firebase.md.
sed -i "s|^VITE_API_URL=.*|VITE_API_URL=http://localhost:${NGINX_PORT:-8001}|" front/.env.local

configure_env() {
    local file="back/.env"
    sed -i "s|^APP_URL=.*|APP_URL=http://localhost:${NGINX_PORT:-8001}|" "$file"
    sed -i "s|^DB_CONNECTION=.*|DB_CONNECTION=pgsql|" "$file"
    sed -i "s|^# DB_HOST=.*|DB_HOST=postgres|" "$file"
    sed -i "s|^DB_HOST=.*|DB_HOST=postgres|" "$file"
    sed -i "s|^# DB_PORT=.*|DB_PORT=5432|" "$file"
    sed -i "s|^DB_PORT=.*|DB_PORT=5432|" "$file"
    sed -i "s|^# DB_DATABASE=.*|DB_DATABASE=${DB_DATABASE:-dates}|" "$file"
    sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${DB_DATABASE:-dates}|" "$file"
    sed -i "s|^# DB_USERNAME=.*|DB_USERNAME=${DB_USERNAME:-dates}|" "$file"
    sed -i "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USERNAME:-dates}|" "$file"
    sed -i "s|^# DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD:-secret}|" "$file"
    sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD:-secret}|" "$file"
    # The notification sends run on the queue (docker-compose.yml, service `queue`), so the
    # default `sync` driver would make them inline again and put FCM's latency back inside
    # the scheduler tick.
    sed -i "s|^QUEUE_CONNECTION=.*|QUEUE_CONNECTION=database|" "$file"
    sed -i "s|^SESSION_DRIVER=.*|SESSION_DRIVER=database|" "$file"

    # Appended rather than replaced, because a checkout made before this key existed has a
    # back/.env without it — and the file is gitignored, so nothing else would ever add it.
    # The JSON it points at is not in the repo either: docs/firebase.md says where to get it.
    grep -q '^FIREBASE_CREDENTIALS=' "$file" \
        || printf '\nFIREBASE_CREDENTIALS=storage/app/firebase-credentials.json\n' >> "$file"
}

configure_env
echo -e "${GREEN}back/.env configured.${NC}"

# Install composer dependencies
echo "Installing composer dependencies..."
docker compose run --rm --no-deps php composer install

# Generate app key if not set
docker compose run --rm --no-deps php php artisan key:generate --no-interaction

# Install node dependencies
echo "Installing node dependencies..."
docker compose run --rm --no-deps node npm install

# Build the PWA before the containers come up: nginx bind-mounts front/dist/pwa, and a bind
# mount whose source is missing is created by the daemon as a root-owned directory.
echo "Building the PWA..."
docker compose run --rm --no-deps node npm run build

# Start all services and run migrations
docker compose up -d
echo "Running migrations..."
docker compose exec php php artisan migrate --force

echo ""
echo -e "${GREEN}=========================="
echo "  Setup complete!"
echo -e "==========================${NC}"
echo ""
echo "  API:   http://localhost:${NGINX_PORT:-8001}/api/health"
echo "  PWA:   http://localhost:${NGINX_FRONT_PORT:-8084}"
echo "  Dev:   make node, then npm run dev  (http://localhost:${QUASAR_PORT:-9201})"
echo ""
