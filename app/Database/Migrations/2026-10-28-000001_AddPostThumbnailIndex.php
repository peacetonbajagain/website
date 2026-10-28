<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Migration;

class AddPostThumbnailIndex extends Migration
{
    private const INDEX_NAME = 'idx_posts_thumbnail_id';

    public function up()
    {
        $index = $this->thumbnailIndex();
        if ($index !== null) {
            return;
        }

        $this->forge->addKey('thumbnail_id', false, false, self::INDEX_NAME);
        $this->forge->processIndexes('posts');
    }

    public function down()
    {
        $index = $this->thumbnailIndex(true);
        if ($index !== null) {
            $this->forge->dropKey('posts', self::INDEX_NAME, false);
        }
    }

    private function thumbnailIndex(bool $namedOnly = false): ?object
    {
        if (! $this->db instanceof BaseConnection) {
            return null;
        }

        foreach ($this->db->getIndexData('posts') as $index) {
            if ($index->name === self::INDEX_NAME || (! $namedOnly && in_array('thumbnail_id', $index->fields, true))) {
                return $index;
            }
        }

        return null;
    }
}