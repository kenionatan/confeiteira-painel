<?php

namespace App\Controllers;

use App\Models\ClienteModel;
use App\Models\PlanModel;
use App\Models\SubscriptionModel;
use App\Services\StripeInvoicePaymentRecorder;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeWebhookController extends BaseController
{
    /**
     * POST /webhooks/stripe — eventos Stripe (assinatura + faturas mensais).
     * Configure o endpoint no Stripe Dashboard e subscriptions.stripeWebhookSecret no .env.
     */
    public function stripe()
    {
        $cfg = config('Subscriptions');
        if ($cfg->stripeWebhookSecret === '' || $cfg->stripeSecretKey === '') {
            return $this->response->setStatusCode(503)->setBody('Webhook não configurado.');
        }

        $payload = file_get_contents('php://input');
        if ($payload === false || $payload === '') {
            return $this->response->setStatusCode(400)->setBody('Corpo vazio.');
        }

        $sigHeader = $this->request->getHeaderLine('Stripe-Signature');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $cfg->stripeWebhookSecret);
        } catch (\Throwable) {
            return $this->response->setStatusCode(400)->setBody('Assinatura inválida.');
        }

        $stripe = new StripeClient($cfg->stripeSecretKey);

        match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutSessionCompleted($stripe, $event->data->object),
            'invoice.paid', 'invoice.payment_succeeded' => $this->handleInvoicePaid($event->data->object),
            default => null,
        };

        return $this->response->setJSON(['received' => true]);
    }

    private function handleCheckoutSessionCompleted(StripeClient $stripe, object $session): void
    {
        if (($session->mode ?? '') !== 'subscription') {
            return;
        }

        $customerId = is_string($session->customer ?? null) ? $session->customer : null;
        $subscriptionId = is_string($session->subscription ?? null) ? $session->subscription : null;
        if ($customerId === null || $subscriptionId === null) {
            return;
        }

        $meta = $session->metadata ?? null;
        $planSlug = null;
        if (is_object($meta) && isset($meta->plan)) {
            $planSlug = (string) $meta->plan;
        } elseif (is_array($meta) && isset($meta['plan'])) {
            $planSlug = (string) $meta['plan'];
        }
        $planSlug = strtolower(trim((string) $planSlug));
        if ($planSlug === '') {
            return;
        }

        $customer = $stripe->customers->retrieve($customerId);
        $email = isset($customer->email) ? strtolower(trim((string) $customer->email)) : '';

        $clienteModel = new ClienteModel();
        $cliente = $clienteModel->where('stripe_customer_id', $customerId)->first();
        if (! $cliente && $email !== '') {
            $cliente = $clienteModel->where('email', $email)->first();
            if ($cliente) {
                $clienteModel->update($cliente['id'], ['stripe_customer_id' => $customerId]);
                $cliente['stripe_customer_id'] = $customerId;
            }
        }

        $plan = (new PlanModel())->where('slug', $planSlug)->first();
        if (! $cliente || ! $plan) {
            return;
        }

        $subObj = $stripe->subscriptions->retrieve($subscriptionId);
        $start = isset($subObj->current_period_start) ? (int) $subObj->current_period_start : null;
        $end = isset($subObj->current_period_end) ? (int) $subObj->current_period_end : null;

        $subscriptionModel = new SubscriptionModel();
        $row = $subscriptionModel->where('cliente_id', $cliente['id'])->orderBy('id', 'DESC')->first();

        $data = [
            'plan_id'                 => $plan['id'],
            'status'                  => 'active',
            'gateway'                 => 'stripe',
            'gateway_subscription_id' => $subscriptionId,
            'started_at'              => $start ? date('Y-m-d H:i:s', $start) : date('Y-m-d H:i:s'),
            'next_billing_at'         => $end ? date('Y-m-d H:i:s', $end) : null,
            'ends_at'                 => null,
        ];

        if ($row) {
            $subscriptionModel->update($row['id'], $data);
        } else {
            $data['cliente_id'] = $cliente['id'];
            $subscriptionModel->insert($data);
        }
    }

    private function handleInvoicePaid(object $invoice): void
    {
        $stripeSubId = $invoice->subscription ?? null;
        if (! is_string($stripeSubId) || $stripeSubId === '') {
            return;
        }

        $local = (new SubscriptionModel())->where('gateway_subscription_id', $stripeSubId)->first();
        if (! $local) {
            return;
        }

        StripeInvoicePaymentRecorder::recordIfNew($invoice, (int) $local['id']);
    }
}
