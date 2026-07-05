#!/bin/bash
set -euo pipefail

# Deja el entorno listo en Claude Code en la web (clon fresco sin .env ni
# vendor): dependencias, APP_KEY y base SQLite migrada, para que los tests,
# Pint y `php artisan serve` anden desde el primer momento. En local no hace
# nada: ahí el entorno ya está configurado.
if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
  exit 0
fi

cd "$CLAUDE_PROJECT_DIR"

composer install --no-interaction --prefer-dist

# Mismos pasos que `composer run setup`, pero idempotentes: no regenera la
# APP_KEY (invalidaría las sesiones de la base local) ni corre el build de
# Vite, que los tests no necesitan (TestCase usa withoutVite).
[ -f .env ] || cp .env.example .env
grep -q '^APP_KEY=.\+' .env || php artisan key:generate --force

[ -f database/database.sqlite ] || touch database/database.sqlite
php artisan migrate --force --no-interaction

npm install --ignore-scripts
