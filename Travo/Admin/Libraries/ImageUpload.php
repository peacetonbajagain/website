<?php

namespace Travo\Admin\Libraries;

use CodeIgniter\HTTP\Files\UploadedFile;
use RuntimeException;

final class ImageUpload
{
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    public static function store(UploadedFile $file): int
    {
        if (! $file->isValid() || $file->hasMoved()) {
            throw new RuntimeException('One image could not be uploaded.');
        }
        if ($file->getSize() > 5 * 1024 * 1024) {
            throw new RuntimeException('Each image must be 5 MB or smaller.');
        }

        $imageInfo = @getimagesize($file->getTempName());
        $mime = $imageInfo['mime'] ?? '';
        if ($imageInfo === false || ! isset(self::MIME_EXTENSIONS[$mime])) {
            throw new RuntimeException('Only valid JPG, PNG, GIF, or WebP images are accepted.');
        }

        $relativeDirectory = 'uploads/' . date('Y/m');
        $directory = FCPATH . $relativeDirectory;
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('The upload folder could not be created.');
        }

        $name = bin2hex(random_bytes(16)) . '.' . self::MIME_EXTENSIONS[$mime];
        $file->move($directory, $name);
        $path = $relativeDirectory . '/' . $name;
        $absolutePath = $directory . DIRECTORY_SEPARATOR . $name;
        $thumbnailRelativePath = ImageThumbnail::targetRelativePath($path);
        $thumbnailAbsolutePath = FCPATH . $thumbnailRelativePath;
        $db = db_connect();

        try {
            $hasThumbnail = ImageThumbnail::generate($absolutePath, $thumbnailAbsolutePath);
            $db->table('uploads')->insert([
                'path' => $path,
                'thumbnail_path' => $hasThumbnail ? $thumbnailRelativePath : null,
                'name' => basename($file->getClientName()),
                'mime_type' => $mime,
                'file_size' => filesize($absolutePath),
                'width' => $imageInfo[0],
                'height' => $imageInfo[1],
                'uploaded_by' => auth()->id(),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            return (int) $db->insertID();
        } catch (\Throwable $exception) {
            if (is_file($thumbnailAbsolutePath)) {
                unlink($thumbnailAbsolutePath);
            }
            if (is_file($absolutePath)) {
                unlink($absolutePath);
            }

            throw new RuntimeException('The image could not be added to the media library.', 0, $exception);
        }
    }
}