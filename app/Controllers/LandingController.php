<?php

namespace App\Controllers;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

class LandingController extends BaseController
{
    public function index(): string
    {
        $tz = new DateTimeZone('America/Sao_Paulo');
        $offerEndsAt = (new DateTimeImmutable('now', $tz))
            ->modify('+90 minutes')
            ->format(DateTimeInterface::ATOM);

        return view('landing/index', [
            'title'                  => 'Confeiteira Pro',
            'offerEndsAt'            => $offerEndsAt,
            'socialProofFakeClients' => $this->socialProofFakeClients(),
            'plans'                  => [
                'free' => [
                    'nome'          => 'Free',
                    'valor'         => 'R$ 0,00',
                    'valorAnterior' => '',
                    'descricao'     => 'Precificação, cadastro de produtos e mais.',
                    'destaque'      => false,
                    'features'      => [
                        'Precificação',
                        'Cadastro de produtos',
                        'Mais',
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
                        'Metas de faturamento',
                        'Cadastro de produtos por cupom fiscal (IA)',
                    ],
                ],
                'pro' => [
                    'nome'          => 'Pro',
                    'valor'         => 'R$ 34,90',
                    'valorAnterior' => 'R$ 79,90',
                    'descricao'     => 'Tudo do plano Básico + recursos inteligentes de IA.',
                    'destaque'      => true,
                    'features'      => [
                        'Cadastro e histórico de clientes',
                        'Pedidos e acompanhamento',
                        'Precificação e controle de produtos',
                        'Dashboard com indicadores',
                        'Metas de faturamento',
                        'Cadastro de produtos por cupom fiscal (IA)',
                        'Cardápio e promoções',
                        'Ambiente personalizado para clientes',
                        'Pedidos em tempo real',
                        'Acompanhamento de entregas',
                        'Suporte prioritário',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Listas usadas nos alertas de prova social simulados na landing (fácil de editar aqui).
     *
     * @return array<string, list<string>>
     */
    private function socialProofFakeClients(): array
    {
        return [
            'firstNames' => [
                'Carla Cakes', 'anamaria_cakes', 'Júlia Confeitaria', 'Tudo Doces', 'Açaí Deus é amor', 'Camila', 'Rafaela', 'Açaiteria da Rose', 'Bruna', 'Gabriela',
            ],
            'neighborhoods' => [
                'Centro', 'Vila Nova', 'Jardins', 'Boa Vista', 'Planalto', 'Alvorada', 'Santa Clara', 'Industrial',
            ],
            'cities' => [
                'São Paulo', 'Jundiaí', 'Indaiatuba', 'Campinas', 'Curitiba', 'Belo Horizonte', 'Goiânia', 'Recife', 'Fortaleza', 'Porto Alegre',
            ],
            'actions' => [
                'acabou de se cadastrar no plano Free',
                'acabou de assinar o plano Básico',
                'acabou de assinar o plano Pro',
                'finalizou o cadastro e já iniciou o teste',
            ],
            'minuteHints' => [
                'agora mesmo', 'há 1 min', 'há 2 min', 'há 3 min', 'há 5 min', 'há 7 min',
            ],
        ];
    }
}
