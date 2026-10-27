<?php

namespace Travo\Admin\Controllers;

use App\Controllers\BaseController;
use Travo\Admin\Models\UploadModel;

class GalleryController extends BaseController
{
    public function index()
    {
        return view('Travo\Admin\Views\Gallery\index', [
            'title' => 'Media library',
            'images' => UploadModel::libraryImages(),
        ]);
    }

    public function delete()
    {
        $id = (int) $this->request->getPost('id');
        $model = new UploadModel();
        $image = $model->find($id);
        $filePath = $image ? $this->filePath($image['path']) : false;

        if ($image === null || $filePath === false) {
            return redirect()->to('admin/gallery')->with('error', 'That image could not be found.');
        }

        if (db_connect()->table('posts')->where('thumbnail_id', $id)->countAllResults() > 0) {
            return redirect()->to('admin/gallery')->with('error', 'This image is assigned to a post thumbnail and cannot be deleted.');
        }

        if (! unlink($filePath)) {
            return redirect()->to('admin/gallery')->with('error', 'The image could not be deleted.');
        }

        $model->delete($id);
        log_activity('delete', 'uploads', $id, $image['name']);

        return redirect()->to('admin/gallery')->with('success', 'Image deleted.');
    }

    public function rename()
    {
        $id = (int) $this->request->getPost('id');
        $newName = trim((string) $this->request->getPost('name'));
        $model = new UploadModel();
        $image = $model->find($id);
        $filePath = $image ? $this->filePath($image['path']) : false;

        if ($image === null || $filePath === false) {
            return redirect()->to('admin/gallery')->with('error', 'That image could not be found.');
        }

        if (! preg_match('/\A[A-Za-z0-9][A-Za-z0-9 _-]{0,99}\z/', $newName)) {
            return redirect()->to('admin/gallery')->with('error', 'Use 1–100 letters, numbers, spaces, hyphens, or underscores for the filename.');
        }

        $extension = pathinfo($image['path'], PATHINFO_EXTENSION);
        $newRelativePath = dirname($image['path']) . '/' . $newName . '.' . $extension;
        $newPath = FCPATH . $newRelativePath;
        if ($newPath !== $filePath && file_exists($newPath)) {
            return redirect()->to('admin/gallery')->with('error', 'A file with that name already exists in this folder.');
        }
        if ($newPath !== $filePath && ! rename($filePath, $newPath)) {
            return redirect()->to('admin/gallery')->with('error', 'The image could not be renamed.');
        }

        $model->update($id, ['path' => $newRelativePath, 'name' => $newName . '.' . $extension]);
        log_activity('rename', 'uploads', $id, $newRelativePath);

        return redirect()->to('admin/gallery')->with('success', 'Image renamed.');
    }

    private function filePath(string $relativePath): string|false
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
}