#!/usr/bin/env php
<?php
/**
 * Atualiza a primeira linha da tabela de assinatura no banco do tenant (seed free -> plano real).
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
 *
 * Opcional: TENANT_SUBSCRIPTIONS_TABLE=nome_fisico_da_tabela (se usar prefixo DB no portal, ex. app_subscriptions).
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
try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    fwrite(STDERR, 'sync-subscription-bootstrap: falha ao conectar ao MySQL: ' . $e->getMessage() . "\n");
    exit(1);
}

$overrideTable = trim((string) (getenv('TENANT_SUBSCRIPTIONS_TABLE') ?: ''));
if ($overrideTable !== '' && preg_match('/^[a-zA-Z0-9_]+$/', $overrideTable) !== 1) {
    fwrite(STDERR, "sync-subscription-bootstrap: TENANT_SUBSCRIPTIONS_TABLE invalido.\n");
    exit(1);
}

$subscriptionsTable = $overrideTable !== '' ? $overrideTable : resolveSubscriptionsTableName($pdo);
if ($subscriptionsTable === null) {
    $listed = implode(', ', array_map(static fn ($r) => (string) ($r[0] ?? ''), $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM)));
    fwrite(STDERR, "sync-subscription-bootstrap: nenhuma tabela de assinatura encontrada (esperado *subscriptions* sem 'payment'). Tabelas: {$listed}\n");
    exit(1);
}

$safeTable = str_replace('`', '', $subscriptionsTable);

$stmt = $pdo->query('SELECT MIN(id) FROM `' . $safeTable . '`');
$rowId = $stmt ? (int) $stmt->fetchColumn() : 0;
if ($rowId < 1) {
    fwrite(STDERR, "sync-subscription-bootstrap: tabela {$subscriptionsTable} vazia.\n");
    exit(1);
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

$qTable = '`' . $safeTable . '`';

/** @var array<string, string> coluna logica (minuscula) => nome fisico na tabela */
$physicalCols = subscriptionTablePhysicalColumns($pdo, $safeTable);
if ($physicalCols === []) {
    fwrite(STDERR, "sync-subscription-bootstrap: nao foi possivel ler colunas de {$subscriptionsTable}.\n");
    exit(1);
}

$values = [
    'plan_slug' => $planSlug,
    'plan_name' => $planName,
    'status' => $status,
    'gateway' => $gateway,
    'gateway_subscription_id' => $gwSub,
    'started_at' => $started,
    'next_billing_at' => $next,
    'ends_at' => $ends,
    'updated_at' => $now,
];

$setParts = [];
$params = [];
foreach ($values as $logical => $val) {
    $phys = $physicalCols[strtolower($logical)] ?? null;
    if ($phys === null) {
        continue;
    }
    $setParts[] = '`' . str_replace('`', '', $phys) . '` = ?';
    $params[] = $val;
}

if ($setParts === []) {
    fwrite(STDERR, 'sync-subscription-bootstrap: nenhuma coluna conhecida (plan_slug, plan_name, status, ...) existe na tabela. Colunas: '
        . implode(', ', array_values($physicalCols)) . "\n");
    exit(1);
}

$params[] = $rowId;
$sql = 'UPDATE ' . $qTable . ' SET ' . implode(', ', $setParts) . ' WHERE `id` = ?';

try {
    $st = $pdo->prepare($sql);
    $st->execute($params);
} catch (PDOException $e) {
    fwrite(STDERR, 'sync-subscription-bootstrap: erro no UPDATE: ' . $e->getMessage() . "\nSQL: {$sql}\n");
    exit(1);
}

$affected = $st->rowCount();
if ($affected < 1) {
    // MySQL pode retornar 0 linhas "alteradas" quando os valores ja eram iguais; confere leitura.
    if (subscriptionRowMatches($pdo, $safeTable, $rowId, $physicalCols, $planSlug, $status, $gateway, $gwSub, $planName)) {
        fwrite(STDERR, "sync-subscription-bootstrap: {$subscriptionsTable} id {$rowId} ja estava alinhado ({$planSlug}).\n");

        exit(0);
    }
    fwrite(STDERR, "sync-subscription-bootstrap: UPDATE nao alterou linhas nem conferiu dados (id={$rowId}, tabela={$subscriptionsTable}).\n");
    exit(1);
}

