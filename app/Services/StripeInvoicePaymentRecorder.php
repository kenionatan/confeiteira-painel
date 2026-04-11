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
            $ok = $paymentModel->insert([
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
            if ($ok === false) {
                log_message('error', 'subscription_payments insert falhou (modelo): ' . json_encode($paymentModel->errors()));
            }
        } catch (DatabaseException $e) {
            if (self::isDuplicateKeyException($e)) {
                return;
            }
            log_message('error', 'subscription_payments insert DB: ' . $e->getMessage());
        }
    }

    /**
     * Grava todas as faturas já pagas da assinatura Stripe (idempotente).
     * Preferível a só "latest_invoice": o status da última fatura pode não ser "paid" ainda ou a API pode devolver só o id.
     */
    public static function recordAllPaidInvoicesForSubscription(StripeClient $stripe, string $stripeSubscriptionId, int $localSubscriptionId): void
    {
        $params = [
            'subscription' => $stripeSubscriptionId,
            'status'       => 'paid',
            'limit'        => 100,
        ];

        $collection = $stripe->invoices->all($params);
        foreach ($collection->autoPagingIterator() as $invoice) {
            if (is_object($invoice)) {
                self::recordIfNew($invoice, $localSubscriptionId);
            }
        }
    }

    /**
     * @deprecated Use recordAllPaidInvoicesForSubscription; mantido como alias.
     */
    public static function recordLatestPaidInvoiceForSubscription(StripeClient $stripe, string $stripeSubscriptionId, int $localSubscriptionId): void
    {
        self::recordAllPaidInvoicesForSubscription($stripe, $stripeSubscriptionId, $localSubscriptionId);
    }

    private static function isDuplicateKeyException(DatabaseException $e): bool
    {
        $code = (string) $e->getCode();
        $msg  = $e->getMessage();

        return str_contains($msg, 'Duplicate')
            || str_contains($msg, '1062')
            || str_contains($msg, 'UNIQUE constraint')
            || $code === '1062';
    }
}
