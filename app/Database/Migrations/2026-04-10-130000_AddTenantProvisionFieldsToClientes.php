<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTenantProvisionFieldsToClientes extends Migration
{
    public function up()
    {
        $this->forge->addColumn('clientes', [
            'tenant_status' => [
                'type'       => 'ENUM',
                'constraint' => ['pending', 'provisioning', 'ready', 'failed'],
                'default'    => 'pending',
                'after'      => 'stripe_customer_id',
            ],
            'tenant_db_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
                'null'       => true,
                'after'      => 'tenant_status',
            ],
            'tenant_db_user' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
                'null'       => true,
                'after'      => 'tenant_db_name',
            ],
            'tenant_ready_at' => [
                'type'  => 'DATETIME',
                'null'  => true,
                'after' => 'tenant_db_user',
            ],
            'tenant_error_message' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'tenant_ready_at',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('clientes', 'tenant_error_message');
        $this->forge->dropColumn('clientes', 'tenant_ready_at');
        $this->forge->dropColumn('clientes', 'tenant_db_user');
        $this->forge->dropColumn('clientes', 'tenant_db_name');
        $this->forge->dropColumn('clientes', 'tenant_status');
    }
}
