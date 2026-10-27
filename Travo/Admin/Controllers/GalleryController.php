<?php

namespace Travo\Admin\Controllers;

use App\Controllers\BaseController;

class GalleryController extends BaseController
{
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    public static function libraryImages(): array
    {
        $images = [];
        $uploadRoot = realpath(FCPATH . 'uploads');

        if ($uploadRoot === false) {
            return $images;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($uploadRoot, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $imageInfo = @getimagesize($file->getPathname());
            if ($imageInfo === false || ! isset(self::MIME_EXTENSIONS[$imageInfo['mime'] ?? ''])) {
                continue;
            }

            $path = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), \strlen(FCPATH)));
            $images[] = [
                'path' => $path,
                'url' => base_url($path),
                'name' => $file->getFilename(),
                'size' => $file->getSize(),
                'width' => $imageInfo[0],
                'height' => $imageInfo[1],
                'modified' => $file->getMTime(),
            ];
        }

        usort($images, static fn (array $a, array $b): int => $b['modified'] <=> $a['modified']);

        return $images;
    }

    public function index()
    {
        return view('Travo\Admin\Views\Gallery\index', [
            'title' => 'Media library',
            'images' => self::libraryImages(),
        ]);
    }

    public function upload()
    {
        $files = $this->request->getFileMultiple('images') ?? [];
        if (\count($files) > 12) {
            return redirect()->to('admin/gallery')->with('error', 'Upload up to 12 images at a time.');
        }

        $directory = FCPATH . 'uploads/' . date('Y/m');
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            return redirect()->to('admin/gallery')->with('error', 'The upload folder could not be created.');
        }

        $uploaded = 0;
        $errors = [];

        foreach ($files as $file) {
            if ($file->getError() === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if (! $file->isValid() || $file->hasMoved()) {
                $errors[] = 'One file could not be uploaded.';
                continue;
            }
            if ($file->getSize() > 5 * 1024 * 1024) {
                $errors[] = 'Each image must be 5 MB or smaller.';
                continue;
            }

            $imageInfo = @getimagesize($file->getTempName());
            $mime = $imageInfo['mime'] ?? '';
            if ($imageInfo === false || ! isset(self::MIME_EXTENSIONS[$mime])) {
                $errors[] = 'Only valid JPG, PNG, GIF, or WebP images are accepted.';
                continue;
            }

            $name = bin2hex(random_bytes(16)) . '.' . self::MIME_EXTENSIONS[$mime];
            $file->move($directory, $name);
            $path = 'uploads/' . date('Y/m') . '/' . $name;
            log_activity('upload', 'gallery', $path, $name);
            $uploaded++;
        }

        if ($uploaded > 0) {
            $message = $uploaded . ' image' . ($uploaded === 1 ? '' : 's') . ' added to the library.';
            if ($errors !== []) {
                $message .= ' ' . implode(' ', array_unique($errors));
            }

            return redirect()->to('admin/gallery')->with('success', $message);
        }

        return redirect()->to('admin/gallery')->with('error', $errors !== [] ? implode(' ', array_unique($errors)) : 'Choose at least one image to upload.');
    }

    public function delete()
    {
        $relativePath = str_replace('\\', '/', trim((string) $this->request->getPost('path')));
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
            return redirect()->to('admin/gallery')->with('error', 'That image could not be found.');
        }

        $imageInfo = @getimagesize($filePath);
        if ($imageInfo === false || ! isset(self::MIME_EXTENSIONS[$imageInfo['mime'] ?? ''])) {
            return redirect()->to('admin/gallery')->with('error', 'Only images in the library can be deleted here.');
        }

        if (! unlink($filePath)) {
            return redirect()->to('admin/gallery')->with('error', 'The image could not be deleted.');
        }

        log_activity('delete', 'gallery', $relativePath, basename($relativePath));

        return redirect()->to('admin/gallery')->with('success', 'Image deleted.');
    }

    public function rename()
    {
        $relativePath = str_replace('\\', '/', trim((string) $this->request->getPost('path')));
        $newName = trim((string) $this->request->getPost('name'));
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
            return redirect()->to('admin/gallery')->with('error', 'That image could not be found.');
        }

        $imageInfo = @getimagesize($filePath);
        if ($imageInfo === false || ! isset(self::MIME_EXTENSIONS[$imageInfo['mime'] ?? ''])) {
            return redirect()->to('admin/gallery')->with('error', 'Only images in the library can be renamed here.');
        }
        if (! preg_match('/\A[A-Za-z0-9][A-Za-z0-9 _-]{0,99}\z/', $newName)) {
            return redirect()->to('admin/gallery')->with('error', 'Use 1–100 letters, numbers, spaces, hyphens, or underscores for the filename.');
        }

        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $newPath = dirname($filePath) . DIRECTORY_SEPARATOR . $newName . '.' . $extension;
        if ($newPath !== $filePath && file_exists($newPath)) {
            return redirect()->to('admin/gallery')->with('error', 'A file with that name already exists in this folder.');
        }
        if ($newPath !== $filePath && ! rename($filePath, $newPath)) {
            return redirect()->to('admin/gallery')->with('error', 'The image could not be renamed.');
        }

        $newRelativePath = str_replace(DIRECTORY_SEPARATOR, '/', substr($newPath, \strlen(FCPATH)));
        db_connect()->table('posts')->where('thumbnail', $relativePath)->update(['thumbnail' => $newRelativePath]);
        log_activity('rename', 'gallery', $newRelativePath, basename($filePath));

        return redirect()->to('admin/gallery')->with('success', 'Image renamed.');
    }
}