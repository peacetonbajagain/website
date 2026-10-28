<?php

namespace Travo\Admin\Libraries;

use RuntimeException;
use Throwable;

final class ImageThumbnail
{
    private const MAX_WIDTH = 640;
    private const MAX_HEIGHT = 360;
    private const QUALITY = 82;

    public static function targetRelativePath(string $sourcePath): string
    {
        $sourcePath = str_replace('\\', '/', $sourcePath);
        if (! str_starts_with($sourcePath, 'uploads/') || str_contains($sourcePath, '..')) {
            throw new RuntimeException('The image path is not a local upload.');
        }

        $directory = dirname($sourcePath);
        $filename = pathinfo($sourcePath, PATHINFO_FILENAME) . '-thumb.' . pathinfo($sourcePath, PATHINFO_EXTENSION);

        return ($directory === '.' ? '' : $directory . '/') . $filename;
    }

    public static function generate(string $sourcePath, string $thumbnailPath): bool
    {
        $imageInfo = @getimagesize($sourcePath);
        if ($imageInfo === false) {
            throw new RuntimeException('The image could not be read for thumbnail generation.');
        }

        [$width, $height] = $imageInfo;
        $scale = min(self::MAX_WIDTH / $width, self::MAX_HEIGHT / $height, 1);
        if ($scale >= 1) {
            return false;
        }

        $thumbnailWidth = max(1, (int) floor($width * $scale));
        $thumbnailHeight = max(1, (int) floor($height * $scale));
        $directory = dirname($thumbnailPath);
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('The thumbnail folder could not be created.');
        }

        try {
            if (
                ($imageInfo['mime'] ?? '') === 'image/webp'
                && ! (function_exists('imagecreatefromwebp') && function_exists('imagewebp'))
            ) {
                self::generateWebp($sourcePath, $thumbnailPath, $thumbnailWidth, $thumbnailHeight);
            } else {
                service('image', 'gd')->withFile($sourcePath)
                    ->resize($thumbnailWidth, $thumbnailHeight, true)
                    ->save($thumbnailPath, self::QUALITY);
            }
        } catch (Throwable $exception) {
            if (is_file($thumbnailPath)) {
                unlink($thumbnailPath);
            }

            throw new RuntimeException('The image thumbnail could not be generated.', 0, $exception);
        }

        if (! is_file($thumbnailPath)) {
            throw new RuntimeException('The image thumbnail could not be generated.');
        }

        return true;
    }

    private static function generateWebp(string $sourcePath, string $thumbnailPath, int $width, int $height): void
    {
        $binary = self::findExecutable('cwebp');
        if ($binary === null) {
            throw new RuntimeException('WebP thumbnails require the cwebp command.');
        }

        $process = proc_open(
            [$binary, '-quiet', '-resize', (string) $width, (string) $height, '-q', (string) self::QUALITY, $sourcePath, '-o', $thumbnailPath],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        if (! is_resource($process)) {
            throw new RuntimeException('The WebP thumbnail process could not be started.');
        }

        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        if (proc_close($process) !== 0 || ! is_file($thumbnailPath)) {
            throw new RuntimeException(trim($error) ?: 'The WebP thumbnail process failed.');
        }
    }

    private static function findExecutable(string $name): ?string
    {
        foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $directory) {
            $candidate = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}