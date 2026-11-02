<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddUserProfileFields extends Migration
{
    public function up()
    {
        $this->forge->addColumn('users', [
            'first_name' => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true],
            'last_name' => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true],
            'phone' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'mobile' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'points' => ['type' => 'INT', 'default' => 0],
            'wallet_balance' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'fcm_token' => ['type' => 'TEXT', 'null' => true],
            'avatar' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('users', [
            'first_name', 'last_name', 'phone', 'mobile', 'points', 'wallet_balance', 'fcm_token', 'avatar',
        ]);
    }
}
