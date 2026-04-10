#!/usr/bin/env php
<?php
/**
 * Atualiza a primeira linha de `subscriptions` no banco do tenant (seed free -> plano real).
 *
 * Uso (credenciais do usuário do tenant — como no provision-tenant.sh):
 *   TENANT_DB_NAME=... TENANT_DB_USER=... TENANT_DB_PASS=... MYSQL_HOST=... MYSQL_PORT=...
 *   TENANT_SUBSCRIPTION_JSON_FILE=/tmp/x.json   OU   TENANT_SUBSCRIPTION_B64=...
 *   php sync-subscription-bootstrap.php
 *
 * Uso (somente credenciais admin — sync remoto pelo index.php do provisionador):
 *   TENANT_DB_NAME=... MYSQL_ADMIN_USER=... MYSQL_ADMIN_PASS=... MYSQL_HOST=... MYSQL_PORT=...
 *   TENANT_SUBSCRIPTION_JSON_FILE=...
 *   php sync-subscription-bootstrap.php
 */
declare(strict_types=1);

$jsonFile = (string) (getenv('TENANT_SUBSCRIPTION_JSON_FILE') ?: '');
$b64 = (string) (getenv('TENANT_SUBSCRIPTION_B64') ?: '');

$raw = '';
if ($jsonFile !== '' && is_readable($jsonFile)) {
    $raw = (string) file_get_contents($jsonFile);
    @unlink($jsonFile);
} elseif ($b64 !== '') {
    $decoded = base64_decode($b64, true);
    $raw = ($decoded !== false) ? $decoded : '';
}

if ($raw === '') {
    fwrite(STDERR, "sync-subscription-bootstrap: sem TENANT_SUBSCRIPTION_JSON_FILE legivel nem TENANT_SUBSCRIPTION_B64 valido.\n");
    exit(1);
}

$data = json_decode($raw, true);
if (! is_array($data)) {
    fwrite(STDERR, "sync-subscription-bootstrap: JSON invalido.\n");
    exit(1);
}

$dbName = (string) (getenv('TENANT_DB_NAME') ?: '');
if ($dbName === '') {
    fwrite(STDERR, "sync-subscription-bootstrap: TENANT_DB_NAME ausente.\n");
    exit(1);
}

$host = getenv('MYSQL_HOST') ?: '127.0.0.1';
$port = (int) (getenv('MYSQL_PORT') ?: '3306');

$tenantUser = (string) (getenv('TENANT_DB_USER') ?: '');
$tenantPass = (string) (getenv('TENANT_DB_PASS') ?: '');
if ($tenantUser !== '') {
    $user = $tenantUser;
    $pass = $tenantPass;
} else {
    $user = (string) (getenv('MYSQL_ADMIN_USER') ?: '');
    $pass = (string) (getenv('MYSQL_ADMIN_PASS') ?: '');
}

if ($user === '') {
    fwrite(STDERR, "sync-subscription-bootstrap: defina TENANT_DB_USER ou MYSQL_ADMIN_USER.\n");
    exit(1);
}

$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $dbName);
$pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM);
$hasSubscriptions = false;
foreach ($tables as $row) {
    $t = (string) ($row[0] ?? '');
    if (strcasecmp($t, 'subscriptions') === 0) {
        $hasSubscriptions = true;
        break;
    }
}

if (! $hasSubscriptions) {
    fwrite(STDERR, "sync-subscription-bootstrap: tabela subscriptions ausente.\n");
    exit(0);
}

$stmt = $pdo->query('SELECT MIN(id) FROM `subscriptions`');
$rowId = $stmt ? (int) $stmt->fetchColumn() : 0;
if ($rowId < 1) {
    fwrite(STDERR, "sync-subscription-bootstrap: subscriptions vazia.\n");
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

fwrite(STDERR, "sync-subscription-bootstrap: subscriptions id {$rowId} atualizado ({$planSlug}).\n");
