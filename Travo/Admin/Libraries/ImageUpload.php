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
        [$imageInfo, $mime, $extension] = self::inspect($file);

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
                'file_name' => $name,
                'file_original_name' => basename($file->getClientName()),
                'extension' => $extension,
                'type' => 'image',
                'upload_path' => date('Y/m'),
                'user_id' => auth()->id(),
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

    /** @return array{path: string, name: string} */
    public static function stage(UploadedFile $file): array
    {
        [, , $extension] = self::inspect($file);
        $directory = WRITEPATH . 'uploads/story-submissions';
        if (! is_dir($directory) && ! mkdir($directory, 0750, true) && ! is_dir($directory)) {
            throw new RuntimeException('The private upload folder could not be created.');
        }

        $name = bin2hex(random_bytes(16)) . '.' . $extension;
        $file->move($directory, $name);

        return [
            'path' => $name,
            'name' => basename($file->getClientName()),
        ];
    }

    public static function publishStaged(string $stagedName, string $originalName, int $userId): int
    {
        if (! preg_match('/\\A[a-f0-9]{32}\\.(?:jpg|png|gif|webp)\\z/', $stagedName)) {
            throw new RuntimeException('The staged image path is invalid.');
        }

        $pendingDirectory = realpath(WRITEPATH . 'uploads/story-submissions');
        $stagedPath = realpath(WRITEPATH . 'uploads/story-submissions/' . $stagedName);
        if (
            $pendingDirectory === false
            || $stagedPath === false
            || ! str_starts_with($stagedPath, $pendingDirectory . DIRECTORY_SEPARATOR)
            || ! is_file($stagedPath)
        ) {
            throw new RuntimeException('The staged image could not be found.');
        }

        $imageInfo = @getimagesize($stagedPath);
        $mime = $imageInfo['mime'] ?? '';
        $extension = self::MIME_EXTENSIONS[$mime] ?? null;
        if ($imageInfo === false || $extension === null || pathinfo($stagedName, PATHINFO_EXTENSION) !== $extension) {
            throw new RuntimeException('The staged image is no longer valid.');
        }

        $uploadDirectory = date('Y/m');
        $relativeDirectory = 'uploads/' . $uploadDirectory;
        $directory = FCPATH . $relativeDirectory;
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('The public upload folder could not be created.');
        }

        $fileName = bin2hex(random_bytes(16)) . '.' . $extension;
        $relativePath = $relativeDirectory . '/' . $fileName;
        $absolutePath = $directory . DIRECTORY_SEPARATOR . $fileName;
        if (! rename($stagedPath, $absolutePath)) {
            throw new RuntimeException('The approved image could not be moved to the gallery.');
        }

        $thumbnailRelativePath = ImageThumbnail::targetRelativePath($relativePath);
        $thumbnailAbsolutePath = FCPATH . $thumbnailRelativePath;
        $db = db_connect();

        try {
            $hasThumbnail = ImageThumbnail::generate($absolutePath, $thumbnailAbsolutePath);
            $db->table('uploads')->insert([
                'path' => $relativePath,
                'thumbnail_path' => $hasThumbnail ? $thumbnailRelativePath : null,
                'name' => basename($originalName),
                'mime_type' => $mime,
                'file_size' => filesize($absolutePath),
                'width' => $imageInfo[0],
                'height' => $imageInfo[1],
                'uploaded_by' => $userId,
                'file_name' => $fileName,
                'file_original_name' => basename($originalName),
                'extension' => $extension,
                'type' => 'image',
                'upload_path' => $uploadDirectory,
                'user_id' => $userId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $uploadId = (int) $db->insertID();
            if ($uploadId === 0) {
                throw new RuntimeException('The approved image could not be added to the gallery.');
            }

            return $uploadId;
        } catch (\Throwable $exception) {
            if (is_file($thumbnailAbsolutePath)) {
                unlink($thumbnailAbsolutePath);
            }
            if (is_file($absolutePath)) {
                rename($absolutePath, $stagedPath);
            }

            throw new RuntimeException('The approved image could not be added to the gallery.', 0, $exception);
        }
    }

    public static function discardStaged(?string $stagedName): void
    {
        if ($stagedName === null || ! preg_match('/\\A[a-f0-9]{32}\\.(?:jpg|png|gif|webp)\\z/', $stagedName)) {
            return;
        }

        $stagedPath = self::stagedAbsolutePath($stagedName);
        if ($stagedPath !== false) {
            unlink($stagedPath);
        }
    }

    public static function stagedAbsolutePath(string $stagedName): string|false
    {
        if (! preg_match('/\\A[a-f0-9]{32}\\.(?:jpg|png|gif|webp)\\z/', $stagedName)) {
            return false;
        }

        $pendingDirectory = realpath(WRITEPATH . 'uploads/story-submissions');
        $stagedPath = realpath(WRITEPATH . 'uploads/story-submissions/' . $stagedName);
        if (
            $pendingDirectory === false
            || $stagedPath === false
            || ! str_starts_with($stagedPath, $pendingDirectory . DIRECTORY_SEPARATOR)
            || ! is_file($stagedPath)
        ) {
            return false;
        }

        return $stagedPath;
    }

    public static function restoreStaged(int $uploadId, string $stagedName): void
    {
        if (! preg_match('/\\A[a-f0-9]{32}\\.(?:jpg|png|gif|webp)\\z/', $stagedName)) {
            return;
        }

        $upload = db_connect()->table('uploads')->where('id', $uploadId)->get()->getRowArray();
        $source = $upload ? self::publicAbsolutePath((string) $upload['path']) : false;
        if ($upload === null || $source === false) {
            return;
        }

        $pendingDirectory = WRITEPATH . 'uploads/story-submissions';
        if (! is_dir($pendingDirectory) && ! mkdir($pendingDirectory, 0750, true) && ! is_dir($pendingDirectory)) {
            return;
        }
        $stagedPath = $pendingDirectory . DIRECTORY_SEPARATOR . $stagedName;
        if (file_exists($stagedPath) || ! rename($source, $stagedPath)) {
            return;
        }

        if (! empty($upload['thumbnail_path'])) {
            $thumbnailPath = self::publicAbsolutePath((string) $upload['thumbnail_path']);
            if ($thumbnailPath !== false) {
                unlink($thumbnailPath);
            }
        }
        db_connect()->table('uploads')->where('id', $uploadId)->delete();
    }

    private static function publicAbsolutePath(string $relativePath): string|false
    {
        $uploadRoot = realpath(FCPATH . 'uploads');
        $filePath = realpath(FCPATH . $relativePath);
        if (
            $uploadRoot === false
            || ! str_starts_with($relativePath, 'uploads/')
            || str_contains($relativePath, '..')
            || $filePath === false
            || ! str_starts_with($filePath, $uploadRoot . DIRECTORY_SEPARATOR)
            || ! is_file($filePath)
        ) {
            return false;
        }

        return $filePath;
    }

    /** @return array{0: array, 1: string, 2: string} */
    private static function inspect(UploadedFile $file): array
    {
        if (! $file->isValid() || $file->hasMoved()) {
            throw new RuntimeException('One image could not be uploaded.');
        }
        if ($file->getSize() > 5 * 1024 * 1024) {
            throw new RuntimeException('Each image must be 5 MB or smaller.');
        }

        $imageInfo = @getimagesize($file->getTempName());
        $mime = $imageInfo['mime'] ?? '';
        $extension = self::MIME_EXTENSIONS[$mime] ?? null;
        if ($imageInfo === false || $extension === null) {
            throw new RuntimeException('Only valid JPG, PNG, GIF, or WebP images are accepted.');
        }

        return [$imageInfo, $mime, $extension];
    }
}