#!/usr/bin/env bash
set -euo pipefail
export HOME=/var/www
export GIT_SSH_COMMAND="ssh -o BatchMode=yes -o StrictHostKeyChecking=yes"
export GIT_TERMINAL_PROMPT=0

# Exemplo de provisionador local (endpoint/backend worker), para custo baixo:
# - cria DB com nome do subdominio
# - cria pasta /var/www/html/{subdominio}
# - clona repo do portal
# - gera .env
# - roda migrations do portal
# - atualiza users (admin) e subscriptions (plano) a partir do JSON do painel
# - faz callback para o painel
#
# Variáveis opcionais vindas do index.php do provisionador:
#   TENANT_SUBSCRIPTION_JSON_FILE — caminho do JSON (preferido)
#   TENANT_SUBSCRIPTION_B64 — alternativa base64(JSON)
# Copie sync-subscription-bootstrap.php para o mesmo diretório deste script (ou defina SUBSCRIPTION_BOOTSTRAP_PHP).
#
# Atenção: adapte para seu ambiente e hardening.
#
# Requisitos comuns quando o clone falha (exit 255):
# - O usuário que executa este script (ex.: www-data via PHP) precisa de escrita em $(dirname APP_DIR), ex.: /srv/tenants
#   sudo mkdir -p /srv/tenants && sudo chown www-data:www-data /srv/tenants
# - Repo SSH (git@...): chave deploy em ~www-data/.ssh (e host no known_hosts), ou use URL HTTPS público
# - GIT_SSH_COMMAND acima usa StrictHostKeyChecking=yes — o primeiro clone exige o host já conhecido pelo usuário

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SUBSCRIPTION_BOOTSTRAP_PHP="${SUBSCRIPTION_BOOTSTRAP_PHP:-$SCRIPT_DIR/sync-subscription-bootstrap.php}"

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

# Roda como www-data quando o script é executado como root/outro usuário (ex.: provisionador via Apache).
run_as_www_data() {
  local wuid
  wuid="$(id -u www-data 2>/dev/null || true)"
  if [[ -n "$wuid" && "$(id -u)" != "$wuid" ]]; then
    sudo -u www-data -- "$@"
  else
    "$@"
  fi
}

# MySQL: evita -p na linha de comando (aviso "Using a password on the command line...").
MYSQL_CNF="$(mktemp)"
chmod 600 "$MYSQL_CNF"
{
  echo '[client]'
  echo "user=${MYSQL_ADMIN_USER}"
  echo "host=${MYSQL_HOST}"
  echo "port=${MYSQL_PORT}"
  if [[ -n "${MYSQL_ADMIN_PASS}" ]]; then
    echo "password=${MYSQL_ADMIN_PASS}"
  fi
} >"$MYSQL_CNF"
mysql --defaults-file="$MYSQL_CNF" <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'%' IDENTIFIED BY '$DB_PASS';
ALTER USER '$DB_USER'@'%' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'%';
FLUSH PRIVILEGES;
SQL
rm -f "$MYSQL_CNF"

PARENT_DIR="$(dirname "$APP_DIR")"
if ! mkdir -p "$PARENT_DIR" 2>/dev/null; then
  echo "Erro: nao foi possivel criar diretorio pai: ${PARENT_DIR}" >&2
  exit 1
fi
if [[ ! -w "$PARENT_DIR" ]]; then
  echo "Erro: sem permissao de escrita em ${PARENT_DIR} (usuario $(whoami)). Ajuste dono/grupo, ex.: chown www-data ${PARENT_DIR}" >&2
  exit 1
fi

if [[ -d "$APP_DIR/.git" ]]; then
  echo "Atualizando repo existente em ${APP_DIR} (ref ${PORTAL_REF})..." >&2
  git -C "$APP_DIR" fetch --all --prune
  git -C "$APP_DIR" checkout "$PORTAL_REF"
  git -C "$APP_DIR" pull --ff-only origin "$PORTAL_REF" || {
    echo "Erro: git pull falhou em ${APP_DIR}. Verifique rede, branch e permissoes." >&2
    exit 1
  }
