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

# Build images
echo "Building Docker images..."
docker compose build

# Start postgres first
docker compose up -d postgres
echo "Waiting for PostgreSQL to be ready..."
RETRIES=30
until docker compose exec -T postgres pg_isready -U dates > /dev/null 2>&1; do
    RETRIES=$((RETRIES - 1))
    if [ $RETRIES -le 0 ]; then
        echo "PostgreSQL failed to start. Check logs: docker compose logs postgres"
        exit 1
    fi
    sleep 2
done
echo -e "${GREEN}PostgreSQL ready.${NC}"

# Configure back/.env
if [ ! -f back/.env ]; then
    cp back/.env.example back/.env
fi

# Configure front/.env.local
if [ ! -f front/.env.local ]; then
    cp front/.env.example front/.env.local
    echo -e "${GREEN}Created front/.env.local from front/.env.example${NC}"
fi

configure_env() {
    local file="back/.env"
    sed -i "s|^APP_URL=.*|APP_URL=http://localhost:8001|" "$file"
    sed -i "s|^DB_CONNECTION=.*|DB_CONNECTION=pgsql|" "$file"
    sed -i "s|^# DB_HOST=.*|DB_HOST=postgres|" "$file"
    sed -i "s|^DB_HOST=.*|DB_HOST=postgres|" "$file"
    sed -i "s|^# DB_PORT=.*|DB_PORT=5432|" "$file"
    sed -i "s|^DB_PORT=.*|DB_PORT=5432|" "$file"
    sed -i "s|^# DB_DATABASE=.*|DB_DATABASE=dates|" "$file"
    sed -i "s|^DB_DATABASE=.*|DB_DATABASE=dates|" "$file"
    sed -i "s|^# DB_USERNAME=.*|DB_USERNAME=dates|" "$file"
    sed -i "s|^DB_USERNAME=.*|DB_USERNAME=dates|" "$file"
    sed -i "s|^# DB_PASSWORD=.*|DB_PASSWORD=secret|" "$file"
    sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=secret|" "$file"
    # The notification sends run on the queue (docker-compose.yml, service `queue`), so the
    # default `sync` driver would make them inline again and put FCM's latency back inside
    # the scheduler tick.
    sed -i "s|^QUEUE_CONNECTION=.*|QUEUE_CONNECTION=database|" "$file"
    sed -i "s|^SESSION_DRIVER=.*|SESSION_DRIVER=database|" "$file"
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
echo "  API:   http://localhost:8001/api/health"
echo "  PWA:   http://localhost:8084"
echo "  Dev:   make node, then npm run dev  (http://localhost:9201)"
echo ""
