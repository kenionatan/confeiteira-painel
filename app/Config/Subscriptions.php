<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Subscriptions extends BaseConfig
{
    public string $gateway = 'stripe';
    public string $mercadoPagoPublicKey = '';
    public string $stripePublicKey = '';
    public string $stripeSecretKey = '';
    public string $stripeWebhookSecret = '';
    public string $stripeSuccessUrl = '/painel/login?stripe=success';
    public string $stripeCancelUrl = '/?stripe=cancel';

    public string $offerEndsAt = '2026-12-31T23:59:59-03:00';

    /**
     * URLs de checkout hospedado (Mercado Pago).
     * Trocar pelos links reais de producao.
     *
     * @var array<string, string>
     */
    public array $checkoutLinks = [
        'free'   => '/painel/cadastro?plano=free',
        'basico' => 'price_basico_placeholder',
        'pro'    => 'price_pro_placeholder',
    ];

    public function __construct()
    {
        parent::__construct();

        $this->gateway = (string) env('subscriptions.gateway', $this->gateway);
        $this->mercadoPagoPublicKey = (string) env('subscriptions.mercadoPagoPublicKey', $this->mercadoPagoPublicKey);
        $this->stripePublicKey = (string) env('subscriptions.stripePublicKey', $this->stripePublicKey);
        $this->stripeSecretKey = (string) env('subscriptions.stripeSecretKey', $this->stripeSecretKey);
        $this->stripeWebhookSecret = (string) env('subscriptions.stripeWebhookSecret', $this->stripeWebhookSecret);
        $this->stripeSuccessUrl = (string) env('subscriptions.stripeSuccessUrl', $this->stripeSuccessUrl);
        $this->stripeCancelUrl = (string) env('subscriptions.stripeCancelUrl', $this->stripeCancelUrl);

        $this->checkoutLinks = [
            'free'   => '/painel/cadastro?plano=free',
            'basico' => (string) env('subscriptions.stripePriceBasico', $this->checkoutLinks['basico']),
            'pro'    => (string) env('subscriptions.stripePricePro', $this->checkoutLinks['pro']),
        ];
    }
}