fwrite(STDERR, "sync-subscription-bootstrap: {$subscriptionsTable} id {$rowId} atualizado ({$planSlug}).\n");

/**
 * @return array<string, string> chave em minusculo => nome fisico do campo (Field)
 */
function subscriptionTablePhysicalColumns(PDO $pdo, string $safeTable): array
{
    $t = str_replace('`', '', $safeTable);
    $st = $pdo->query('SHOW COLUMNS FROM `' . $t . '`');
    if (! $st) {
        return [];
    }
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $f = (string) ($row['Field'] ?? '');
        if ($f !== '') {
            $out[strtolower($f)] = $f;
        }
    }

    return $out;
}

/**
 * Confere se a linha do tenant ja reflete o payload (para rowCount=0 apos UPDATE idempotente).
 *
 * @param array<string, string> $physicalCols
 */
function subscriptionRowMatches(
    PDO $pdo,
    string $safeTable,
    int $rowId,
    array $physicalCols,
    string $planSlug,
    string $status,
    string $gateway,
    ?string $gwSub,
    ?string $planName = null
): bool {
    $t = str_replace('`', '', $safeTable);
    $st = $pdo->prepare('SELECT * FROM `' . $t . '` WHERE `id` = ? LIMIT 1');
    $st->execute([$rowId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (! is_array($row)) {
        return false;
    }

    $slugKey = $physicalCols['plan_slug'] ?? null;
    if ($slugKey !== null) {
        $v = strtolower(trim((string) ($row[$slugKey] ?? '')));
        if ($v !== strtolower($planSlug)) {
            return false;
        }
    }

    $stKey = $physicalCols['status'] ?? null;
    if ($stKey !== null && strtolower((string) ($row[$stKey] ?? '')) !== strtolower($status)) {
        return false;
    }

    $gwKey = $physicalCols['gateway'] ?? null;
    if ($gwKey !== null && strtolower((string) ($row[$gwKey] ?? '')) !== strtolower($gateway)) {
        return false;
    }

    $gsKey = $physicalCols['gateway_subscription_id'] ?? null;
    if ($gsKey !== null) {
        $dbGs = $row[$gsKey] ?? null;
        $dbGs = ($dbGs !== null && $dbGs !== '') ? (string) $dbGs : null;
        if ($dbGs !== $gwSub) {
            return false;
        }
    }

    $nameKey = $physicalCols['plan_name'] ?? null;
    if ($nameKey !== null) {
        $want = ($planName === null || $planName === '') ? '' : trim((string) $planName);
        $got = trim((string) ($row[$nameKey] ?? ''));
        if ($want !== $got) {
            return false;
        }
    }

    return $slugKey !== null || $stKey !== null || $gwKey !== null || $gsKey !== null || $nameKey !== null;
}

/**
 * Descobre o nome físico da tabela: exatamente "subscriptions" (qualquer casing) ou termina em "subscriptions" (ex.: prefixo CI).
 *
 * @return non-empty-string|null
 */
function resolveSubscriptionsTableName(PDO $pdo): ?string
{
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM);
    $exact = null;
    $candidates = [];

    foreach ($tables as $row) {
        $t = (string) ($row[0] ?? '');
        if ($t === '') {
            continue;
        }
        if (strcasecmp($t, 'subscriptions') === 0) {
            $exact = $t;
            break;
        }
        $lower = strtolower($t);
        if (! str_ends_with($lower, 'subscriptions')) {
            continue;
        }
        if (str_contains($lower, 'subscription_payment') || str_contains($lower, 'subscriptionpay')) {
            continue;
        }
        $candidates[] = $t;
    }

    if ($exact !== null) {
        return $exact;
    }

    if ($candidates === []) {
        return null;
    }

    sort($candidates, SORT_STRING);

    return $candidates[0];
}
