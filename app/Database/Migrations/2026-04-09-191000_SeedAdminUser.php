<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Insere um usuário administrador para acesso ao painel.
 * Credenciais padrão: admin@appdoce.top / admin123
 * Sobrescreva com PAINEL_ADMIN_EMAIL e PAINEL_ADMIN_PASSWORD no .env antes de migrar.
 */
class SeedAdminUser extends Migration
{
    public function up()
    {
        $email = env('PAINEL_ADMIN_EMAIL', 'admin@appdoce.top');
        $plain = env('PAINEL_ADMIN_PASSWORD', 'admin123');

        $builder = $this->db->table('users');
        if ($builder->where('email', $email)->countAllResults() > 0) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $builder->insert([
            'cliente_id'       => null,
            'name'             => 'Administrador',
            'email'            => $email,
            'password_hash'    => password_hash($plain, PASSWORD_DEFAULT),
            'preferred_theme'  => 'light',
            'is_owner'         => 1,
            'is_active'        => 1,
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);
    }

    public function down()
    {
        $email = env('PAINEL_ADMIN_EMAIL', 'admin@appdoce.top');
        $this->db->table('users')->where('email', $email)->delete();
    }
}