else
  rm -rf "$APP_DIR"
  echo "Clonando ${PORTAL_REPO} -> ${APP_DIR} (branch ${PORTAL_REF})..." >&2
  if ! git clone --branch "$PORTAL_REF" "$PORTAL_REPO" "$APP_DIR"; then
    echo "Erro: git clone falhou. Causas frequentes: URL/repo inexistente; SSH sem chave para o usuario $(whoami); host nao esta em known_hosts (StrictHostKeyChecking); ou sem espaco em disco." >&2
    exit 1
  fi
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
cupomfiscal.ollamaBaseUrl = http://127.0.0.1:11434
cupomfiscal.ollamaModel = glm-ocr
cupomfiscal.iaProvider = ocrspace
cupomfiscal.ocrSpaceApiKey = K82986800088957
EOF

if [[ -f "$APP_DIR/composer.json" ]]; then
  run_as_www_data composer --working-dir="$APP_DIR" install --no-dev --optimize-autoloader || true
fi

if [[ -f "$APP_DIR/spark" ]]; then
    run_as_www_data php "$APP_DIR/spark" migrate -n App || run_as_www_data php "$APP_DIR/spark" migrate
fi

# Atualiza o primeiro usuário do portal (seed/migrations) com e-mail e hash do cadastro no painel.
if [[ -n "${TENANT_ADMIN_EMAIL:-}" && -n "${TENANT_ADMIN_PASSWORD_HASH:-}" ]]; then
  export MYSQL_HOST MYSQL_PORT DB_NAME DB_USER DB_PASS
  export TENANT_ADMIN_EMAIL TENANT_ADMIN_PASSWORD_HASH
  export TENANT_ADMIN_NAME="${TENANT_ADMIN_NAME:-Administrador}"
  run_as_www_data env MYSQL_HOST="$MYSQL_HOST" MYSQL_PORT="$MYSQL_PORT" \
    TENANT_DB_NAME="$DB_NAME" TENANT_DB_USER="$DB_USER" TENANT_DB_PASS="$DB_PASS" \
    TENANT_ADMIN_EMAIL="$TENANT_ADMIN_EMAIL" TENANT_ADMIN_PASSWORD_HASH="$TENANT_ADMIN_PASSWORD_HASH" \
    TENANT_ADMIN_NAME="$TENANT_ADMIN_NAME" \
    TENANT_SUBSCRIPTION_JSON_FILE="${TENANT_SUBSCRIPTION_JSON_FILE:-}" \
    SUBSCRIPTION_BOOTSTRAP_PHP="$SUBSCRIPTION_BOOTSTRAP_PHP" \
    php <<'BOOTSTRAP_ADMIN'
<?php
declare(strict_types=1);

$host = getenv('MYSQL_HOST') ?: '127.0.0.1';
$port = (int) (getenv('MYSQL_PORT') ?: '3306');
$db   = (string) getenv('TENANT_DB_NAME');
$user = (string) getenv('TENANT_DB_USER');
$pass = (string) getenv('TENANT_DB_PASS');
$email = trim((string) getenv('TENANT_ADMIN_EMAIL'));
$hash  = (string) getenv('TENANT_ADMIN_PASSWORD_HASH');
$name  = trim((string) getenv('TENANT_ADMIN_NAME')) ?: 'Administrador';

if ($email === '' || $hash === '' || $db === '') {
    fwrite(STDERR, "tenant-admin-bootstrap: faltam dados; nada feito.\n");
    exit(0);
}

$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $db);
$pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$cols = $pdo->query("SHOW COLUMNS FROM `users`")->fetchAll(PDO::FETCH_COLUMN, 0);
if (! in_array('password_hash', $cols, true) || ! in_array('email', $cols, true)) {
    fwrite(STDERR, "tenant-admin-bootstrap: tabela users sem email/password_hash; adapte ao seu portal.\n");
    exit(0);
}

$stmt = $pdo->query('SELECT MIN(id) FROM `users`');
$firstId = $stmt ? (int) $stmt->fetchColumn() : 0;
if ($firstId < 1) {
    fwrite(STDERR, "tenant-admin-bootstrap: nenhum usuario em users; rode migrations primeiro.\n");
    exit(0);
}

$sql = 'UPDATE `users` SET `email` = ?, `password_hash` = ?';
$params = [$email, $hash];
if (in_array('name', $cols, true)) {
    $sql .= ', `name` = ?';
    $params[] = $name;
}
if (in_array('updated_at', $cols, true)) {
    $sql .= ', `updated_at` = ?';
    $params[] = date('Y-m-d H:i:s');
}
$sql .= ' WHERE `id` = ?';
$params[] = $firstId;

$pdo->prepare($sql)->execute($params);
fwrite(STDERR, "tenant-admin-bootstrap: usuario id {$firstId} atualizado para {$email}\n");

