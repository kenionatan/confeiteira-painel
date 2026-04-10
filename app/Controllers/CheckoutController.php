<?php

namespace App\Controllers;

use Stripe\Checkout\Session;
use Stripe\Stripe;

class CheckoutController extends BaseController
{
    public function redirect(string $plan)
    {
        $normalizedPlan = strtolower(trim($plan));
        $subscriptions = config('Subscriptions');
        $checkoutLinks = $subscriptions->checkoutLinks;

        if (! isset($checkoutLinks[$normalizedPlan])) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        if ($subscriptions->gateway === 'stripe' && $normalizedPlan !== 'free') {
            if ($subscriptions->stripeSecretKey === '') {
                return redirect()->to('/')->with('errors', ['Stripe nao configurado. Defina subscriptions.stripeSecretKey no .env.']);
            }

            $priceId = (string) $checkoutLinks[$normalizedPlan];
            if (! str_starts_with($priceId, 'price_')) {
                return redirect()->to('/')->with('errors', ['Price ID do Stripe nao configurado para este plano.']);
            }

            Stripe::setApiKey($subscriptions->stripeSecretKey);

            $successUrl = str_starts_with($subscriptions->stripeSuccessUrl, 'http')
                ? $subscriptions->stripeSuccessUrl
                : base_url(ltrim($subscriptions->stripeSuccessUrl, '/'));
            $cancelUrl = str_starts_with($subscriptions->stripeCancelUrl, 'http')
                ? $subscriptions->stripeCancelUrl
                : base_url(ltrim($subscriptions->stripeCancelUrl, '/'));
            $successJoiner = str_contains($successUrl, '?') ? '&' : '?';

            try {
                $session = Session::create([
                    'mode' => 'subscription',
                    'line_items' => [[
                        'price' => $priceId,
                        'quantity' => 1,
                    ]],
                    'success_url' => $successUrl . $successJoiner . 'session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => $cancelUrl,
                    'metadata' => [
                        'plan' => $normalizedPlan,
                    ],
                ]);
            } catch (\Throwable $e) {
                return redirect()->to('/')->with('errors', ['Falha ao iniciar checkout Stripe. Verifique credenciais e price IDs.']);
            }

            return redirect()->to($session->url);
        }

        $target = $checkoutLinks[$normalizedPlan];

        if (str_starts_with($target, '/')) {
            return redirect()->to(base_url(ltrim($target, '/')));
        }

        return redirect()->to($target);
    }
}
