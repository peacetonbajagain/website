<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTravoNewsTables extends Migration
{
    private function base(array $extra): array
    {
        return ['id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true]] + $extra;
    }

    public function up()
    {
        $ts = ['created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true]];
        $int = fn($default = 0) => ['type' => 'TINYINT', 'constraint' => 1, 'default' => $default];

        $this->forge->addField($this->base([
            'parent_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'sort_order' => ['type' => 'INT', 'default' => 0],
            'on_menu' => $int(1),
            'title' => ['type' => 'VARCHAR', 'constraint' => 255],
            'slug' => ['type' => 'VARCHAR', 'constraint' => 200],
            'status' => $int(1),
        ] + $ts))->addKey('id', true)->addUniqueKey('slug')->createTable('blog_categories', true);

        $this->forge->addField($this->base([
            'author_id' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'title' => ['type' => 'VARCHAR', 'constraint' => 255],
            'slug' => ['type' => 'VARCHAR', 'constraint' => 255],
            'content' => ['type' => 'LONGTEXT', 'null' => true],
            'excerpt' => ['type' => 'TEXT', 'null' => true],
            'thumbnail' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'is_featured' => $int(), 'is_breaking_news' => $int(), 'is_trending' => $int(),
            'meta_title' => ['type' => 'VARCHAR', 'constraint' => 250, 'null' => true],
            'meta_desc' => ['type' => 'TEXT', 'null' => true],
            'author_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'view_count' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'status' => $int(0),
            'published_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ] + $ts))->addKey('id', true)->addUniqueKey('slug')->addKey(['status', 'published_at'])->createTable('posts', true);

        $this->forge->addField($this->base([
            'post_id' => ['type' => 'INT', 'unsigned' => true],
            'category_id' => ['type' => 'INT', 'unsigned' => true],
        ]))->addKey('id', true)->addKey('post_id')->addKey('category_id')->createTable('post_categories', true);

        $this->forge->addField($this->base([
            'title' => ['type' => 'VARCHAR', 'constraint' => 150],
            'slug' => ['type' => 'VARCHAR', 'constraint' => 190],
            'content' => ['type' => 'LONGTEXT', 'null' => true],
            'meta_title' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'meta_desc' => ['type' => 'TEXT', 'null' => true],
            'status' => $int(1),
        ] + $ts))->addKey('id', true)->addUniqueKey('slug')->createTable('pages', true);

        $this->forge->addField($this->base([
            'post_id' => ['type' => 'INT', 'unsigned' => true],
            'parent_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'author_name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'author_email' => ['type' => 'VARCHAR', 'constraint' => 255],
            'body' => ['type' => 'TEXT'],
            'status' => ['type' => 'ENUM', 'constraint' => ['pending', 'approved', 'spam'], 'default' => 'pending'],
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
        ] + $ts))->addKey('id', true)->addKey('post_id')->addKey('status')->createTable('comments', true);

        $this->forge->addField($this->base([
            'user_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'user_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'module' => ['type' => 'VARCHAR', 'constraint' => 50],
            'action' => ['type' => 'VARCHAR', 'constraint' => 50],
            'target_id' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'details' => ['type' => 'TEXT', 'null' => true],
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]))->addKey('id', true)->createTable('activity_logs', true);
    }

    public function down()
    {
        foreach (['activity_logs', 'comments', 'pages', 'post_categories', 'posts', 'blog_categories'] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