// Assinatura: dados do painel em TENANT_SUBSCRIPTION_JSON_FILE — delega ao sync-subscription-bootstrap.php (UPDATE dinamico, MIN(id), etc.).
$jsonPath = (string) (getenv('TENANT_SUBSCRIPTION_JSON_FILE') ?: '');
$bootstrap = (string) (getenv('SUBSCRIPTION_BOOTSTRAP_PHP') ?: '');
if ($jsonPath !== '' && is_readable($jsonPath)) {
    if ($bootstrap === '' || ! is_readable($bootstrap)) {
        fwrite(STDERR, "tenant-admin-bootstrap: SUBSCRIPTION_BOOTSTRAP_PHP ilegivel ou vazio: {$bootstrap}\n");
        exit(1);
    }
    $phpBin = defined('PHP_BINARY') && is_string(PHP_BINARY) && PHP_BINARY !== '' ? PHP_BINARY : 'php';
    $envPairs = [
        'MYSQL_HOST' => $host,
        'MYSQL_PORT' => (string) $port,
        'TENANT_DB_NAME' => $db,
        'TENANT_DB_USER' => $user,
        'TENANT_DB_PASS' => $pass,
        'TENANT_SUBSCRIPTION_JSON_FILE' => $jsonPath,
    ];
    $parts = ['env'];
    foreach ($envPairs as $k => $v) {
        $parts[] = $k . '=' . escapeshellarg($v);
    }
    $parts[] = escapeshellarg($phpBin);
    $parts[] = escapeshellarg($bootstrap);
    passthru(implode(' ', $parts) . ' 2>&1', $syncExit);
    if ($syncExit !== 0) {
        exit($syncExit);
    }
}

BOOTSTRAP_ADMIN

fi

# JSON ainda existe (admin nao rodou ou nao recebeu o arquivo no env): sync isolado.
if [[ -n "${TENANT_SUBSCRIPTION_JSON_FILE:-}" && -f "${TENANT_SUBSCRIPTION_JSON_FILE}" ]]; then
  if [[ ! -f "$SUBSCRIPTION_BOOTSTRAP_PHP" ]]; then
    echo "Erro: $SUBSCRIPTION_BOOTSTRAP_PHP nao encontrado. Copie docs/sync-subscription-bootstrap.php do repositorio." >&2
    exit 1
  fi
  run_as_www_data env MYSQL_HOST="$MYSQL_HOST" MYSQL_PORT="$MYSQL_PORT" \
    TENANT_DB_NAME="$DB_NAME" TENANT_DB_USER="$DB_USER" TENANT_DB_PASS="$DB_PASS" \
    TENANT_SUBSCRIPTION_JSON_FILE="${TENANT_SUBSCRIPTION_JSON_FILE}" \
    php "$SUBSCRIPTION_BOOTSTRAP_PHP" || exit 1
fi

# Base64 (fluxo alternativo sem arquivo temporario).
if [[ -n "${TENANT_SUBSCRIPTION_B64:-}" ]]; then
  if [[ ! -f "$SUBSCRIPTION_BOOTSTRAP_PHP" ]]; then
    echo "Erro: $SUBSCRIPTION_BOOTSTRAP_PHP nao encontrado." >&2
    exit 1
  fi
  run_as_www_data env MYSQL_HOST="$MYSQL_HOST" MYSQL_PORT="$MYSQL_PORT" \
    TENANT_DB_NAME="$DB_NAME" TENANT_DB_USER="$DB_USER" TENANT_DB_PASS="$DB_PASS" \
    TENANT_SUBSCRIPTION_B64="$TENANT_SUBSCRIPTION_B64" \
    php "$SUBSCRIPTION_BOOTSTRAP_PHP" || exit 1
fi

# --fail: HTTP 4xx/5xx do painel faz o script falhar (evita "sucesso" sem gravar tenant_db_name).
if ! curl -fsS -X POST "$CALLBACK_URL" \
  -H "Authorization: Bearer ${CALLBACK_TOKEN}" \
  -H "Content-Type: application/json" \
  -d "{
    \"cliente_id\": ${CLIENT_ID},
    \"status\": \"ready\",
    \"db_name\": \"${DB_NAME}\",
    \"db_user\": \"${DB_USER}\"
  }"; then
  echo "Erro: callback para o painel falhou (URL/token ou rota). Verifique CALLBACK_URL e provisioning.callbackToken." >&2
  exit 1
fi

echo "Provisionamento concluido para ${SUBDOMAIN}"
