<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPromotionStatusToSubscriptions extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('subscriptions')) {
            return;
        }

        $this->db->query(
            "ALTER TABLE subscriptions MODIFY status ENUM('trial','active','promotion','past_due','cancelled') DEFAULT 'trial'"
        );
    }

    public function down()
    {
        if (! $this->db->tableExists('subscriptions')) {
            return;
        }

        $this->db->table('subscriptions')
            ->where('status', 'promotion')
            ->update(['status' => 'trial']);

        $this->db->query(
            "ALTER TABLE subscriptions MODIFY status ENUM('trial','active','past_due','cancelled') DEFAULT 'trial'"
        );
    }
}
