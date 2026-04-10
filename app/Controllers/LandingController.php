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
                    'descricao'     => 'Precificação, cadastro de produtos e acesso por 7 dias.',
                    'destaque'      => false,
                    'features'      => [
                        'Precificação',
                        'Cadastro de produtos',
                        'Acesso por 7 dias',
                    ],
                ],
                'basico' => [
                    'nome'          => 'Básico',
                    'valor'         => 'R$ 27,90',
                    'valorAnterior' => 'R$ 57,90',
                    'descricao'     => 'Gestão completa para rotina de vendas e clientes.',
                    'destaque'      => false,
                    'features'      => [
                        'Cadastro e histórico de clientes',
                        'Pedidos e acompanhamento',
                        'Precificação e controle de produtos',
                        'Dashboard com indicadores',
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
