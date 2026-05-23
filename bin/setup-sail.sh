#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

if [[ ! -f compose.yaml && ! -f docker-compose.yml ]]; then
    echo "Missing compose.yaml — run: php artisan sail:install --with=mysql,redis"
    exit 1
fi

if ! docker info >/dev/null 2>&1; then
    echo "Docker is not running."
    exit 1
fi

if [[ ! -f .env ]]; then
    cp .env.example .env
    bash bin/env.sh sail
fi

echo "→ Starting Sail"
./vendor/bin/sail up -d

echo "→ Waiting for MySQL"
ready=0
for _ in $(seq 1 30); do
    if ./vendor/bin/sail exec mysql mysqladmin ping -ppassword --silent 2>/dev/null; then
        ready=1
        break
    fi
    sleep 2
done

if [[ "$ready" -ne 1 ]]; then
    echo "MySQL did not become ready. Try: ./vendor/bin/sail logs mysql"
    exit 1
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    echo "→ Generating application key"
    ./vendor/bin/sail artisan key:generate --force
fi

echo "→ Linking storage"
./vendor/bin/sail artisan storage:link

echo "→ Running migrations"
./vendor/bin/sail artisan migrate --force

echo "→ Installing npm dependencies"
./vendor/bin/sail npm install

echo "→ Building frontend"
./vendor/bin/sail npm run build

echo ""
echo "Sail setup complete — http://localhost"
echo "Use: ./vendor/bin/sail artisan …"
