#!/usr/bin/env bash
set -euo pipefail

# Exemplo de provisionador local (endpoint/backend worker), para custo baixo:
# - cria DB com nome do subdominio
# - cria pasta /var/www/html/{subdominio}
# - clona repo do portal
# - gera .env
# - roda migrations do portal
# - faz callback para o painel
#
# Atenção: adapte para seu ambiente e hardening.

SUBDOMAIN="${1:-}"
DB_NAME="${2:-}"
APP_DIR="${3:-}"
PORTAL_REPO="${4:-}"
PORTAL_REF="${5:-main}"
CLIENT_ID="${6:-0}"
CALLBACK_URL="${7:-}"
CALLBACK_TOKEN="${8:-}"

if [[ -z "$SUBDOMAIN" || -z "$DB_NAME" || -z "$APP_DIR" || -z "$PORTAL_REPO" || -z "$CALLBACK_URL" ]]; then
  echo "Uso: $0 <subdomain> <db_name> <app_dir> <repo> <ref> <cliente_id> <callback_url> <callback_token>"
  exit 1
fi

if [[ ! "$SUBDOMAIN" =~ ^[a-z0-9][a-z0-9-]{0,62}$ ]]; then
  echo "Subdominio invalido"
  exit 1
fi

MYSQL_HOST="${MYSQL_HOST:-127.0.0.1}"
MYSQL_PORT="${MYSQL_PORT:-3306}"
MYSQL_ADMIN_USER="${MYSQL_ADMIN_USER:-root}"
MYSQL_ADMIN_PASS="${MYSQL_ADMIN_PASS:-}"
DB_USER="$SUBDOMAIN"
DB_PASS="$(openssl rand -base64 18 | tr -dc 'A-Za-z0-9' | head -c 24)"

mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_ADMIN_USER" -p"$MYSQL_ADMIN_PASS" <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'%' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'%';
FLUSH PRIVILEGES;
SQL

mkdir -p "$APP_DIR"
if [[ -d "$APP_DIR/.git" ]]; then
  git -C "$APP_DIR" fetch --all --prune
  git -C "$APP_DIR" checkout "$PORTAL_REF"
  git -C "$APP_DIR" pull --ff-only origin "$PORTAL_REF"
else
  rm -rf "$APP_DIR"
  git clone --branch "$PORTAL_REF" "$PORTAL_REPO" "$APP_DIR"
fi

cat > "$APP_DIR/.env" <<EOF
CI_ENVIRONMENT = production
app.baseURL = https://${SUBDOMAIN}.appdoce.top/
database.default.hostname = ${MYSQL_HOST}
database.default.database = ${DB_NAME}
database.default.username = ${DB_USER}
database.default.password = ${DB_PASS}
database.default.DBDriver = MySQLi
database.default.port = ${MYSQL_PORT}
EOF

if [[ -f "$APP_DIR/composer.json" ]]; then
  composer --working-dir="$APP_DIR" install --no-dev --optimize-autoloader || true
fi

if [[ -f "$APP_DIR/spark" ]]; then
  php "$APP_DIR/spark" migrate -n App || php "$APP_DIR/spark" migrate
fi

curl -sS -X POST "$CALLBACK_URL" \
  -H "Authorization: Bearer ${CALLBACK_TOKEN}" \
  -H "Content-Type: application/json" \
  -d "{
    \"cliente_id\": ${CLIENT_ID},
    \"status\": \"ready\",
    \"db_name\": \"${DB_NAME}\",
    \"db_user\": \"${DB_USER}\"
  }"

echo "Provisionamento concluido para ${SUBDOMAIN}"
