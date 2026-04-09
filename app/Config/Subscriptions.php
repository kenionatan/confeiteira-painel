<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Subscriptions extends BaseConfig
{
    public string $gateway = 'mercado_pago';
    public string $mercadoPagoPublicKey = '';

    public string $offerEndsAt = '2026-12-31T23:59:59-03:00';

    /**
     * URLs de checkout hospedado (Mercado Pago).
     * Trocar pelos links reais de producao.
     *
     * @var array<string, string>
     */
    public array $checkoutLinks = [
        'free'   => '/painel/cadastro?plano=free',
        'basico' => 'https://www.mercadopago.com.br/subscriptions/checkout?preapproval_plan_id=PLANO_BASICO',
        'pro'    => 'https://www.mercadopago.com.br/subscriptions/checkout?preapproval_plan_id=PLANO_PRO',
    ];
}
