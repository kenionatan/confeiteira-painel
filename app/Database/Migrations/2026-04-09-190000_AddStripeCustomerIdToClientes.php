<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddStripeCustomerIdToClientes extends Migration
{
    public function up()
    {
        $this->forge->addColumn('clientes', [
            'stripe_customer_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('clientes', 'stripe_customer_id');
    }
}
