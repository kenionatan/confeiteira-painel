# Wildcard Provisioning (Opcao 1, custo enxuto)

Este documento descreve como automatizar com custo baixo:

1. provisionamento do tenant apos assinatura;
2. criacao de banco dedicado por cliente;
3. atualizacao de status no painel via callback;
4. clone do projeto do portal em `/var/www/html/{subdominio}` com `.env` apontando para o novo banco.

## 1) O que ja foi preparado no app

- tabela `tenant_provision_jobs` (fila de provisionamento);
- novos campos em `clientes`:
  - `tenant_status` (`pending|provisioning|ready|failed`)
  - `tenant_db_name`, `tenant_db_user`, `tenant_ready_at`, `tenant_error_message`;
- callback seguro: `POST /provisioning/callback`;
- comando para reenvio manual: `php spark tenants:dispatch-pending`.

## 2) Variaveis .env necessarias

Adicione no `.env` da aplicacao:

```ini
provisioning.centralHost = appdoce.top
provisioning.tenantRootDomain = appdoce.top
provisioning.dispatchUrl = https://SEU_ENDPOINT_DE_PROVISIONAMENTO/provision-tenant
provisioning.dispatchToken = TOKEN_BEARER_ENVIADO_AO_PROVISIONADOR
provisioning.callbackToken = TOKEN_BEARER_VALIDADO_NO_CALLBACK
provisioning.portalGitRepo = https://github.com/sua-org/seu-portal-cliente.git
provisioning.portalGitRef = main
provisioning.portalBasePath = /var/www/html
# Timeout do POST do painel para o provisionador (segundos; clone + migrate pode levar vários minutos)
# provisioning.dispatchTimeout = 300
```

## 3) DNS wildcard

No provedor de DNS (Superdominios ou Route53):

- crie `A` ou `CNAME` wildcard:
  - `*.appdoce.top` -> load balancer ou servidor da aplicacao tenant;
- mantenha `portal.appdoce.top` apontando para o painel/admin.

## 4) SSL wildcard

No AWS Certificate Manager (ACM):

- emita certificado para:
  - `*.appdoce.top`
  - `appdoce.top` (opcional);
- valide por DNS;
- anexe ao ALB/CloudFront que atende tenants.

## 5) Arquiteturas recomendadas

### A) Custo minimo (sem RDS) - recomendado agora

- painel atual (este projeto) continua como esta;
- um endpoint de provisionamento (pode ser no mesmo servidor, via script HTTP);
- MySQL local/EC2 (nao RDS) para criar DB por tenant;
- clone do repo do portal por tenant em `/var/www/html/{subdominio}`;
- callback para `POST /provisioning/callback`.

### B) AWS gerenciada (mais cara, mais gerenciada)

- **API Gateway**: endpoint de entrada para provisioning;
- **Lambda (ProvisionTenant)**: processa payload do app;
- **RDS MySQL** (ou Aurora MySQL): cria schema/usuario por tenant;
- **Secrets Manager**: guarda credenciais mestre do RDS e (opcional) credenciais dos tenants;
- **CloudWatch Logs**: rastreabilidade;
- (Opcional) **SQS** entre API Gateway e Lambda para desacoplar.

## 6) Fluxo ponta-a-ponta

1. app recebe assinatura confirmada e cria job `pending`;
2. app envia payload para `provisioning.dispatchUrl`;
3. provisionador valida bearer token;
4. provisionador cria DB (nome = subdominio);
5. provisionador cria pasta `/var/www/html/{subdominio}` e clona o repo do portal;
6. provisionador escreve `.env` do portal apontando para o DB novo;
7. provisionador roda migrations do portal;
8. provisionador chama callback `POST /provisioning/callback` com `Authorization: Bearer provisioning.callbackToken`;
9. app marca `tenant_status=ready` (ou `failed`) e salva metadados.

Se o callback vier sem `db_name` / `db_user`, o painel preenche `tenant_db_name` com `requested_db_name` do job e `tenant_db_user` com o subdomínio derivado de `requested_host`.

## 7) Contrato do payload (app -> AWS)

Exemplo de corpo enviado pelo app:

