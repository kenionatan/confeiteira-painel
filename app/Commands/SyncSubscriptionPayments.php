<?php

namespace App\Commands;

use App\Models\SubscriptionModel;
use App\Services\StripeInvoicePaymentRecorder;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Stripe\StripeClient;
use Throwable;

/**
 * Sincroniza faturas pagas do Stripe para subscription_payments (backfill / diagnóstico).
 */
class SyncSubscriptionPayments extends BaseCommand
{
    protected $group       = 'Custom';
    protected $name        = 'subscriptions:sync-payments';
    protected $description = 'Busca faturas pagas no Stripe e grava em subscription_payments (por id local ou todas).';
    protected $usage       = 'subscriptions:sync-payments [subscription_id]';
    protected $arguments   = [
        'subscription_id' => 'ID da linha em subscriptions (omitir = todas com gateway stripe)',
    ];

    public function run(array $params)
    {
        $cfg = config('Subscriptions');
        if ($cfg->stripeSecretKey === '') {
            CLI::error('Defina subscriptions.stripeSecretKey / STRIPE no .env.');

            return;
        }

        $stripe = new StripeClient($cfg->stripeSecretKey);
        $model  = new SubscriptionModel();

        $filterId = isset($params[0]) ? (int) $params[0] : 0;

        if ($filterId > 0) {
            $rows = $model->where('id', $filterId)->where('gateway', 'stripe')->findAll();
        } else {
            $rows = $model->where('gateway', 'stripe')->orderBy('id', 'DESC')->findAll(500);
            $rows = array_values(array_filter(
                $rows,
                static fn (array $r): bool => isset($r['gateway_subscription_id'])
                    && is_string($r['gateway_subscription_id'])
                    && str_starts_with(trim($r['gateway_subscription_id']), 'sub_')
            ));
        }

        if ($rows === []) {
            CLI::write('Nenhuma assinatura Stripe encontrada.', 'yellow');

            return;
        }

        $ok = 0;
        foreach ($rows as $row) {
            $localId = (int) $row['id'];
            $stripeSubId = trim((string) ($row['gateway_subscription_id'] ?? ''));
            if ($stripeSubId === '' || ! str_starts_with($stripeSubId, 'sub_')) {
                CLI::write("Pular local id={$localId}: gateway_subscription_id inválido.", 'yellow');
                continue;
            }

            try {
                StripeInvoicePaymentRecorder::recordAllPaidInvoicesForSubscription($stripe, $stripeSubId, $localId);
                CLI::write("Sincronizado subscription local id={$localId} stripe={$stripeSubId}", 'green');
                $ok++;
            } catch (Throwable $e) {
                CLI::error("Falha id={$localId}: " . $e->getMessage());
            }
        }

        CLI::write("Processadas {$ok} assinatura(s). Verifique a tabela subscription_payments e writable/logs.", 'cyan');
    }
}
