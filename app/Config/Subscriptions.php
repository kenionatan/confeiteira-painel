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

    /** Price IDs Stripe (price_...) para assinatura no cadastro integrado. */
    public string $stripePriceBasico = '';
    public string $stripePricePro = '';

    /**
     * Destinos do botao Assinar na landing (/assinar/:plano).
     * Planos pagos apontam para o cadastro no painel com o mesmo fluxo do Free + cobranca Stripe.
     *
     * @var array<string, string>
     */
    public array $checkoutLinks = [
        'free'   => '/painel/cadastro?plano=free',
        'basico' => '/painel/cadastro?plano=basico',
        'pro'    => '/painel/cadastro?plano=pro',
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

        $this->stripePriceBasico = (string) env('subscriptions.stripePriceBasico', $this->stripePriceBasico);
        $this->stripePricePro = (string) env('subscriptions.stripePricePro', $this->stripePricePro);

        $this->checkoutLinks = [
            'free'   => '/painel/cadastro?plano=free',
            'basico' => '/painel/cadastro?plano=basico',
            'pro'    => '/painel/cadastro?plano=pro',
        ];
    }

    public function stripePriceIdForPlanSlug(string $slug): string
    {
        return match ($slug) {
            'basico' => $this->stripePriceBasico,
            'pro'    => $this->stripePricePro,
            default  => '',
        };
    }
}
