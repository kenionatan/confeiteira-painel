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
                    'descricao'     => 'Plano gratuito para comecar com cadastro completo da conta.',
                    'destaque'      => false,
                    'features'      => [
                        'Cadastrar dominio no formato: dominio.appdoce.top',
                        'Nome, telefone com WhatsApp, email e senha',
                        'Cadastro de cartao obrigatorio (sem cobranca no Free)',
                        'Acesso inicial para estruturar sua operacao',
                    ],
                ],
                'basico' => [
                    'nome'          => 'Basico',
                    'valor'         => 'R$ 27,90',
                    'valorAnterior' => 'R$ 57,90',
                    'descricao'     => 'Gestao completa para rotina de vendas e clientes.',
                    'destaque'      => false,
                    'features'      => [
                        'Cadastro e historico de clientes',
                        'Pedidos e acompanhamento',
                        'Precificacao e controle de produtos',
                        'Dashboard com indicadores',
                    ],
                ],
                'pro' => [
                    'nome'          => 'Pro',
                    'valor'         => 'R$ 34,90',
                    'valorAnterior' => 'R$ 79,90',
                    'descricao'     => 'Tudo do plano Basico + recursos inteligentes de IA.',
                    'destaque'      => true,
                    'features'      => [
                        'Tudo do Basico',
                        'Cadastro de produto por cupom fiscal (IA)',
                        'Sugestoes inteligentes de cadastro',
                        'Recursos premium futuros inclusos',
                    ],
                ],
            ],
        ]);
    }
}
