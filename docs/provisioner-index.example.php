<?php
/**
 * Exemplo de endpoint do provisionador (ex.: /var/www/provisioner/public/index.php).
 *
 * Copie para o servidor como index.php e:
 * - copie sync-subscription-bootstrap.php para o mesmo diretório do provision-tenant.sh (ex.: /usr/local/lib/provisioning/)
 * - defina SYNC_SUBSCRIPTION_PHP se o bootstrap não estiver ao lado do .sh
 *
 * Fluxos:
 * - Provisionamento completo: JSON padrão + tenant_subscription → arquivo temporário (evita limite de linha de comando com Base64).
 * - sync_subscription_only: só atualiza subscriptions no banco do tenant (MySQL admin).
 */
declare(strict_types=1);

header('Content-Type: application/json');

$expectedToken = getenv('PROVISIONER_TOKEN') ?: '';
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

if ($auth === '' && function_exists('apache_request_headers')) {
    $h = apache_request_headers();
    $auth = $h['Authorization'] ?? $h['authorization'] ?? '';
}

if ($expectedToken === '') {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'PROVISIONER_TOKEN nao configurado']);
    exit;
}

if (! hash_equals('Bearer ' . $expectedToken, $auth)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Nao autorizado']);
    exit;
}

$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (! is_array($data)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'JSON invalido']);
    exit;
}

$bootstrapPhp = getenv('SYNC_SUBSCRIPTION_PHP') ?: '/usr/local/lib/provisioning/sync-subscription-bootstrap.php';

/**
 * Só atualiza subscriptions (tenant já existe).
 */
if (($data['action'] ?? '') === 'sync_subscription_only') {
    foreach (['requested_subdomain', 'requested_db_name', 'tenant_subscription'] as $field) {
        if (empty($data[$field]) && $field !== 'tenant_subscription') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => "Campo ausente: {$field}"]);
            exit;
        }
    }
    if (! is_array($data['tenant_subscription'])) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'tenant_subscription invalido']);
        exit;
    }

    $sub = (string) $data['requested_subdomain'];
    if (! preg_match('/^[a-z0-9][a-z0-9-]{0,62}$/', $sub)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Subdominio invalido']);
        exit;
    }

    $dbName = (string) $data['requested_db_name'];

    if (! is_readable($bootstrapPhp)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'SYNC_SUBSCRIPTION_PHP ilegivel: ' . $bootstrapPhp]);
        exit;
    }

    $tmp = tempnam(sys_get_temp_dir(), 'tsync_');
    if ($tmp === false) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Falha ao criar arquivo temporario']);
        exit;
    }

    file_put_contents($tmp, json_encode($data['tenant_subscription'], JSON_UNESCAPED_UNICODE));
    chmod($tmp, 0644);

    $tableOverride = getenv('TENANT_SUBSCRIPTIONS_TABLE') ?: '';
    $tableEnv = '';
    if ($tableOverride !== '' && preg_match('/^[a-zA-Z0-9_]+$/', $tableOverride) === 1) {
        $tableEnv = 'TENANT_SUBSCRIPTIONS_TABLE=' . escapeshellarg($tableOverride) . ' ';
    }

    $cmd = sprintf(
        '%sTENANT_SUBSCRIPTION_JSON_FILE=%s TENANT_DB_NAME=%s MYSQL_HOST=%s MYSQL_PORT=%s MYSQL_ADMIN_USER=%s MYSQL_ADMIN_PASS=%s php %s 2>&1',
        $tableEnv,
        escapeshellarg($tmp),
        escapeshellarg($dbName),
        escapeshellarg(getenv('MYSQL_HOST') ?: '127.0.0.1'),
        escapeshellarg(getenv('MYSQL_PORT') ?: '3306'),
        escapeshellarg(getenv('MYSQL_ADMIN_USER') ?: 'provisioner'),
        escapeshellarg(getenv('MYSQL_ADMIN_PASS') ?: ''),
        escapeshellarg($bootstrapPhp)
    );

    exec($cmd, $output, $exitCode);
    if (file_exists($tmp)) {
        @unlink($tmp);
    }

    if ($exitCode !== 0) {
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'error' => 'Falha ao atualizar subscriptions no tenant',
            'exit_code' => $exitCode,
            'log' => implode("\n", $output),
        ]);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'message' => 'subscriptions atualizada no tenant',
        'log' => implode("\n", $output),
    ]);
    exit;
}

