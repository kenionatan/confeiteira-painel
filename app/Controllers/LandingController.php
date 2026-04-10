<?php

namespace App\Controllers;

class LandingController extends BaseController
{
    public function index(): string
    {
        $subscriptions = config('Subscriptions');

        return view('landing/index', [
            'title'       => 'Confeiteira Pro',
            'offerEndsAt' => $subscriptions->offerEndsAt,
            'plans'       => [
                'free' => [
                    'nome'          => 'Free',
                    'valor'         => 'R$ 0,00',
                    'valorAnterior' => '',
                    'descricao'     => 'Plano gratuito para começar com cadastro completo da conta.',
                    'destaque'      => false,
                    'features'      => [
                        'Cadastrar domínio no formato: dominio.appdoce.top',
                        'Nome, telefone com WhatsApp, e-mail e senha',
                        'Cadastro de cartão obrigatório (sem cobrança no Free)',
                        'Acesso inicial para estruturar sua operação',
                    ],
                ],
                'basico' => [
                    'nome'          => 'Básico',
                    'valor'         => 'R$ 27,90',
                    'valorAnterior' => 'R$ 57,90',
                    'descricao'     => 'Precificação, cadastro de produtos e acesso por 7 dias.',
                    'destaque'      => false,
                    'features'      => [
                        'Precificação',
                        'Cadastro de produtos',
                        'Acesso por 7 dias',
                    ],
                ],
                'pro' => [
                    'nome'          => 'Pro',
                    'valor'         => 'R$ 34,90',
                    'valorAnterior' => 'R$ 79,90',
                    'descricao'     => 'Tudo do plano Básico + recursos inteligentes de IA.',
                    'destaque'      => true,
                    'features'      => [
                        'Tudo do Básico',
                        'Cadastro de produto por cupom fiscal (IA)',
                        'Sugestões inteligentes de cadastro',
                        'Recursos premium futuros incluídos',
                    ],
                ],
            ],
        ]);
    }
}
