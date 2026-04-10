<?php

/**
 * Referência: antes de executar provision-tenant.sh, repasse do JSON do painel:
 *
 *   tenant_admin_email
 *   tenant_admin_name        (opcional)
 *   tenant_admin_password_hash   (bcrypt; mesmo valor de clientes.senha_hash)
 *
 * use putenv() ou passe o array `env` em proc_open(), conforme o seu provisionador.
 * Não grave esse hash em logs.
 */

// putenv('TENANT_ADMIN_EMAIL=' . (string) ($data['tenant_admin_email'] ?? ''));
// putenv('TENANT_ADMIN_NAME=' . (string) ($data['tenant_admin_name'] ?? ''));
// putenv('TENANT_ADMIN_PASSWORD_HASH=' . (string) ($data['tenant_admin_password_hash'] ?? ''));
