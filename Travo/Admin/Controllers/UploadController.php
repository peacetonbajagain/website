<?php

namespace Travo\Admin\Controllers;

use App\Controllers\BaseController;
use Travo\Admin\Libraries\ImageUpload;
use Throwable;

class UploadController extends BaseController
{
    public function upload()
    {
        $files = $this->request->getFileMultiple('images') ?? [];
        if (count($files) > 12) {
            return redirect()->to('admin/gallery')->with('error', 'Upload up to 12 images at a time.');
        }

        $uploaded = 0;
        $errors = [];
        foreach ($files as $file) {
            if ($file->getError() === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            try {
                ImageUpload::store($file);
                $uploaded++;
            } catch (Throwable $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        if ($uploaded > 0) {
            $message = $uploaded . ' image' . ($uploaded === 1 ? '' : 's') . ' added to the library.';
            if ($errors !== []) {
                $message .= ' ' . implode(' ', array_unique($errors));
            }
            log_activity('upload', 'uploads', $uploaded, $message);

            return redirect()->to('admin/gallery')->with('success', $message);
        }

        return redirect()->to('admin/gallery')->with('error', $errors !== [] ? implode(' ', array_unique($errors)) : 'Choose at least one image to upload.');
    }
}