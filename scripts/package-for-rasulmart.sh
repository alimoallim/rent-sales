#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$ROOT/deploy/output"
MODE="${1:-app}"

echo "==> Build mode: $MODE"

if [[ "$MODE" == "app" ]]; then
  ENV_FILE="$ROOT/frontend/.env.production"
  APP_HTACCESS="$ROOT/deploy/rasulmart/symlink-app.htaccess"
elif [[ "$MODE" == "rent-sales-public" ]]; then
  ENV_FILE="$ROOT/frontend/.env.production.rent-sales-public"
  APP_HTACCESS="$ROOT/deploy/rasulmart/rent-sales-public.htaccess"
else
  echo "Usage: $0 [app|rent-sales-public]"
  exit 1
fi

echo "==> Building frontend..."
cd "$ROOT/frontend"
cp "$ENV_FILE" .env.production.local
npm ci
npm run build
rm -f .env.production.local

echo "==> Preparing deploy/output..."
rm -rf "$OUT"
mkdir -p "$OUT/public_html/rent-sales"

echo "==> Installing PHP dependencies (production)..."
cd "$ROOT/backend"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Copying Laravel backend to public_html/rent-sales/..."
rsync -a \
  --exclude='node_modules' \
  --exclude='.git' \
  --exclude='tests' \
  --exclude='.env' \
  --exclude='.phpunit.result.cache' \
  --exclude='storage/logs/*' \
  "$ROOT/backend/" "$OUT/public_html/rent-sales/"

echo "==> Copying SPA into rent-sales/public/..."
cp "$APP_HTACCESS" "$OUT/public_html/rent-sales/public/.htaccess"
cp -r "$ROOT/frontend/dist/"* "$OUT/public_html/rent-sales/public/"
if [[ "$MODE" == "app" ]]; then
  cp "$ROOT/deploy/rasulmart/rent-sales-root.htaccess" "$OUT/public_html/rent-sales/.htaccess"
fi

echo "==> Restoring local dev PHP dependencies..."
cd "$ROOT/backend"
composer install --no-interaction --quiet

cat > "$OUT/public_html/rent-sales/.env.example.production" <<'EOF'
APP_NAME="Rasul Mart Rent & Sales"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://rasulmart.com/app

DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=rasulmar_rent_sales
DB_USERNAME=rasulmar_alisax
DB_PASSWORD=CHANGE_ME

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_PATH=/app
SESSION_DOMAIN=rasulmart.com
SESSION_SECURE_COOKIE=true
SANCTUM_STATEFUL_DOMAINS=rasulmart.com,www.rasulmart.com
FRONTEND_URL=https://rasulmart.com

# Optional: absolute path to SPA index when using public_html/app/ as web root
# SPA_INDEX_PATH=/home/rasulmar/public_html/app/index.html

CACHE_STORE=database
QUEUE_CONNECTION=database
EOF

if [[ "$MODE" == "rent-sales-public" ]]; then
  sed -i 's|/app|/rent-sales/public|g' "$OUT/public_html/rent-sales/.env.example.production"
fi

echo ""
echo "Done. Upload the contents of:"
echo "  $OUT/public_html/"
echo "If public_html/app is a symlink to rent-sales/public, upload rent-sales/public/ only."
echo ""
echo "Then on the server:"
echo "  cd ~/public_html/rent-sales"
echo "  cp .env.example.production .env"
echo "  php artisan key:generate"
echo "  chmod -R ug+rwx storage bootstrap/cache"
echo "  php artisan migrate --force"
echo "  php artisan config:cache"
