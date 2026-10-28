<?php

namespace Travo\Admin\Models;

use CodeIgniter\Model;

class UploadModel extends Model
{
    protected $table = 'uploads';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['path', 'thumbnail_path', 'name', 'mime_type', 'file_size', 'width', 'height', 'uploaded_by'];

    public static function libraryImages(): array
    {
        $images = db_connect()->table('uploads')->orderBy('id', 'DESC')->get()->getResultArray();

        foreach ($images as &$image) {
            $scheme = parse_url($image['path'], PHP_URL_SCHEME);
            $previewPath = $image['thumbnail_path'] ?: $image['path'];
            $image['url'] = in_array($scheme, ['http', 'https'], true) ? $previewPath : base_url($previewPath);
            $image['size'] = $image['file_size'];
        }
        unset($image);

        return $images;
    }
}