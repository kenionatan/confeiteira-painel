<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Stripe\StripeClient;

/**
 * Persiste pagamentos de assinatura (faturas Stripe) em subscription_payments.
 * Usa Query Builder para evitar estado compartilhado do Model e falhas silenciosas.
 */
class StripeInvoicePaymentRecorder
{
    /**
     * Grava linha em subscription_payments a partir de uma fatura Stripe (idempotente por gateway_invoice_id).
     */
    public static function recordIfNew(object $invoice, int $localSubscriptionId): void
    {
        $invoiceId = self::stripeInvoiceId($invoice);
        if ($invoiceId === null) {
            log_message('debug', 'subscription_payments: objeto de fatura sem id in_*');

            return;
        }

        $db = \Config\Database::connect();
        if (self::paymentExists($db, $invoiceId)) {
            return;
        }

        $amountPaid = (int) ($invoice->amount_paid ?? 0);
        $amount       = self::formatAmountFromCents($amountPaid);

        [$periodStart, $periodEnd] = self::invoicePeriodBounds($invoice);

        if (isset($invoice->status_transitions->paid_at) && $invoice->status_transitions->paid_at) {
            $paidAt = date('Y-m-d H:i:s', (int) $invoice->status_transitions->paid_at);
        } else {
            $paidAt = date('Y-m-d H:i:s');
        }

        $desc = (string) ($invoice->description ?? '');
        if ($desc === '' && isset($invoice->billing_reason)) {
            $desc = (string) $invoice->billing_reason;
        }

        $row = [
            'subscription_id'    => $localSubscriptionId,
            'amount'             => $amount,
            'currency'           => strtoupper((string) ($invoice->currency ?? 'brl')),
            'status'             => 'paid',
            'period_start'       => $periodStart,
            'period_end'         => $periodEnd,
            'gateway'            => 'stripe',
            'gateway_invoice_id' => $invoiceId,
            'description'        => $desc !== '' ? substr($desc, 0, 500) : null,
            'paid_at'            => $paidAt,
            'created_at'         => date('Y-m-d H:i:s'),
            'updated_at'         => date('Y-m-d H:i:s'),
        ];

        try {
            $db->table('subscription_payments')->insert($row);
        } catch (\Throwable $e) {
            if (self::isDuplicateKeyError($e)) {
                return;
            }
            log_message('error', 'subscription_payments insert falhou: ' . $e->getMessage());
        }
    }

    /**
     * Grava todas as faturas pagas encontradas para a assinatura Stripe (idempotente).
     */
    public static function recordAllPaidInvoicesForSubscription(StripeClient $stripe, string $stripeSubscriptionId, int $localSubscriptionId): void
    {
        $invoices = self::fetchPaidInvoicesForSubscription($stripe, $stripeSubscriptionId);
        if ($invoices === []) {
            log_message(
                'warning',
                'subscription_payments: nenhuma fatura com status=paid encontrada no Stripe para assinatura '
                . $stripeSubscriptionId . ' (subscription local id=' . $localSubscriptionId . ').'
            );

            return;
        }

        foreach ($invoices as $invoice) {
            if (is_object($invoice)) {
                self::recordIfNew($invoice, $localSubscriptionId);
            }
        }
    }

    /**
     * @deprecated alias de recordAllPaidInvoicesForSubscription
     */
    public static function recordLatestPaidInvoiceForSubscription(StripeClient $stripe, string $stripeSubscriptionId, int $localSubscriptionId): void
    {
        self::recordAllPaidInvoicesForSubscription($stripe, $stripeSubscriptionId, $localSubscriptionId);
    }

