#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

if [[ ! -f .env ]]; then
    echo "Missing .env — run composer setup or composer setup:sail first."
    exit 1
fi

target="${1:-}"
file=".env"

replace() {
    local key="$1"
    local value="$2"

    if grep -q "^${key}=" "$file"; then
        if [[ "$OSTYPE" == darwin* ]]; then
            sed -i '' "s|^${key}=.*|${key}=${value}|" "$file"
        else
            sed -i "s|^${key}=.*|${key}=${value}|" "$file"
        fi
    else
        echo "${key}=${value}" >>"$file"
    fi
}

case "$target" in
local)
    replace DB_HOST 127.0.0.1
    replace DB_USERNAME root
    replace DB_PASSWORD ""
    replace REDIS_HOST 127.0.0.1
    echo "→ .env configured for local (Herd, Valet, native MySQL/Redis)"
    ;;
sail)
    replace DB_HOST mysql
    replace DB_USERNAME sail
    replace DB_PASSWORD password
    replace REDIS_HOST redis
    echo "→ .env configured for Sail (Docker)"
    ;;
*)
    echo "Usage: bin/env.sh local|sail"
    exit 1
    ;;
esac

echo "Run: php artisan config:clear"
