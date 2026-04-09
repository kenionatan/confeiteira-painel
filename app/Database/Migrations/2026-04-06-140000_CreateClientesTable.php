<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateClientesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'nome' => [
                'type' => 'VARCHAR',
                'constraint' => 150,
            ],
            'dominio' => [
                'type' => 'VARCHAR',
                'constraint' => 190,
            ],
            'whatsapp' => [
                'type' => 'VARCHAR',
                'constraint' => 30,
            ],
            'email' => [
                'type' => 'VARCHAR',
                'constraint' => 180,
            ],
            'senha_hash' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'cartao_token' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'cartao_ultimos4' => [
                'type' => 'VARCHAR',
                'constraint' => 4,
            ],
            'cartao_bandeira' => [
                'type' => 'VARCHAR',
                'constraint' => 30,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('dominio');
        $this->forge->addUniqueKey('email');
        $this->forge->createTable('clientes');
    }

    public function down()
    {
        $this->forge->dropTable('clientes', true);
    }
}
