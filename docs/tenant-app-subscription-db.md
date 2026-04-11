# Assinatura e pagamentos no banco do painel (app tenant)

O painel (`confeiteira-painel`) grava **assinaturas** em `subscriptions` e **pagamentos** (faturas Stripe pagas) em `subscription_payments`. Outro repositório (portal do cliente / tenant) pode usar **o mesmo MySQL** em modo leitura para exibir histórico e status.

## Pré-requisitos

1. Rodar migrations neste projeto: `php spark migrate` (tabela `subscription_payments` e FK para `subscriptions`).
2. No app tenant, configurar credenciais de **somente leitura** (usuário MySQL com `SELECT` nas tabelas necessárias).

## Tabelas relevantes

| Tabela | Uso |
|--------|-----|
| `clientes` | Cliente do SaaS (`email`, `dominio`, `stripe_customer_id`, etc.). |
| `subscriptions` | Uma linha por vínculo plano/cliente; `gateway_subscription_id` = id Stripe `sub_...`. |
| `subscription_payments` | Uma linha por fatura paga; `gateway_invoice_id` = id Stripe `in_...` (único). |
| `plans` | Metadados do plano (`slug`, `nome`, `valor_mensal`). |

## Relacionamento

- `subscriptions.cliente_id` → `clientes.id`
- `subscription_payments.subscription_id` → `subscriptions.id`

## Exemplos SQL (somente leitura)

Assinatura ativa e próxima cobrança pelo **domínio** do tenant:

```sql
SELECT s.id,
       s.status,
       s.gateway_subscription_id,
       s.started_at,
       s.next_billing_at,
       p.slug AS plan_slug,
       p.nome AS plan_nome
FROM clientes c
JOIN subscriptions s ON s.cliente_id = c.id
JOIN plans p ON p.id = s.plan_id
WHERE c.dominio = ?
ORDER BY s.id DESC
LIMIT 1;
```

Pagamentos da assinatura (substitua `?` pelo `subscriptions.id` retornado acima):

```sql
SELECT amount, currency, status, paid_at, period_start, period_end,
       gateway_invoice_id, description
FROM subscription_payments
WHERE subscription_id = ?
ORDER BY paid_at DESC, id DESC;
```

Ou em uma única consulta pelo domínio:

```sql
SELECT sp.amount, sp.currency, sp.paid_at, sp.gateway_invoice_id, sp.description
FROM clientes c
JOIN subscriptions s ON s.cliente_id = c.id
JOIN subscription_payments sp ON sp.subscription_id = s.id
WHERE c.dominio = ?
ORDER BY sp.paid_at DESC, sp.id DESC;
```

## Preenchimento de `subscription_payments`

- **Cadastro com cartão** (`AuthController::paymentConfirm`): após commit de `clientes` + `subscriptions`, o painel lista no Stripe todas as faturas `status=paid` da assinatura e insere linhas idempotentes.
- **Webhook Stripe** (`invoice.paid` / `invoice.payment_succeeded`): grava a fatura recebida se já existir `subscriptions.gateway_subscription_id`.
- **Checkout** (`checkout.session.completed`): após atualizar/inserir a assinatura, o painel sincroniza todas as faturas pagas da assinatura.

Clientes antigos sem linhas precisam de **novo evento** no Stripe ou de um script de backfill consultando a API Stripe e chamando a mesma lógica.

## Segurança

- Não use o usuário root do MySQL no app tenant.
- Limite o usuário de leitura às tabelas acima (e colunas mínimas, se o MySQL permitir views).
