<?php

namespace Travo\Admin\Models;

use CodeIgniter\Model;

class UploadModel extends Model
{
    protected $table = 'uploads';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['path', 'name', 'mime_type', 'file_size', 'width', 'height', 'uploaded_by'];

    public static function libraryImages(): array
    {
        $images = db_connect()->table('uploads')->orderBy('id', 'DESC')->get()->getResultArray();

        foreach ($images as &$image) {
            $scheme = parse_url($image['path'], PHP_URL_SCHEME);
            $image['url'] = in_array($scheme, ['http', 'https'], true) ? $image['path'] : base_url($image['path']);
            $image['size'] = $image['file_size'];
        }
        unset($image);

        return $images;
    }
}