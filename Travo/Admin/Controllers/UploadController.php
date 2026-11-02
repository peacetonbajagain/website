<?php

namespace Travo\Admin\Controllers;

use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Controller;
use Travo\Admin\Models\UploadModel;

class UploadController extends Controller
{
    use ResponseTrait;

    protected $uploadModel;
    protected const UPLOAD_PATH = 'uploads/';
    // Define thumbnail sizes in one place for consistency
    protected const THUMBNAIL_SIZES = ['900x600', '600x600', '300x300', '100x100'];

    // Upload security limits (bytes)
    protected const MAX_IMAGE_SIZE = 20 * 1024 * 1024;   // 20 MB
    protected const MAX_VIDEO_SIZE = 200 * 1024 * 1024;  // 200 MB
    protected const MAX_PDF_SIZE = 50 * 1024 * 1024;     // 50 MB

    // WebP conversion quality (0-100) — high-contrast, high-compression balanced
    protected const WEBP_QUALITY = 80;
    // Preserve animated GIFs (GD cannot write animated WebP, so keep GIF)
    protected const PRESERVE_ANIMATED_GIF = true;

    // Extension allowlists - the client filename extension must match the detected content type
    // Expanded to generally all common image formats
    protected const ALLOWED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp', 'tif', 'tiff', 'svg', 'heic', 'heif', 'jfif', 'pjpeg', 'pjp', 'ico'];
    protected const ALLOWED_VIDEO_EXTENSIONS = ['mp4', 'webm', 'mov', 'm4v', 'mkv', 'avi', 'ogv', 'ogg', 'mpeg', 'mpg'];
    protected const ALLOWED_VIDEO_MIMES = [
        'video/mp4', 'video/webm', 'video/quicktime', 'video/x-m4v', 'video/x-matroska',
        'video/x-msvideo', 'video/ogg', 'video/mpeg', 'video/3gpp', 'video/x-ms-wmv',
    ];

    /**
     * Detect the real content type from magic bytes with fallbacks.
     */
    protected function detectRealMime(string $path, ?string $fallbackMime = null): ?string
    {
        if (class_exists('\finfo')) {
            try {
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($path);
                if ($mime && $mime !== 'application/octet-stream') {
                    return $mime;
                }
            } catch (\Throwable $e) {
                // Ignore finfo failure
            }
        }

        if (function_exists('mime_content_type')) {
            $mime = @mime_content_type($path);
            if ($mime && $mime !== 'application/octet-stream') {
                return $mime;
            }
        }

        if (function_exists('getimagesize')) {
            $imageInfo = @getimagesize($path);
            if (!empty($imageInfo['mime'])) {
                return $imageInfo['mime'];
            }
        }

        return $fallbackMime;
    }

    /**
     * Detect animated GIF by counting Graphic Control Extensions.
     * Used with GD (which flattens animation) to decide to preserve GIF.
     */
    protected function isAnimatedGif(string $path): bool
    {
        if (!is_file($path)) return false;
        // Prefer Imagick if available (accurate)
        $imagickClass = 'Imagick';
        if (class_exists($imagickClass)) {
            try {
            $im = new $imagickClass($path);
                $count = $im->getNumberImages();
                $im->clear();
                if ($count > 1) return true;
            } catch (\Throwable $e) {}
        }
        $content = @file_get_contents($path);
        if ($content === false) return false;
        // GCE header 0x21 0xF9 0x04 appears per frame
        return substr_count($content, "\x21\xF9\x04") > 1;
    }

