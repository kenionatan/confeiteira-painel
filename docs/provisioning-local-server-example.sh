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
#   TENANT_SUBSCRIPTION_B64 — base64(JSON) com plan_slug, plan_name, status, gateway, etc.
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

# CREATE USER IF NOT EXISTS não atualiza senha em reexecução; ALTER USER mantém MySQL e .env iguais.
mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_ADMIN_USER" -p"$MYSQL_ADMIN_PASS" <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'%' IDENTIFIED BY '$DB_PASS';
ALTER USER '$DB_USER'@'%' IDENTIFIED BY '$DB_PASS';
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
BOOTSTRAP_ADMIN

fi

# Atualiza a linha de assinatura criada pelo seed da migration do portal (ex.: plano free -> plano real).
if [[ -n "${TENANT_SUBSCRIPTION_B64:-}" ]]; then
  run_as_www_data env MYSQL_HOST="$MYSQL_HOST" MYSQL_PORT="$MYSQL_PORT" \
    TENANT_DB_NAME="$DB_NAME" TENANT_DB_USER="$DB_USER" TENANT_DB_PASS="$DB_PASS" \
    TENANT_SUBSCRIPTION_B64="$TENANT_SUBSCRIPTION_B64" \
    php <<'BOOTSTRAP_SUBSCRIPTION'
<?php
declare(strict_types=1);

$b64 = (string) getenv('TENANT_SUBSCRIPTION_B64');
if ($b64 === '') {
    exit(0);
}

$raw = base64_decode($b64, true);
if ($raw === false || $raw === '') {
    fwrite(STDERR, "tenant-subscription-bootstrap: TENANT_SUBSCRIPTION_B64 invalido.\n");
    exit(1);
}

$data = json_decode($raw, true);
if (! is_array($data)) {
    fwrite(STDERR, "tenant-subscription-bootstrap: JSON invalido apos base64.\n");
    exit(1);
}

$host = getenv('MYSQL_HOST') ?: '127.0.0.1';
$port = (int) (getenv('MYSQL_PORT') ?: '3306');
$db   = (string) getenv('TENANT_DB_NAME');
$user = (string) getenv('TENANT_DB_USER');
$pass = (string) getenv('TENANT_DB_PASS');

$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $db);
$pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM);
$hasSubscriptions = false;
foreach ($tables as $row) {
    if (($row[0] ?? '') === 'subscriptions') {
        $hasSubscriptions = true;
        break;
    }
}
if (! $hasSubscriptions) {
    fwrite(STDERR, "tenant-subscription-bootstrap: tabela subscriptions ausente; nada feito.\n");
    exit(0);
}

$stmt = $pdo->query('SELECT MIN(id) FROM `subscriptions`');
$rowId = $stmt ? (int) $stmt->fetchColumn() : 0;
if ($rowId < 1) {
    fwrite(STDERR, "tenant-subscription-bootstrap: subscriptions vazia; nada feito.\n");
    exit(0);
}

$allowedStatus = ['trial', 'active', 'past_due', 'cancelled'];
$status = (string) ($data['status'] ?? 'trial');
if (! in_array($status, $allowedStatus, true)) {
    $status = 'trial';
}

$planSlug = substr(trim((string) ($data['plan_slug'] ?? 'free')), 0, 40);
if ($planSlug === '') {
    $planSlug = 'free';
}
$planName = isset($data['plan_name']) && $data['plan_name'] !== null && $data['plan_name'] !== ''
    ? substr((string) $data['plan_name'], 0, 120)
    : null;
$gateway = substr(trim((string) ($data['gateway'] ?? 'none')), 0, 40);
if ($gateway === '') {
    $gateway = 'none';
}

$gwSub = $data['gateway_subscription_id'] ?? null;
$gwSub = ($gwSub !== null && $gwSub !== '') ? substr((string) $gwSub, 0, 120) : null;

$nullOrString = static function ($v): ?string {
    if ($v === null || $v === '') {
        return null;
    }

    return (string) $v;
};

$started = $nullOrString($data['started_at'] ?? null);
$next = $nullOrString($data['next_billing_at'] ?? null);
$ends = $nullOrString($data['ends_at'] ?? null);
$now = date('Y-m-d H:i:s');

$sql = 'UPDATE `subscriptions` SET
    `plan_slug` = ?, `plan_name` = ?, `status` = ?, `gateway` = ?, `gateway_subscription_id` = ?,
    `started_at` = ?, `next_billing_at` = ?, `ends_at` = ?, `updated_at` = ?
    WHERE `id` = ?';

$pdo->prepare($sql)->execute([
    $planSlug,
    $planName,
    $status,
    $gateway,
    $gwSub,
    $started,
    $next,
    $ends,
    $now,
    $rowId,
]);

fwrite(STDERR, "tenant-subscription-bootstrap: subscriptions id {$rowId} atualizado ({$planSlug})\n");
BOOTSTRAP_SUBSCRIPTION

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
