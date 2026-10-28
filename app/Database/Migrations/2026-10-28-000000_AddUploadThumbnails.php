<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Throwable;
use Travo\Admin\Libraries\ImageThumbnail;

class AddUploadThumbnails extends Migration
{
    public function up()
    {
        $this->forge->addColumn('uploads', [
            'thumbnail_path' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ]);

        $uploadRoot = realpath(FCPATH . 'uploads');
        if ($uploadRoot === false) {
            return;
        }

        $uploads = $this->db->table('uploads')->select('id, path, mime_type')->get()->getResultArray();
        foreach ($uploads as $upload) {
            $relativePath = str_replace('\\', '/', $upload['path']);
            if (
                ! str_starts_with($relativePath, 'uploads/')
                || str_contains($relativePath, '..')
                || ($upload['mime_type'] ?? '') === 'image/remote'
            ) {
                continue;
            }

            $sourcePath = realpath(FCPATH . $relativePath);
            if ($sourcePath === false || ! str_starts_with($sourcePath, $uploadRoot . DIRECTORY_SEPARATOR)) {
                continue;
            }

            $thumbnailRelativePath = ImageThumbnail::targetRelativePath($relativePath);
            try {
                if (ImageThumbnail::generate($sourcePath, FCPATH . $thumbnailRelativePath)) {
                    $this->db->table('uploads')->where('id', $upload['id'])->update([
                        'thumbnail_path' => $thumbnailRelativePath,
                    ]);
                }
            } catch (Throwable $exception) {
                log_message('error', 'Could not generate thumbnail for upload {id}: {error}', [
                    'id' => $upload['id'],
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    public function down()
    {
        $uploads = $this->db->table('uploads')->select('thumbnail_path')->where('thumbnail_path IS NOT NULL', null, false)->get()->getResultArray();
        $uploadRoot = realpath(FCPATH . 'uploads');

        foreach ($uploads as $upload) {
            $relativePath = str_replace('\\', '/', $upload['thumbnail_path']);
            if ($uploadRoot === false || ! str_starts_with($relativePath, 'uploads/') || str_contains($relativePath, '..')) {
                continue;
            }

            $thumbnailPath = realpath(FCPATH . $relativePath);
            if ($thumbnailPath !== false && str_starts_with($thumbnailPath, $uploadRoot . DIRECTORY_SEPARATOR) && is_file($thumbnailPath)) {
                unlink($thumbnailPath);
            }
        }

        $this->forge->dropColumn('uploads', 'thumbnail_path');
    }
}