    /**
     * @return list<object>
     */
    public static function fetchPaidInvoicesForSubscription(StripeClient $stripe, string $stripeSubscriptionId): array
    {
        $byId = [];

        $merge = static function (array $list) use (&$byId): void {
            foreach ($list as $inv) {
                if (! is_object($inv)) {
                    continue;
                }
                $id = self::stripeInvoiceId($inv);
                if ($id === null) {
                    continue;
                }
                if (($inv->status ?? '') !== 'paid') {
                    continue;
                }
                $byId[$id] = $inv;
            }
        };

        try {
            $merge(self::listInvoiceObjects($stripe, [
                'subscription' => $stripeSubscriptionId,
                'status'         => 'paid',
                'limit'          => 100,
            ]));
        } catch (\Throwable $e) {
            log_message('error', 'subscription_payments Stripe invoices.list (paid): ' . $e->getMessage());
        }

        if ($byId === []) {
            try {
                $candidates = self::listInvoiceObjects($stripe, [
                    'subscription' => $stripeSubscriptionId,
                    'limit'          => 25,
                ]);
                foreach ($candidates as $inv) {
                    if (is_object($inv) && ($inv->status ?? '') === 'paid') {
                        $id = self::stripeInvoiceId($inv);
                        if ($id !== null) {
                            $byId[$id] = $inv;
                        }
                    }
                }
            } catch (\Throwable $e) {
                log_message('error', 'subscription_payments Stripe invoices.list (sem status): ' . $e->getMessage());
            }
        }

        if ($byId === []) {
            try {
                $sub = $stripe->subscriptions->retrieve($stripeSubscriptionId, ['expand' => ['latest_invoice']]);
                $li  = $sub->latest_invoice ?? null;
                if (is_string($li) && $li !== '') {
                    $li = $stripe->invoices->retrieve($li, ['expand' => ['lines.data']]);
                }
                if (is_object($li) && ($li->status ?? '') === 'paid') {
                    $id = self::stripeInvoiceId($li);
                    if ($id !== null) {
                        $byId[$id] = $li;
                    }
                }
            } catch (\Throwable $e) {
                log_message('error', 'subscription_payments Stripe subscription.latest_invoice: ' . $e->getMessage());
            }
        }

        return array_values($byId);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return list<object>
     */
    private static function listInvoiceObjects(StripeClient $stripe, array $params): array
    {
        $out   = [];
        $page  = $stripe->invoices->all($params);

        while (true) {
            $chunk = $page->data ?? [];
            if ($chunk === []) {
                break;
            }
            foreach ($chunk as $row) {
                if (is_object($row)) {
                    $out[] = $row;
                }
            }
            if (! ($page->has_more ?? false)) {
                break;
            }
            $last = $page->last();
            if ($last === null || ! is_object($last) || empty($last->id)) {
                break;
            }
            $params['starting_after'] = $last->id;
            $page                     = $stripe->invoices->all($params);
        }

        return $out;
    }

    private static function stripeInvoiceId(object $invoice): ?string
    {
        $raw = $invoice->id ?? null;
        if (is_string($raw) && str_starts_with($raw, 'in_')) {
            return $raw;
        }

        return null;
    }

    private static function paymentExists(BaseConnection $db, string $gatewayInvoiceId): bool
    {
        return $db->table('subscription_payments')
            ->where('gateway_invoice_id', $gatewayInvoiceId)
            ->countAllResults() > 0;
    }

    private static function formatAmountFromCents(int $cents): string
    {
        return number_format(round(max(0, $cents) / 100, 2), 2, '.', '');
    }

    /**
     * @return array{0: ?string, 1: ?string} [period_start, period_end] as SQL datetime or null
     */
    private static function invoicePeriodBounds(object $invoice): array
    {
        $periodStart = null;
        $periodEnd   = null;

        $lines = $invoice->lines->data ?? [];
        if ($lines !== [] && isset($lines[0]->period)) {
            $p = $lines[0]->period;
            if (isset($p->start)) {
                $periodStart = date('Y-m-d H:i:s', (int) $p->start);
            }
            if (isset($p->end)) {
                $periodEnd = date('Y-m-d H:i:s', (int) $p->end);
            }
        }

        if ($periodStart === null && isset($invoice->period_start) && (int) $invoice->period_start > 0) {
            $periodStart = date('Y-m-d H:i:s', (int) $invoice->period_start);
        }
        if ($periodEnd === null && isset($invoice->period_end) && (int) $invoice->period_end > 0) {
            $periodEnd = date('Y-m-d H:i:s', (int) $invoice->period_end);
        }

        return [$periodStart, $periodEnd];
    }

    private static function isDuplicateKeyError(\Throwable $e): bool
    {
        $code = (string) $e->getCode();
        $msg  = $e->getMessage();

        return str_contains($msg, 'Duplicate')
            || str_contains($msg, '1062')
            || str_contains($msg, 'UNIQUE constraint')
            || $code === '1062';
    }
}
