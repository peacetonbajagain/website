<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPostEditorFieldsAndTags extends Migration
{
    public function up()
    {
        $this->forge->addColumn('blog_categories', [
            'author_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'level' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'content' => ['type' => 'LONGTEXT', 'null' => true],
            'thumbnail' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'meta_title' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'meta_desc' => ['type' => 'TEXT', 'null' => true],
            'icon' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'color' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'is_featured' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
        ]);

        $this->forge->addColumn('posts', [
            'post_type' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'article'],
            'show_thumbnail_in_featured' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'breaking_news_expires_at' => ['type' => 'DATETIME', 'null' => true],
            'is_pinned' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'source_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'source_url' => ['type' => 'VARCHAR', 'constraint' => 2048, 'null' => true],
            'featured_video' => ['type' => 'VARCHAR', 'constraint' => 2048, 'null' => true],
            'gallery_images' => ['type' => 'TEXT', 'null' => true],
            'tags' => ['type' => 'TEXT', 'null' => true],
            'author_role' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'author_bio' => ['type' => 'TEXT', 'null' => true],
            'author_avatar' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ]);

        $this->forge->addColumn('uploads', [
            'file_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'file_original_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'extension' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'type' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'upload_path' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'user_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'external_link' => ['type' => 'VARCHAR', 'constraint' => 2048, 'null' => true],
        ]);

        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'slug' => ['type' => 'VARCHAR', 'constraint' => 120],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ])->addKey('id', true)->addUniqueKey('slug')->createTable('tags', true);

        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'post_id' => ['type' => 'INT', 'unsigned' => true],
            'tag_id' => ['type' => 'INT', 'unsigned' => true],
        ])->addKey('id', true)->addUniqueKey(['post_id', 'tag_id'])->addKey('tag_id')->createTable('post_tags', true);

        $uploads = $this->db->table('uploads')->select('id, path, name, mime_type, uploaded_by')->get()->getResultArray();
        foreach ($uploads as $upload) {
            $path = str_replace('\\', '/', (string) $upload['path']);
            $relativePath = preg_replace('#^uploads/#', '', $path);
            $fileName = basename($relativePath);
            $uploadPath = dirname($relativePath);
            $uploadPath = $uploadPath === '.' ? '' : $uploadPath;
            $mime = (string) ($upload['mime_type'] ?? '');
            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $type = str_starts_with($mime, 'video/') ? 'video' : ($mime === 'application/pdf' ? 'pdf' : 'image');

            $this->db->table('uploads')->where('id', $upload['id'])->update([
                'file_name' => $fileName,
                'file_original_name' => $upload['name'],
                'extension' => $extension,
                'type' => $type,
                'upload_path' => $uploadPath,
                'user_id' => $upload['uploaded_by'],
            ]);
        }
    }

    public function down()
    {
        $this->forge->dropTable('post_tags', true);
        $this->forge->dropTable('tags', true);
        $this->forge->dropColumn('uploads', [
            'file_name', 'file_original_name', 'extension', 'type', 'upload_path', 'user_id', 'external_link',
        ]);
        $this->forge->dropColumn('posts', [
            'post_type', 'show_thumbnail_in_featured', 'breaking_news_expires_at', 'is_pinned', 'source_name',
            'source_url', 'featured_video', 'gallery_images', 'tags', 'author_role', 'author_bio', 'author_avatar',
        ]);
        $this->forge->dropColumn('blog_categories', [
            'author_id', 'level', 'content', 'thumbnail', 'meta_title', 'meta_desc', 'icon', 'color', 'is_featured',
        ]);
    }
}