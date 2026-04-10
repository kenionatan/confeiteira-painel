<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Atualiza textos dos planos (acentuação e resumo do Básico).
 */
class UpdatePlansBasicoAndAccents extends Migration
{
    public function up()
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('plans')->where('slug', 'basico')->update([
            'nome' => 'Básico',
            'descricao' => 'Precificação, cadastro de produtos e acesso por 7 dias.',
            'updated_at' => $now,
        ]);
        $this->db->table('plans')->where('slug', 'free')->update([
            'descricao' => 'Plano gratuito com cadastro completo.',
            'updated_at' => $now,
        ]);
        $this->db->table('plans')->where('slug', 'pro')->update([
            'descricao' => 'Tudo do Básico + recursos inteligentes de IA.',
            'updated_at' => $now,
        ]);
    }

    public function down()
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('plans')->where('slug', 'basico')->update([
            'nome' => 'Basico',
            'descricao' => 'Gestao completa para rotina de vendas e clientes.',
            'updated_at' => $now,
        ]);
        $this->db->table('plans')->where('slug', 'free')->update([
            'descricao' => 'Plano gratuito com cadastro completo.',
            'updated_at' => $now,
        ]);
        $this->db->table('plans')->where('slug', 'pro')->update([
            'descricao' => 'Tudo do Basico + recursos inteligentes de IA.',
            'updated_at' => $now,
        ]);
    }
}
