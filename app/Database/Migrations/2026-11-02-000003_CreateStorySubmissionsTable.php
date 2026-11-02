<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStorySubmissionsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id' => ['type' => 'INT', 'unsigned' => true],
            'title' => ['type' => 'VARCHAR', 'constraint' => 255],
            'excerpt' => ['type' => 'TEXT', 'null' => true],
            'content' => ['type' => 'LONGTEXT'],
            'image_path' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'image_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'status' => ['type' => 'ENUM', 'constraint' => ['pending', 'approved', 'rejected'], 'default' => 'pending'],
            'post_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ])->addKey('id', true)
            ->addKey('user_id')
            ->addKey('status')
            ->addKey('post_id')
            ->createTable('story_submissions', true);
    }

    public function down()
    {
        $this->forge->dropTable('story_submissions', true);
    }
}