    /**
     * Light SVG sanitization — strip scripts, event handlers, javascript: URIs.
     */
    protected function sanitizeSvgContent(string $svg): string
    {
        // Remove script tags
        $svg = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $svg);
        // Remove on* attributes
        $svg = preg_replace('/\s+on[a-z]+\s*=\s*["\'][^"\']*["\']/i', '', $svg);
        $svg = preg_replace('/\s+on[a-z]+\s*=\s*[^ >]+/i', '', $svg);
        // Remove javascript: href
        $svg = preg_replace('/href\s*=\s*["\']javascript:[^"\']*["\']/i', 'href="#"', $svg);
        return $svg;
    }

    protected function renderImage(string $sourcePath, string $targetPath, ?int $width, ?int $height, string $format): void
    {
        $image = \Config\Services::image(null, null, false)->withFile($sourcePath);
        if ($width !== null && $height !== null) {
            $image->fit($width, $height);
        }

        $imageType = $format === 'webp' ? IMAGETYPE_WEBP : IMAGETYPE_JPEG;
        $image->convert($imageType)->save($targetPath, self::WEBP_QUALITY);
    }

    /**
     * Apply multi-word search: split by whitespace, every word must match (AND)
     * across file_name OR file_original_name OR type. High-contrast UX for words.
     */
    protected function applyMultiWordSearch($query, string $search, array $fields = ['file_name', 'file_original_name']): void
    {
        $search = trim($search);
        if ($search === '') return;
        $words = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY);
        if (!$words) return;
        foreach ($words as $word) {
            $word = trim($word);
            if ($word === '') continue;
            $query->groupStart();
            $first = true;
            foreach ($fields as $field) {
                if ($first) {
                    $query->like($field, $word);
                    $first = false;
                } else {
                    $query->orLike($field, $word);
                }
            }
            $query->groupEnd();
        }
    }

    /**
     * Validate an uploaded file against the security policy.
     * Returns [ok: bool, type: ?string, error: ?string]
     *
     * @return array{ok: bool, type: ?string, error: ?string, realMime: ?string}
     */
    protected function validateUpload($file, string $originalName): array
    {
        $size = $file->getSize();

        if ($size > 0 && $size > self::MAX_VIDEO_SIZE) {
            return [false, null, "$originalName: file exceeds the maximum allowed size", null];
        }

        $clientMime = method_exists($file, 'getClientMimeType') ? $file->getClientMimeType() : null;
        $realMime = $this->detectRealMime($file->getTempName(), $clientMime);

        if ($realMime === null || $realMime === 'application/octet-stream') {
            if ($clientMime && $clientMime !== 'application/octet-stream') {
                $realMime = $clientMime;
            } else {
                return [false, null, "$originalName: unable to determine file content", $realMime];
            }
        }

        if (str_starts_with($realMime, 'image/')) {
            if ($size > self::MAX_IMAGE_SIZE) {
                return [false, null, "$originalName: image exceeds the maximum allowed size", $realMime];
            }
            $clientExt = strtolower((string) $file->getExtension());
            // SVG special: must be svg
            if ($realMime === 'image/svg+xml') {
                if ($clientExt !== '' && $clientExt !== 'svg') {
                    return [false, null, "$originalName: SVG must have .svg extension", $realMime];
                }
                return [true, 'image', null, $realMime];
            }
            // Generally allow all image/*, but enforce allowlist when extension present
            // If extension not in allowlist but mime is valid image, derive extension from mime (covers tif->tiff, etc)
            if ($clientExt !== '' && !\in_array($clientExt, self::ALLOWED_IMAGE_EXTENSIONS, true)) {
                // Allow mime-derived extension fallback for known mimes
                $mimeToExt = [
                    'image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/pjpeg' => 'jpg',
                    'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp',
                    'image/avif' => 'avif', 'image/bmp' => 'bmp', 'image/x-ms-bmp' => 'bmp',
                    'image/tiff' => 'tiff', 'image/x-tiff' => 'tiff',
                    'image/heic' => 'heic', 'image/heif' => 'heif', 'image/x-icon' => 'ico',
                    'image/vnd.microsoft.icon' => 'ico',
                ];
                $derived = $mimeToExt[$realMime] ?? '';
                if ($derived === '' || $derived !== $clientExt) {
                    // still reject if truly unknown extension
                    if (!\in_array($clientExt, ['tif','jfif','pjp','ico'], true)) {
                        return [false, null, "$originalName: image extension '$clientExt' is not allowed", $realMime];
                    }
                }
            }
            return [true, 'image', null, $realMime];
        }

        if (str_starts_with($realMime, 'video/')) {
            if (!\in_array($realMime, self::ALLOWED_VIDEO_MIMES, true)) {
                return [false, null, "$originalName: video format is not allowed", $realMime];
            }
            if ($size > self::MAX_VIDEO_SIZE) {
                return [false, null, "$originalName: video exceeds the maximum allowed size", $realMime];
            }
            $clientExt = strtolower((string) $file->getExtension());

            if ($clientExt !== '' && !\in_array($clientExt, self::ALLOWED_VIDEO_EXTENSIONS, true)) {
                return [false, null, "$originalName: video extension '$clientExt' is not allowed", $realMime];
            }

            return [true, 'video', null, $realMime];
        }

        if ($realMime === 'application/pdf') {
            if ($size > self::MAX_PDF_SIZE) {
                return [false, null, "$originalName: PDF exceeds the maximum allowed size", $realMime];
            }

            return [true, 'pdf', null, $realMime];
        }

        return [false, null, "$originalName: unsupported file type", $realMime];
    }

    public function __construct()
    {
        $this->uploadModel = new UploadModel();

        // Ensure upload directory exists
        $uploadRoot = FCPATH . self::UPLOAD_PATH;
        if (!is_dir($uploadRoot) && !mkdir($uploadRoot, 0775, true)) {
            throw new \RuntimeException('Failed to create upload directory: ' . $uploadRoot);
        }
        helper('files');
    }

    /**
     * Resolve a safe output extension for an uploaded video.
     * Prefers the allowlisted client extension, falls back to one derived from the detected MIME.
     */
    protected function getSafeExtension($file, string $realMime): string
    {
        $clientExt = strtolower((string) $file->getExtension());

        if (\in_array($clientExt, self::ALLOWED_VIDEO_EXTENSIONS, true)) {
            return $clientExt;
        }

        $mimeToExt = [
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
            'video/x-m4v' => 'm4v',
            'video/x-matroska' => 'mkv',
            'video/x-msvideo' => 'avi',
            'video/ogg' => 'ogv',
            'video/mpeg' => 'mpg',
            'video/3gpp' => '3gp',
            'video/x-ms-wmv' => 'wmv',
        ];

        return $mimeToExt[$realMime] ?? 'mp4';
    }

    public function index()
    {
        $request = service('request');
        $search = $request->getGet('search') ?? '';
        $type = $request->getGet('type') ?? '';

        // Stats (fresh queries, before any builder state is set)
        $totalFiles = $this->uploadModel->countAllResults();
        $sizeSummary = $this->uploadModel->selectSum('file_size')->first();
        $totalSize = $sizeSummary['file_size'] ?? 0;
        $imageCount = $this->uploadModel->where('type', 'image')->countAllResults();
        $videoCount = $this->uploadModel->where('type', 'video')->countAllResults();
        $pdfCount = $this->uploadModel->where('type', 'pdf')->countAllResults();

        // Main query with filters
        $query = $this->uploadModel->orderBy('created_at', 'DESC');

        if ($search !== '') {
            $this->applyMultiWordSearch($query, $search, ['file_name', 'file_original_name']);
        }
        if ($type !== '' && \in_array($type, ['image', 'video', 'pdf'])) {
            $query->where('type', $type);
        }

        $data = [
            'page_heading' => 'Manage Media',
            'medias' => $query->paginate(24),
            'pager' => $this->uploadModel->pager,
            'search' => $search,
            'type' => $type,
            'total_files' => $totalFiles,
            'total_size' => $totalSize,
            'image_count' => $imageCount,
            'video_count' => $videoCount,
            'pdf_count' => $pdfCount,
        ];

        return view('Travo\Admin\Views\Media\index', $data);
    }

    public function galleryIndex()
    {
        $search = trim((string) $this->request->getGet('q'));
        $type = (string) $this->request->getGet('type');

        return view('Travo\Admin\Views\Gallery\index', [
            'title' => 'Media library',
            'images' => UploadModel::libraryItems($search, $type),
            'search' => $search,
            'type' => $type,
        ]);
    }

    public function deleteFromGallery()
    {
        $id = (int) $this->request->getPost('id');
        $model = new UploadModel();
        $image = $model->find($id);
        $filePath = $image ? $this->galleryFilePath($image['path']) : false;

        if ($image === null || $filePath === false) {
            return redirect()->to('admin/gallery')->with('error', 'That image could not be found.');
        }

        if (db_connect()->table('posts')->where('thumbnail_id', $id)->countAllResults() > 0) {
            return redirect()->to('admin/gallery')->with('error', 'This image is assigned to a post thumbnail and cannot be deleted.');
        }

        if (! unlink($filePath)) {
            return redirect()->to('admin/gallery')->with('error', 'The image could not be deleted.');
        }

        if (! empty($image['thumbnail_path'])) {
            $thumbnailFile = $this->galleryFilePath($image['thumbnail_path']);
            if ($thumbnailFile !== false && $thumbnailFile !== $filePath && is_file($thumbnailFile)) {
                unlink($thumbnailFile);
            }
        }

        $model->delete($id);
        log_activity('delete', 'uploads', $id, $image['name']);

        return redirect()->to('admin/gallery')->with('success', 'Image deleted.');
    }

    public function renameInGallery()
    {
        $id = (int) $this->request->getPost('id');
        $newName = trim((string) $this->request->getPost('name'));
        $model = new UploadModel();
        $image = $model->find($id);
        $filePath = $image ? $this->galleryFilePath($image['path']) : false;

        if ($image === null || $filePath === false) {
            return redirect()->to('admin/gallery')->with('error', 'That image could not be found.');
        }

        if (! preg_match('/\A[A-Za-z0-9][A-Za-z0-9 _-]{0,99}\z/', $newName)) {
            return redirect()->to('admin/gallery')->with('error', 'Use 1-100 letters, numbers, spaces, hyphens, or underscores for the filename.');
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

        $renamedFile = $newName . '.' . $extension;
        $model->update($id, [
            'path' => $newRelativePath,
            'name' => $renamedFile,
            'file_name' => $renamedFile,
            'file_original_name' => $renamedFile,
        ]);
        log_activity('rename', 'uploads', $id, $newRelativePath);

        return redirect()->to('admin/gallery')->with('success', 'Image renamed.');
    }

    private function galleryFilePath(string $relativePath): string|false
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

    public function get_uploaded_files_editor(): ResponseInterface
    {
        $request = service('request');
        $search = $request->getGet('keyword') ?? '';

        $query = $this->uploadModel->where('user_id', user_id())
            ->orderBy('created_at', 'DESC');

        if (!empty($search)) {
            $this->applyMultiWordSearch($query, $search, ['file_name', 'file_original_name']);
        }

        $uploads = $query->paginate(21);
        $files = [];

        foreach ($uploads as $file) {
            // Construct paths based on the new storage structure
            $fileBaseName = pathinfo($file['file_name'], PATHINFO_FILENAME);
            // Handle GIF thumbs (animated GIF stored as .gif but thumbs are .webp) and SVG/PDF
            $thumbExt = $file['extension'];
            if ($thumbExt === 'gif') $thumbExt = 'webp';
            $thumbName = $fileBaseName . '-100x100.' . $thumbExt;

            // Check if it's a video
            if (isset($file['type']) && $file['type'] === 'video') {
                // Use a generic placeholder for video
                $thumbUrl = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxMDAgMTAwIiBmaWxsPSIjZWVlIj48cmVjdCB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIvPjxjaXJjbGUgY3g9IjUwIiBjeT0iNTAiIHI9IjIwIiBmaWxsPSIjY2NjIi8+PHBhdGggZD0iTTQ1IDQwTDU1IDUwTDQ1IDYwVjQweiIgZmlsbD0iIzY2NiIvPjwvc3ZnPg==';
            } elseif (isset($file['type']) && $file['type'] === 'pdf') {
                // Try PDF preview webp if exists, else generic PDF placeholder
                $previewName = $fileBaseName . '-preview.webp';
                $previewPath = FCPATH . self::UPLOAD_PATH . $file['upload_path'] . '/' . $previewName;
                if (file_exists($previewPath)) {
                    $thumbUrl = base_url(self::UPLOAD_PATH . $file['upload_path'] . '/' . $previewName);
                } else {
                    $thumbUrl = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxMDAgMTAwIiBmaWxsPSIjZmZmIj48cmVjdCB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgZmlsbD0iI2RhMTYxNiIvPjx0ZXh0IHg9IjUwIiB5PSI2MCIgZm9udC1zaXplPSIyNSIgZm9udC1mYW1pbHk9IkFyaWFsIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmaWxsPSIjZmZmIj5QREY8L3RleHQ+PC9zdmc+';
                }
            } elseif ($file['extension'] === 'svg') {
                $thumbUrl = base_url(self::UPLOAD_PATH . $file['upload_path'] . '/' . $file['file_name']);
            } else {
                $thumbUrl = base_url(self::UPLOAD_PATH . $file['upload_path'] . '/' . $thumbName);
            }

            $files[] = [
                'thumb' => $thumbUrl,
                'url' => base_url(self::UPLOAD_PATH . $file['upload_path'] . '/' . $file['file_name']),
                'id' => $file['id'],
                'alt' => $file['file_name'],
            ];
        }

        return $this->respond($files);
    }

    public function upload(): ResponseInterface
    {
        // Immediately release the session lock so other AJAX requests don't block this one, and this one doesn't block others
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $currentDate = date('Y/m');
        $storageFolder = FCPATH . self::UPLOAD_PATH . $currentDate;

        if (!is_dir($storageFolder) && !mkdir($storageFolder, 0775, true)) {
            return $this->failServerError("Failed to create directory: $storageFolder");
        }

        $flattenFiles = static function (array $items) use (&$flattenFiles): array {
            $files = [];
            foreach ($items as $item) {
                if (\is_array($item)) {
                    $files = array_merge($files, $flattenFiles($item));
                } elseif (\is_object($item)) {
                    $files[] = $item;
                }
            }

            return $files;
        };
        $files = $flattenFiles($this->request->getFiles());
        if ($files === []) {
            return $this->fail('Choose at least one file to upload.', 400);
        }
        if (\count($files) > 12) {
            return $this->fail('Upload no more than 12 files at a time.', 400);
        }

        $webpSupported = function_exists('imagewebp');
        $uploadedCount = 0;
        $uploadErrors = [];
        $batchData = [];

        try {
            foreach ($files as $file) {
                if (!$file->isValid()) {
                    continue;
                }

                $originalName = $file->getClientName();

                // Security gate: classify by MAGIC BYTES (finfo), never by client MIME.
                // Extension whitelist + size limits enforced for the detected type.
                [$valid, $type, $error, $realMime] = $this->validateUpload($file, $originalName);
                if (!$valid) {
                    $uploadErrors[] = $error;

                    continue;
                }

                $isImage = $type === 'image';
                $isVideo = $type === 'video';
                $isPdf = $type === 'pdf';

                $baseName = $this->sanitizeFileName(pathinfo($originalName, PATHINFO_FILENAME));
                $uniqueFileName = uniqid('news-') . '-' . $baseName;

                if ($isImage) {
                    // SVG — store sanitized as-is, no WebP conversion, placeholder thumbs
                    if ($realMime === 'image/svg+xml') {
                        $extension = 'svg';
                        $fileName = $uniqueFileName . '.svg';
                        $filePath = $storageFolder . '/' . $fileName;
                        $svgRaw = @file_get_contents($file->getTempName());
                        if ($svgRaw === false) {
                            throw new \RuntimeException("Failed to read SVG: $originalName");
                        }
                        $svgClean = $this->sanitizeSvgContent($svgRaw);
                        file_put_contents($filePath, $svgClean);
                        $type = 'image';
                        $file_size = filesize($filePath) ?: \strlen($svgClean);
                        // SVG has no raster thumbs — UI uses placeholder / original
                    } elseif ($realMime === 'image/gif' && self::PRESERVE_ANIMATED_GIF && $this->isAnimatedGif($file->getTempName())) {
                        // Animated GIF — preserve animation as GIF (GD cannot write animated WebP)
                        // Requirement: retain gif and its quality, generate WebP static thumbs
                        $extension = 'gif';
                        $fileName = $uniqueFileName . '.gif';
                        $filePath = $storageFolder . '/' . $fileName;
                        // Move original preserving animation (no re-encode, no quality loss)
                        $file->move($storageFolder, $fileName);
                        $file_size = filesize($filePath) ?: $file->getSize();
                        $type = 'image';
                        // Generate static WebP thumbs from first frame for high-contrast listings
                        // Re-read for thumb generation (first frame)
                        try {
                            foreach (self::THUMBNAIL_SIZES as $size) {
                                [$w, $h] = explode('x', $size);
                                $thumbPath = $storageFolder . '/' . $uniqueFileName . "-$size.webp";
                                $this->renderImage($filePath, $thumbPath, (int) $w, (int) $h, 'webp');
                            }
                            // For animated GIF we store main as gif but thumbs as webp — note extension remains gif for original
                        } catch (\Throwable $e) {
                            log_message('warning', 'Animated GIF thumb failed for '.$originalName.': '.$e->getMessage());
                        }
                        // Skip the generic webp conversion below — gif already handled
                    } else {
                        // Generally all raster images → WebP at QUALITY 80 in runtime, no keep original
                        $extension = $webpSupported ? 'webp' : 'jpg';
                        $fileName = $uniqueFileName . '.' . $extension;
                        $filePath = $storageFolder . '/' . $fileName;

                        // Process supported raster formats with CodeIgniter's configured image driver.
                        try {
                            $this->renderImage($file->getTempName(), $filePath, null, null, $extension);
                        } catch (\Throwable $e) {
                            log_message('error', 'Image read failed for '.$originalName.' ('.$realMime.'): '.$e->getMessage());
                            $uploadErrors[] = "$originalName: unsupported image format for conversion";
                            continue;
                        }

                        // Generate cropped thumbnails in the same format as the main image.
                        foreach (self::THUMBNAIL_SIZES as $size) {
                            [$w, $h] = explode('x', $size);
                            $thumbPath = $storageFolder . '/' . $uniqueFileName . "-$size.$extension";
                            $this->renderImage($file->getTempName(), $thumbPath, (int) $w, (int) $h, $extension);
                        }

                        $type = 'image';
                        $file_size = filesize($filePath) ?: 0;
                    }

                } elseif ($isVideo) {
                    // Extension must come from the validated allowlist (never the raw client extension)
                    $extension = $this->getSafeExtension($file, $realMime);
                    $fileName = $uniqueFileName . '.' . $extension;
                    $filePath = $storageFolder . '/' . $fileName;
                    $file->move($storageFolder, $fileName);
                    $type = 'video';
                    $file_size = filesize($filePath);

                } elseif ($isPdf) {
                    $extension = 'pdf';
                    $fileName = $uniqueFileName . '.pdf';
                    $filePath = $storageFolder . '/' . $fileName;
                    $file->move($storageFolder, $fileName);
                    $type = 'pdf';
                    $file_size = filesize($filePath);
                    // PDF options: try to generate WebP preview of first page (high-contrast) if Imagick + Ghostscript available
                    $imagickClass = 'Imagick';
                    if (class_exists($imagickClass)) {
                        try {
                            $pdfPreview = new $imagickClass();
                            $pdfPreview->setResolution(150, 150);
                            $pdfPreview->readImage($filePath . '[0]');
                            $pdfPreview->setImageFormat('webp');
                            $pdfPreview->thumbnailImage(400, 400, true);
                            $pdfPreview->setImageCompressionQuality(self::WEBP_QUALITY);
                            $previewPath = $storageFolder . '/' . $uniqueFileName . '-preview.webp';
                            $pdfPreview->writeImage($previewPath);
                            $pdfPreview->clear();
                        } catch (\Throwable $e) {
                            log_message('info', 'PDF preview not generated for '.$originalName.': '.$e->getMessage());
                        }
                    }
                    // Also generate 100x100 thumb for picker if preview exists, otherwise placeholder will be used
                } else {
                    continue;
                }

                $uploadedCount++;
                $imageInfo = $type === 'image' ? @getimagesize($filePath) : false;

                // Prepare data for batch insert
                $batchData[] = [
                    'user_id' => \intval(user_id()),
                    'file_name' => $fileName,
                    'file_original_name' => $originalName,
                    'extension' => $extension,
                    'type' => $type,
                    'upload_path' => $currentDate,
                    'path' => 'uploads/' . $currentDate . '/' . $fileName,
                    'name' => $fileName,
                    'mime_type' => $realMime ?? 'application/octet-stream',
                    'width' => $imageInfo[0] ?? 0,
                    'height' => $imageInfo[1] ?? 0,
                    'uploaded_by' => \intval(user_id()),
                    'file_size' => $file_size,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
            }

            // Batch insert into DB
            if (!empty($batchData)) {
                $this->uploadModel->insertBatch($batchData);
            }

            $this->response->setHeader('Connection', 'close');

            $message = "$uploadedCount files uploaded successfully";
            if (!empty($uploadErrors)) {
                $message .= '. Skipped: ' . implode('; ', $uploadErrors);
            }

            return $this->respond([
                'status' => 200,
                'message' => $message
            ]);

        } catch (\Exception $e) {
            log_message('error', 'Upload failed: ' . $e->getMessage());
            return $this->failServerError('Failed to process upload. Please try again with a valid file.');
        }
    }

    public function get_uploaded_files(): ResponseInterface
    {
        $request = service('request');
        $search = $request->getGet('search') ?? '';
        $perPage = 24;
        $page = (int) ($request->getGet('page') ?? 1);

        $query = $this->uploadModel->orderBy('created_at', 'DESC');
        //->where('user_id', user_id());

        if ($search !== '') {
            $this->applyMultiWordSearch($query, $search, ['file_name', 'file_original_name']);
        }

        $data = $query->paginate($perPage, 'default', $page);
        $pager = $this->uploadModel->pager;

        return $this->respond([
            'current_page' => $page,
            'data' => $data,
            'total' => $pager->getTotal(),
            'last_page' => $pager->getPageCount(),
            'has_more' => $page < $pager->getPageCount(),
            'next_page' => $page < $pager->getPageCount() ? $page + 1 : null,
            'prev_page_url' => $pager->getPreviousPageURI(),
            'next_page_url' => $pager->getNextPageURI(),
        ]);
    }

    public function get_preview_files(): ResponseInterface
    {
        $ids = $this->request->getPost('ids');
        if (empty($ids)) {
            return $this->respond([]);
        }

        $ids = explode(',', $ids);
        $files = $this->uploadModel->whereIn('id', $ids)->findAll();

        return $this->respond($files);
    }

    public function show_uploader()
    {
        return view('Travo\Admin\Views\Media\upp');
    }

    public function delete($id)
    {
        if ($this->_deleteFile($id)) {
            log_activity('delete', 'media', (int) $id, 'Deleted media file #' . $id);
            return redirect()->to('admin/uploader')->with('message', 'Media deleted successfully');
        }

        return redirect()->to('admin/uploader')->with('error', 'Failed to delete media record');
    }

    public function deleteBatch()
    {
        $ids = $this->request->getPost('ids');
        if (empty($ids)) {
            return $this->fail('No files selected');
        }

        if (!\is_array($ids)) {
            $ids = explode(',', $ids);
        }

        // 1. Fetch all files in ONE query
        $files = $this->uploadModel->whereIn('id', $ids)->findAll();

        if (empty($files)) {
            return $this->fail('No files found to delete');
        }

        $deletedCount = 0;
        $idsToDelete = [];
        $isAdmin = auth()->user()->inGroup('superadmin', 'admin');
        $currentUserId = user_id();

        foreach ($files as $file) {
            // Check permission
            if (!$isAdmin && $file['user_id'] != $currentUserId) {
                continue;
            }

            // Delete physical files — handle WebP thumbs for GIF and PDF preview
            $basePath = FCPATH . self::UPLOAD_PATH . $file['upload_path'] . '/';
            $fileBaseName = pathinfo($file['file_name'], PATHINFO_FILENAME);

            $filesToDelete = [$basePath . $file['file_name']];

            // Collect all sizes to ensure complete cleanup — handle both original ext and webp variant (for animated GIF)
            $allSizes = self::THUMBNAIL_SIZES;

            foreach ($allSizes as $size) {
                // Original extension thumb (standard)
                $filesToDelete[] = $basePath . $fileBaseName . "-$size." . $file['extension'];
                // WebP variant (for GIF original where thumbs are WebP)
                if ($file['extension'] !== 'webp') {
                    $filesToDelete[] = $basePath . $fileBaseName . "-$size.webp";
                }
                // Also clean legacy jpg/png variants if any
                $filesToDelete[] = $basePath . $fileBaseName . "-$size.jpg";
                $filesToDelete[] = $basePath . $fileBaseName . "-$size.png";
            }
            // PDF preview webp
            if (($file['type'] ?? '') === 'pdf' || $file['extension'] === 'pdf') {
                $filesToDelete[] = $basePath . $fileBaseName . '-preview.webp';
                $filesToDelete[] = $basePath . $fileBaseName . '-preview.jpg';
            }
            // SVG has no thumbs, but still clean any stray

            // Deduplicate
            $filesToDelete = array_unique($filesToDelete);

            foreach ($filesToDelete as $filePath) {
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }

            $idsToDelete[] = $file['id'];
            $deletedCount++;
        }

        // 2. Delete all records in ONE query
        if (!empty($idsToDelete)) {
            $this->uploadModel->whereIn('id', $idsToDelete)->delete();
            log_activity('bulk_delete', 'media', implode(',', $idsToDelete), "Bulk deleted {$deletedCount} media files");
            return $this->respond(['status' => 200, 'message' => "$deletedCount files deleted successfully"]);
        }

        return $this->fail('Failed to delete selected files');
    }

    private function _deleteFile($id)
    {
        $file = $this->uploadModel->find($id);

        if (!$file) {
            return false;
        }

        // Check permission
        if (!auth()->user()->inGroup('superadmin', 'admin') && $file['user_id'] != user_id()) {
            return false;
        }

        $basePath = FCPATH . self::UPLOAD_PATH . $file['upload_path'] . '/';
        $fileBaseName = pathinfo($file['file_name'], PATHINFO_FILENAME);

        $filesToDelete = [$basePath . $file['file_name']];

        // Collect all sizes — handle original + webp variant + legacy
        $allSizes = self::THUMBNAIL_SIZES;

        foreach ($allSizes as $size) {
            $filesToDelete[] = $basePath . $fileBaseName . "-$size." . $file['extension'];
            if ($file['extension'] !== 'webp') {
                $filesToDelete[] = $basePath . $fileBaseName . "-$size.webp";
            }
            $filesToDelete[] = $basePath . $fileBaseName . "-$size.jpg";
            $filesToDelete[] = $basePath . $fileBaseName . "-$size.png";
        }
        if (($file['type'] ?? '') === 'pdf' || $file['extension'] === 'pdf') {
            $filesToDelete[] = $basePath . $fileBaseName . '-preview.webp';
            $filesToDelete[] = $basePath . $fileBaseName . '-preview.jpg';
        }
        $filesToDelete = array_unique($filesToDelete);

        foreach ($filesToDelete as $filePath) {
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }

        return $this->uploadModel->delete($id);
    }

    private function sanitizeFileName($name)
    {
        // Replace spaces and special chars with underscores
        $name = preg_replace('/[^A-Za-z0-9_\-]/', '_', $name);
        // Remove multiple consecutive underscores
        $name = preg_replace('/_+/', '_', $name);
        return trim($name, '_');
    }

    public function getUrl(): ResponseInterface
    {
        $id = (int) $this->request->getGet('id');
        $file = $this->uploadModel->find($id);
        if (!$file) {
            return $this->respond(['url' => null], 404);
        }
        $url = base_url(self::UPLOAD_PATH . $file['upload_path'] . '/' . $file['file_name']);
        return $this->respond(['url' => $url]);
    }

    public function pickerGrid(): ResponseInterface
    {
        $request = service('request');
        $page = (int) ($request->getGet('page') ?? 1);
        $search = $request->getGet('search') ?? '';
        $selected = $request->getGet('selected') ?? '';
        $type = $request->getGet('type') ?? '';

        $selectedIds = array_values(array_filter(array_map('intval', explode(',', $selected))));

        $perPage = 24;
        $html = '';

        // ─── 1. Always render the currently selected files first ─────
        // (highlighted + pinned to the top of page 1 so the admin can
        // instantly spot what is already chosen)
        $selectedFiles = [];
        if (!empty($selectedIds) && $page === 1) {
            $selectedFiles = $this->uploadModel
                ->whereIn('id', $selectedIds)
                ->orderBy('created_at', 'DESC')
                ->findAll();
        }

        if (!empty($selectedFiles)) {
            $html .= '<div class="col-span-full flex items-center gap-2 mb-1">';
            $html .= '<span class="text-[10px] font-bold uppercase tracking-wider text-admin-muted">Selected</span>';
            $html .= '<div class="flex-1 h-px bg-admin-border"></div>';
            $html .= '</div>';

            foreach ($selectedFiles as $file) {
                $html .= $this->pickerItemHtml($file);
            }

            $html .= '<div class="col-span-full flex items-center gap-2 mt-2 mb-1">';
            $html .= '<span class="text-[10px] font-bold uppercase tracking-wider text-admin-muted">Media Library</span>';
            $html .= '<div class="flex-1 h-px bg-admin-border"></div>';
            $html .= '</div>';
        }

        // ─── 2. Main paginated grid (selected files excluded) ───────
        $query = $this->uploadModel->orderBy('created_at', 'DESC');

        if (!empty($search)) {
            $this->applyMultiWordSearch($query, $search, ['file_name', 'file_original_name']);
        }
        if (!empty($type) && \in_array($type, ['image', 'video', 'pdf'])) {
            $query->where('type', $type);
        }
        if (!empty($selectedIds)) {
            $query->whereNotIn('id', $selectedIds);
        }

        $total = $query->countAllResults(false);
        $uploads = $query->paginate($perPage, 'default', $page);
        $totalPages = (int) ceil($total / $perPage);

        if (empty($uploads)) {
            $html .= '<div class="col-span-full text-center py-8 text-admin-muted"><p>No files found</p></div>';
        } else {
            foreach ($uploads as $file) {
                $html .= $this->pickerItemHtml($file);
            }
        }

        // ─── 3. Pagination (account for pinned selected rows) ───────
        $pinned = ($page === 1 && !empty($selectedFiles)) ? \count($selectedFiles) : 0;
        if ($totalPages > 1 || $pinned > 0) {
            $html .= '<div class="col-span-full flex justify-center gap-1 mt-3">';
            for ($i = 1; $i <= $totalPages; $i++) {
                $active = $i === $page ? 'bg-blue-600 text-white' : 'bg-gray-100 text-admin-text hover:bg-gray-200';
                $html .= '<button class="size-8 rounded-lg text-xs font-medium ' . $active . '" onclick="loadGridPage(' . $i . ')">' . $i . '</button>';
            }
            $html .= '</div>';
        }

        return $this->response->setContentType('text/html')->setBody($html);
    }

    private function pickerItemHtml(array $file): string
    {
        $fileBaseName = pathinfo($file['file_name'], PATHINFO_FILENAME);
        $fullUrl = base_url(self::UPLOAD_PATH . $file['upload_path'] . '/' . $file['file_name']);

        // Determine thumb — handle video, GIF (thumbs are webp), PDF preview, SVG
        if (($file['type'] ?? '') === 'video') {
            $thumbUrl = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxMDAgMTAwIiBmaWxsPSIjZWVlIj48cmVjdCB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIvPjxjaXJjbGUgY3g9IjUwIiBjeT0iNTAiIHI9IjIwIiBmaWxsPSIjY2NjIi8+PHBhdGggZD0iTTQ1IDQwTDU1IDUwTDQ1IDYwVjQweiIgZmlsbD0iIzY2NiIvPjwvc3ZnPg==';
            // Use custom html for video
            $html = '<div class="media-grid-item media-picker-item aspect-square rounded-lg overflow-hidden bg-gray-900 flex items-center justify-center"';
            $html .= ' data-id="' . $file['id'] . '"';
            $html .= ' :class="$store.mediaManager.isSelected(' . $file['id'] . ') ? \'selected\' : \'\'"';
            $html .= ' @click="$store.mediaManager.toggleSelection(' . $file['id'] . ')"';
            $html .= ' @dblclick="$store.mediaManager.quickAdd(' . $file['id'] . ')">';
            $html .= '<div class="text-center p-2"><div class="size-8 mx-auto bg-white/20 rounded-full flex items-center justify-center text-white text-xs">▶</div><div class="text-[10px] text-white/80 truncate max-w-[80px] mt-1">' . esc($file['file_name']) . '</div></div>';
            $html .= '<div class="media-picker-check absolute top-1.5 left-1.5 size-5 bg-blue-600 text-white rounded-full items-center justify-center text-xs shadow">✓</div>';
            $html .= '</div>';
            return $html;
        } elseif (($file['type'] ?? '') === 'pdf' || $file['extension'] === 'pdf') {
            $previewName = $fileBaseName . '-preview.webp';
            $previewPath = FCPATH . self::UPLOAD_PATH . $file['upload_path'] . '/' . $previewName;
            if (file_exists($previewPath)) {
                $thumbUrl = base_url(self::UPLOAD_PATH . $file['upload_path'] . '/' . $previewName);
            } else {
                // PDF placeholder will be handled in JS fallback; use generic
                $thumbUrl = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxMDAgMTAwIiBmaWxsPSIjZmZmIj48cmVjdCB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgZmlsbD0iI2RhMTYxNiIvPjx0ZXh0IHg9IjUwIiB5PSI2MCIgZm9udC1zaXplPSIyNSIgZm9udC1mYW1pbHk9IkFyaWFsIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmaWxsPSIjZmZmIj5QREY8L3RleHQ+PC9zdmc+';
                // Return early with placeholder thumb
                $html = '<div class="media-grid-item media-picker-item aspect-square rounded-lg overflow-hidden bg-red-50 flex items-center justify-center"';
                $html .= ' data-id="' . $file['id'] . '"';
                $html .= ' :class="$store.mediaManager.isSelected(' . $file['id'] . ') ? \'selected\' : \'\'"';
                $html .= ' @click="$store.mediaManager.toggleSelection(' . $file['id'] . ')"';
                $html .= ' @dblclick="$store.mediaManager.quickAdd(' . $file['id'] . ')">';
                $html .= '<div class="text-center p-2"><div class="text-xs font-bold text-red-600">PDF</div><div class="text-[10px] text-gray-500 truncate max-w-[80px]">' . esc($file['file_name']) . '</div></div>';
                $html .= '<div class="media-picker-check absolute top-1.5 left-1.5 size-5 bg-blue-600 text-white rounded-full items-center justify-center text-xs shadow">✓</div>';
                $html .= '</div>';
                return $html;
            }
        } elseif ($file['extension'] === 'svg') {
            $thumbUrl = $fullUrl;
        } elseif ($file['extension'] === 'gif') {
            // Animated GIF thumbs are WebP static
            $thumbUrl = base_url(self::UPLOAD_PATH . $file['upload_path'] . '/' . $fileBaseName . '-100x100.webp');
        } else {
            $thumbName = $fileBaseName . '-100x100.' . $file['extension'];
            $thumbUrl = base_url(self::UPLOAD_PATH . $file['upload_path'] . '/' . $thumbName);
        }

        // Selection state is bound reactively (:class + CSS pairing) so a
        // single click instantly highlights the item — no server round-trip.
        $html = '<div class="media-grid-item media-picker-item aspect-square rounded-lg overflow-hidden"';
        $html .= ' data-id="' . $file['id'] . '"';
        $html .= ' :class="$store.mediaManager.isSelected(' . $file['id'] . ') ? \'selected\' : \'\'"';
        $html .= ' @click="$store.mediaManager.toggleSelection(' . $file['id'] . ')"';
        $html .= ' @dblclick="$store.mediaManager.quickAdd(' . $file['id'] . ')">';
        $placeholder = 'data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22><rect fill=%22%23eef2f7%22 width=%22100%22 height=%22100%22/><text x=%2250%25%22 y=%2254%25%22 text-anchor=%22middle%22 font-size=%2212%22 fill=%22%2394a3b8%22>TheNosePad</text></svg>';
        $html .= '<img src="' . $thumbUrl . '" alt="' . esc($file['file_name']) . '" class="w-full h-full object-cover cursor-pointer" loading="lazy"';
        $html .= ' onerror="this.src=\'' . $fullUrl . '\'; this.onerror=function(){this.src=\'' . $placeholder . '\'; this.onerror=null;};">';
        $html .= '<div class="media-picker-check absolute top-1.5 left-1.5 size-5 bg-blue-600 text-white rounded-full items-center justify-center text-xs shadow">✓</div>';
        $html .= '</div>';
        return $html;
    }
}
