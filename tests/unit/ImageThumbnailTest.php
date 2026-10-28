<?php

use CodeIgniter\Test\CIUnitTestCase;
use Travo\Admin\Libraries\ImageThumbnail;

/**
 * @internal
 */
final class ImageThumbnailTest extends CIUnitTestCase
{
    public function testCreatesBoundedThumbnailWithoutUpscaling(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD is required to generate thumbnails.');
        }

        $sourcePath = tempnam(sys_get_temp_dir(), 'travo-image-');
        $thumbnailPath = $sourcePath . '-thumb.png';
        $source = imagecreatetruecolor(1600, 900);
        imagepng($source, $sourcePath);
        imagedestroy($source);

        try {
            $this->assertTrue(ImageThumbnail::generate($sourcePath, $thumbnailPath));
            $thumbnailInfo = getimagesize($thumbnailPath);
            $this->assertSame([640, 360], [$thumbnailInfo[0], $thumbnailInfo[1]]);

            $smallPath = $sourcePath . '-small.png';
            $smallThumbnailPath = $smallPath . '-thumb.png';
            $small = imagecreatetruecolor(320, 180);
            imagepng($small, $smallPath);
            imagedestroy($small);

            try {
                $this->assertFalse(ImageThumbnail::generate($smallPath, $smallThumbnailPath));
                $this->assertFileDoesNotExist($smallThumbnailPath);
            } finally {
                @unlink($smallPath);
            }
        } finally {
            @unlink($sourcePath);
            @unlink($thumbnailPath);
            @unlink($sourcePath . '-small.png-thumb.png');
        }
    }

    public function testCreatesWebpThumbnailWhenCwebpIsAvailable(): void
    {
        $binary = $this->findExecutable('cwebp');
        if ($binary === null) {
            $this->markTestSkipped('cwebp is required to test WebP thumbnail generation.');
        }

        $sourcePath = tempnam(sys_get_temp_dir(), 'travo-image-');
        $webpPath = $sourcePath . '.webp';
        $thumbnailPath = $sourcePath . '-thumb.webp';
        $source = imagecreatetruecolor(1600, 900);
        imagepng($source, $sourcePath);
        imagedestroy($source);

        try {
            $process = proc_open(
                [$binary, '-quiet', $sourcePath, '-o', $webpPath],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
            );
            $this->assertIsResource($process);
            fclose($pipes[0]);
            stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            $this->assertSame(0, proc_close($process));

            $this->assertTrue(ImageThumbnail::generate($webpPath, $thumbnailPath));
            $thumbnailInfo = getimagesize($thumbnailPath);
            $this->assertSame('image/webp', $thumbnailInfo['mime']);
            $this->assertSame([640, 360], [$thumbnailInfo[0], $thumbnailInfo[1]]);
        } finally {
            @unlink($sourcePath);
            @unlink($webpPath);
            @unlink($thumbnailPath);
        }
    }

    private function findExecutable(string $name): ?string
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