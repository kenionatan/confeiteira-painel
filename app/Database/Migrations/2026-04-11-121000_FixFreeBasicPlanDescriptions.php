<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Corrige cópia do Free (7 dias) e restaura descrição do Básico após ajuste anterior.
 * Idempotente: seguro rodar mesmo se o estado já estiver correto.
 */
class FixFreeBasicPlanDescriptions extends Migration
{
    public function up()
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('plans')->where('slug', 'free')->update([
            'descricao' => 'Precificação, cadastro de produtos e acesso por 7 dias.',
            'updated_at' => $now,
        ]);
        $this->db->table('plans')->where('slug', 'basico')->update([
            'nome' => 'Básico',
            'descricao' => 'Gestão completa para rotina de vendas e clientes.',
            'updated_at' => $now,
        ]);
    }

    public function down()
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('plans')->where('slug', 'free')->update([
            'descricao' => 'Plano gratuito com cadastro completo.',
            'updated_at' => $now,
        ]);
        $this->db->table('plans')->where('slug', 'basico')->update([
            'descricao' => 'Precificação, cadastro de produtos e acesso por 7 dias.',
            'updated_at' => $now,
        ]);
    }
}