```json
{
  "job_id": 12,
  "cliente_id": 34,
  "requested_host": "kenio.appdoce.top",
  "requested_subdomain": "kenio",
  "requested_db_name": "kenio",
  "requested_app_path": "/var/www/html/kenio",
  "portal_git_repo": "https://github.com/sua-org/seu-portal-cliente.git",
  "portal_git_ref": "main",
  "tenant_admin_email": "kenio@appdoce.top",
  "tenant_admin_name": "Kenio",
  "tenant_admin_password_hash": "$2y$10$........................................",
  "cliente": {
    "nome": "Kenio",
    "email": "kenio@appdoce.top",
    "whatsapp": "82999999999",
    "dominio": "kenio.appdoce.top"
  }
}
```

`tenant_admin_password_hash` é o **bcrypt** já salvo em `clientes.senha_hash` no cadastro (mesma senha que o cliente digitou). O provisionador deve repassar isso ao script (por exemplo via variáveis de ambiente `TENANT_ADMIN_EMAIL`, `TENANT_ADMIN_NAME`, `TENANT_ADMIN_PASSWORD_HASH`) para, após `php spark migrate`, atualizar o primeiro usuário da tabela `users` do portal. Não envie senha em texto plano.

## 8) Contrato do callback (AWS -> app)

### Sucesso

```json
{
  "cliente_id": 34,
  "status": "ready",
  "db_name": "kenio",
  "db_user": "kenio"
}
```

### Falha

```json
{
  "cliente_id": 34,
  "status": "failed",
  "error_message": "Erro ao criar usuario no RDS"
}
```

Status aceitos no callback: `provisioning`, `ready`, `failed`.

## 9) Provisionador local (pseudo passo-a-passo)

1. validar `Authorization: Bearer <dispatchToken>`;
2. validar `requested_subdomain` (regex segura);
3. criar DB: `CREATE DATABASE \`<subdominio>\``;
4. (opcional) criar usuario DB `<subdominio>` e grants;
5. criar pasta `/var/www/html/<subdominio>`;
6. `git clone --branch <ref> <repo> /var/www/html/<subdominio>` (ou `git pull` se existir);
7. gerar `.env` do portal com credenciais do DB recem criado;
8. rodar migrations do portal (ex.: `sudo -u www-data php spark migrate` quando o chamador for root);
9. (opcional) aplicar `tenant_admin_*` no banco do tenant para o login do portal coincidir com o cadastro;
10. callback para `/provisioning/callback` com `status=ready` (use `curl -f` para falhar o script se o painel responder 4xx/5xx);
11. em erro, callback `status=failed`.

Se o callback falhar em silêncio, o painel ainda grava `tenant_db_name` ao receber HTTP 2xx do provisionador (provisionamento síncrono).

### Exemplo minimo de `.env` do portal (gerado)

```ini
CI_ENVIRONMENT = production
app.baseURL = https://kenio.appdoce.top/
database.default.hostname = 127.0.0.1
database.default.database = kenio
database.default.username = kenio
database.default.password = SENHA_GERADA
database.default.DBDriver = MySQLi
database.default.port = 3306
```

## 10) Boas praticas

- idempotencia: se DB ja existir, tratar como sucesso controlado;
- logs com `cliente_id` e `job_id`;
- timeout de provisionamento >= 60s;
- retries com fila/cron para falhas temporarias;
- nunca retornar senha de DB no callback;
- limitar permissoes IAM ao minimo necessario.

## 11) Operacao manual e reprocessamento

- Para reenviar pendencias:

```bash
php spark tenants:dispatch-pending
```

- Para processar em lote maior:

```bash
php spark tenants:dispatch-pending 100
```

- Se o tenant ja foi criado mas `subscriptions` no portal ficou no seed `free`, reenvie só o plano (exige `tenant_db_name` no cliente e handler no provisionador):

```bash
php spark tenants:push-subscription <cliente_id>
```

O painel envia `action: sync_subscription_only` para `provisioning.dispatchUrl`. O `docs/provisioner-index.example.php` trata isso com `MYSQL_ADMIN_*` e `docs/sync-subscription-bootstrap.php`.

Se o portal usar **prefixo de tabela** no MySQL (ex. `app_subscriptions`), defina no ambiente do provisionador `TENANT_SUBSCRIPTIONS_TABLE=app_subscriptions` ou atualize o script `sync-subscription-bootstrap.php` no servidor (ele tenta detectar tabelas que terminam em `subscriptions`).

## 12) Observacoes importantes para seu cenario

- Com wildcard DNS, qualquer subdominio resolve no DNS, mas so os cadastrados devem funcionar na app.
- Nome do banco e da pasta = subdominio (ja preparado no payload como `requested_subdomain` e `requested_db_name`).
- Se voce usar MySQL local, proteja bem o usuario master e rode provisionador com usuario restrito.

