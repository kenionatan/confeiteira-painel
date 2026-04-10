<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class SeedPlans extends Migration
{
    public function up()
    {
        $now = date('Y-m-d H:i:s');
        $table = $this->db->table('plans');

        $existing = $table->select('slug')->get()->getResultArray();
        $existingSlugs = array_column($existing, 'slug');

        $payload = [
            [
                'slug' => 'free',
                'nome' => 'Free',
                'descricao' => 'Plano gratuito com cadastro completo.',
                'valor_mensal' => '0.00',
                'ativo' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'basico',
                'nome' => 'Básico',
                'descricao' => 'Precificação, cadastro de produtos e acesso por 7 dias.',
                'valor_mensal' => '27.90',
                'ativo' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'pro',
                'nome' => 'Pro',
                'descricao' => 'Tudo do Básico + recursos inteligentes de IA.',
                'valor_mensal' => '34.90',
                'ativo' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        $toInsert = array_filter(
            $payload,
            static fn(array $plan): bool => ! in_array($plan['slug'], $existingSlugs, true)
        );

        if ($toInsert !== []) {
            $table->insertBatch(array_values($toInsert));
        }
    }

    public function down()
    {
        $this->db->table('plans')->whereIn('slug', ['free', 'basico', 'pro'])->delete();
    }
}
