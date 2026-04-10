<?php
/**
 * Exemplo de endpoint do provisionador (ex.: /var/www/provisioner/public/index.php).
 * Copie para o servidor como index.php e ajuste caminho de provision-tenant.sh.
 *
 * O painel envia JSON com tenant_subscription (objeto). Este script repassa
 * TENANT_SUBSCRIPTION_B64 (base64 do JSON) ao shell para evitar problemas de escape.
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

$tenantSubB64 = '';
if (isset($data['tenant_subscription']) && is_array($data['tenant_subscription'])) {
    $tenantSubB64 = base64_encode(json_encode($data['tenant_subscription'], JSON_UNESCAPED_UNICODE));
}

if (! preg_match('/^[a-z0-9][a-z0-9-]{0,62}$/', $sub)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Subdominio invalido']);
    exit;
}

$callbackUrl = 'https://appdoce.top/provisioning/callback';
$callbackToken = getenv('CALLBACK_TOKEN') ?: '';
if ($callbackToken === '') {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'CALLBACK_TOKEN nao configurado']);
    exit;
}

$provisionScript = getenv('PROVISION_TENANT_SCRIPT') ?: '/usr/local/bin/provision-tenant.sh';

$cmd = sprintf(
    'TENANT_ADMIN_EMAIL=%s TENANT_ADMIN_NAME=%s TENANT_ADMIN_PASSWORD_HASH=%s TENANT_SUBSCRIPTION_B64=%s MYSQL_HOST=%s MYSQL_PORT=%s MYSQL_ADMIN_USER=%s MYSQL_ADMIN_PASS=%s %s %s %s %s %s %s %d %s %s 2>&1',
    escapeshellarg($tenantEmail),
    escapeshellarg($tenantName),
    escapeshellarg($tenantHash),
    escapeshellarg($tenantSubB64),
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