$required = ['cliente_id', 'requested_subdomain', 'requested_db_name', 'requested_app_path', 'portal_git_repo'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => "Campo ausente: {$field}"]);
        exit;
    }
}

$sub = (string) $data['requested_subdomain'];
$db = (string) $data['requested_db_name'];
$app = (string) $data['requested_app_path'];
$repo = (string) $data['portal_git_repo'];
$ref = (string) ($data['portal_git_ref'] ?? 'main');
$clienteId = (int) $data['cliente_id'];

$tenantEmail = (string) ($data['tenant_admin_email'] ?? '');
$tenantName = (string) ($data['tenant_admin_name'] ?? '');
$tenantHash = (string) ($data['tenant_admin_password_hash'] ?? '');

$tmpSub = '';
if (isset($data['tenant_subscription']) && is_array($data['tenant_subscription'])) {
    $tmpSub = tempnam(sys_get_temp_dir(), 'tsub_');
    if ($tmpSub === false) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Falha ao criar arquivo temporario (subscription)']);
        exit;
    }
    file_put_contents($tmpSub, json_encode($data['tenant_subscription'], JSON_UNESCAPED_UNICODE));
    chmod($tmpSub, 0644);
}

if (! preg_match('/^[a-z0-9][a-z0-9-]{0,62}$/', $sub)) {
    if ($tmpSub !== '') {
        @unlink($tmpSub);
    }
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Subdominio invalido']);
    exit;
}

$callbackUrl = 'https://appdoce.top/provisioning/callback';
$callbackToken = getenv('CALLBACK_TOKEN') ?: '';
if ($callbackToken === '') {
    if ($tmpSub !== '') {
        @unlink($tmpSub);
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'CALLBACK_TOKEN nao configurado']);
    exit;
}

$provisionScript = getenv('PROVISION_TENANT_SCRIPT') ?: '/usr/local/bin/provision-tenant.sh';

$subFileEnv = $tmpSub !== ''
    ? 'TENANT_SUBSCRIPTION_JSON_FILE=' . escapeshellarg($tmpSub)
    : 'TENANT_SUBSCRIPTION_JSON_FILE=' . escapeshellarg('');

$cmd = sprintf(
    'TENANT_ADMIN_EMAIL=%s TENANT_ADMIN_NAME=%s TENANT_ADMIN_PASSWORD_HASH=%s %s MYSQL_HOST=%s MYSQL_PORT=%s MYSQL_ADMIN_USER=%s MYSQL_ADMIN_PASS=%s %s %s %s %s %s %s %d %s %s 2>&1',
    escapeshellarg($tenantEmail),
    escapeshellarg($tenantName),
    escapeshellarg($tenantHash),
    $subFileEnv,
    escapeshellarg(getenv('MYSQL_HOST') ?: '127.0.0.1'),
    escapeshellarg(getenv('MYSQL_PORT') ?: '3306'),
    escapeshellarg(getenv('MYSQL_ADMIN_USER') ?: 'provisioner'),
    escapeshellarg(getenv('MYSQL_ADMIN_PASS') ?: ''),
    escapeshellarg($provisionScript),
    escapeshellarg($sub),
    escapeshellarg($db),
    escapeshellarg($app),
    escapeshellarg($repo),
    escapeshellarg($ref),
    $clienteId,
    escapeshellarg($callbackUrl),
    escapeshellarg($callbackToken)
);

exec($cmd, $output, $exitCode);

if ($tmpSub !== '') {
    @unlink($tmpSub);
}

if ($exitCode !== 0) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Falha no provisionamento',
        'exit_code' => $exitCode,
        'log' => implode("\n", $output),
    ]);
    exit;
}

echo json_encode(['ok' => true, 'message' => 'Provisionamento iniciado/concluido']);
