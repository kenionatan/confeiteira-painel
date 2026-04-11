<?php

namespace App\Services;

use App\Models\SubscriptionPaymentModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use Stripe\StripeClient;

class StripeInvoicePaymentRecorder
{
    /**
     * Grava linha em subscription_payments a partir de uma fatura Stripe (idempotente por gateway_invoice_id).
     */
    public static function recordIfNew(object $invoice, int $localSubscriptionId): void
    {
        $invoiceId = $invoice->id ?? null;
        if (! is_string($invoiceId) || $invoiceId === '') {
            return;
        }

        $paymentModel = new SubscriptionPaymentModel();
        if ($paymentModel->where('gateway_invoice_id', $invoiceId)->first()) {
            return;
        }

        $amountPaid = (int) ($invoice->amount_paid ?? 0);
        $amount = bcdiv((string) $amountPaid, '100', 2);

        $periodStart = null;
        $periodEnd = null;
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

        if (isset($invoice->status_transitions->paid_at) && $invoice->status_transitions->paid_at) {
            $paidAt = date('Y-m-d H:i:s', (int) $invoice->status_transitions->paid_at);
        } else {
            $paidAt = date('Y-m-d H:i:s');
        }

        $desc = (string) ($invoice->description ?? '');
        if ($desc === '' && isset($invoice->billing_reason)) {
            $desc = (string) $invoice->billing_reason;
        }

        try {
            $paymentModel->insert([
                'subscription_id'    => $localSubscriptionId,
                'amount'               => $amount,
                'currency'             => strtoupper((string) ($invoice->currency ?? 'brl')),
                'status'               => 'paid',
                'period_start'         => $periodStart,
                'period_end'           => $periodEnd,
                'gateway'              => 'stripe',
                'gateway_invoice_id'   => $invoiceId,
                'description'          => $desc !== '' ? substr($desc, 0, 500) : null,
                'paid_at'              => $paidAt,
            ]);
        } catch (DatabaseException) {
            // idempotência / UNIQUE gateway_invoice_id
        }
    }

    /**
     * Busca a última fatura paga da assinatura e grava em subscription_payments (cadastro integrado sem webhook ainda).
     */
    public static function recordLatestPaidInvoiceForSubscription(StripeClient $stripe, string $stripeSubscriptionId, int $localSubscriptionId): void
    {
        $sub = $stripe->subscriptions->retrieve($stripeSubscriptionId, ['expand' => ['latest_invoice']]);
        $inv = $sub->latest_invoice ?? null;
        if ($inv === null) {
            return;
        }
        if (is_string($inv) && $inv !== '') {
            $inv = $stripe->invoices->retrieve($inv, ['expand' => ['lines']]);
        }
        if (! is_object($inv)) {
            return;
        }
        if (($inv->status ?? '') !== 'paid') {
            return;
        }

        self::recordIfNew($inv, $localSubscriptionId);
    }
}
