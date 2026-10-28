<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUploadsTableAndConvertPostThumbnails extends Migration
{
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'path' => ['type' => 'VARCHAR', 'constraint' => 255],
            'name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'mime_type' => ['type' => 'VARCHAR', 'constraint' => 100],
            'file_size' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'width' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'height' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'uploaded_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ])->addKey('id', true)->addUniqueKey('path')->createTable('uploads', true);

        $this->forge->addColumn('posts', [
            'thumbnail_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addKey('thumbnail_id', false, false, 'idx_posts_thumbnail_id');
        $this->forge->processIndexes('posts');

        $this->indexExistingImages();

        $posts = $this->db->table('posts')->select('id, thumbnail')->where('thumbnail IS NOT NULL', null, false)->get()->getResultArray();
        foreach ($posts as $post) {
            $upload = $this->db->table('uploads')->select('id')->where('path', $post['thumbnail'])->get()->getRowArray();
            if ($upload === null && in_array(parse_url($post['thumbnail'], PHP_URL_SCHEME), ['http', 'https'], true)) {
                $imageUrlPath = (string) parse_url($post['thumbnail'], PHP_URL_PATH);
                $this->db->table('uploads')->insert([
                    'path' => $post['thumbnail'],
                    'name' => basename($imageUrlPath) ?: $post['thumbnail'],
                    'mime_type' => 'image/remote',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                $upload = $this->db->table('uploads')->select('id')->where('path', $post['thumbnail'])->get()->getRowArray();
            }
            if ($upload !== null) {
                $this->db->table('posts')->where('id', $post['id'])->update(['thumbnail_id' => $upload['id']]);
            }
        }

        $this->forge->dropColumn('posts', 'thumbnail');
    }

    public function down()
    {
        $this->forge->addColumn('posts', [
            'thumbnail' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ]);

        $posts = $this->db->table('posts p')->select('p.id, uploads.path')
            ->join('uploads', 'uploads.id = p.thumbnail_id', 'left')
            ->where('p.thumbnail_id IS NOT NULL', null, false)->get()->getResultArray();
        foreach ($posts as $post) {
            if ($post['path'] !== null) {
                $this->db->table('posts')->where('id', $post['id'])->update(['thumbnail' => $post['path']]);
            }
        }

        $this->forge->dropColumn('posts', 'thumbnail_id');
        $this->forge->dropTable('uploads', true);
    }

    private function indexExistingImages(): void
    {
        $uploadRoot = defined('FCPATH') ? realpath(FCPATH . 'uploads') : false;
        if ($uploadRoot === false) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($uploadRoot, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $imageInfo = @getimagesize($file->getPathname());
            $mime = $imageInfo['mime'] ?? '';
            if ($imageInfo === false || ! isset(self::MIME_EXTENSIONS[$mime])) {
                continue;
            }

            $path = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen(FCPATH)));
            if ($this->db->table('uploads')->where('path', $path)->countAllResults() > 0) {
                continue;
            }

            $this->db->table('uploads')->insert([
                'path' => $path,
                'name' => $file->getFilename(),
                'mime_type' => $mime,
                'file_size' => $file->getSize(),
                'width' => $imageInfo[0],
                'height' => $imageInfo[1],
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
}