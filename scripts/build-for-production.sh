#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"

echo "==> Building frontend (production /app)..."
cd "$ROOT/frontend"
npm ci
npm run build

echo "==> Copying SPA into Laravel public/..."
rm -f "$ROOT/backend/public/index.html"
rm -rf "$ROOT/backend/public/assets"
cp -r dist/* "$ROOT/backend/public/"

echo "==> Done. Upload backend/ to the server (see DEPLOYMENT.md)."
