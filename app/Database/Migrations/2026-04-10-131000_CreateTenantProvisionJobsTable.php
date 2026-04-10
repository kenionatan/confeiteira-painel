<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTenantProvisionJobsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'cliente_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'requested_host' => [
                'type'       => 'VARCHAR',
                'constraint' => 190,
            ],
            'requested_db_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
                'null'       => true,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['pending', 'processing', 'completed', 'failed'],
                'default'    => 'pending',
            ],
            'attempt_count' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'last_error' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'external_request_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => true,
            ],
            'payload_json' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'dispatched_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'completed_at' => [
                'type' => 'DATETIME',
                'null' => true,
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
        $this->forge->addUniqueKey('cliente_id');
        $this->forge->addKey('status');
        $this->forge->addForeignKey('cliente_id', 'clientes', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('tenant_provision_jobs');
    }

    public function down()
    {
        $this->forge->dropTable('tenant_provision_jobs', true);
    }
}
