<?php

namespace Travo\Admin\Controllers;

use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Config\Services;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Database;
use Travo\Admin\Models\PostModel;
use Travo\Admin\Models\PostCategoryRelation;
use Travo\Admin\Models\PostCategoryModel;
use Travo\Admin\Models\TagModel;
use Travo\Admin\Models\PostTagModel;
use Travo\Admin\Models\UploadModel;

class PostsController extends Controller
{
    use ResponseTrait;

    public const POST_TYPES = ['article', 'video', 'photo', 'opinion', 'interview', 'feature'];

    protected $postModel;
    protected $postCategoryModel;
    protected $categoryModel;
    protected $tagModel;
    protected $postTagModel;

    public function __construct()
    {
        $this->postModel = new PostModel();
        $this->postCategoryModel = new PostCategoryRelation();
        $this->categoryModel = new PostCategoryModel();
        $this->tagModel = new TagModel();
        $this->postTagModel = new PostTagModel();
    }

    /**
     * Deny access unless the current user holds the permission.
     */
    protected function denyUnless(string $permission): ?RedirectResponse
    {
        if (!auth()->user()?->can($permission)) {
            return redirect()->to('/admin/posts')->with('error', lang('notAuthorized'));
        }
        return null;
    }

    public function index()
    {
        if ($deny = $this->denyUnless('content.manage')) {
            return $deny;
        }

        $data['page_heading'] = 'Manage Posts';

        $keyword = trim($this->request->getGet('keyword') ?? '');
        $statusFilter = $this->request->getGet('status');
        $typeFilter = $this->request->getGet('post_type');
        $categoryFilter = $this->request->getGet('category');
        $dateFrom = $this->request->getGet('date_from');
        $dateTo = $this->request->getGet('date_to');

        $data['keyword'] = $keyword;
        $data['selected_status'] = $statusFilter ?? 'all';
        $data['selected_type'] = $typeFilter ?? '';
        $data['selected_category'] = $categoryFilter ?? '';
        $data['date_from'] = $dateFrom;
        $data['date_to'] = $dateTo;

        $builder = $this->postModel
            ->select("posts.*, CASE WHEN TRIM(CONCAT_WS(' ', COALESCE(users.first_name,''), COALESCE(users.last_name,''))) <> '' THEN TRIM(CONCAT_WS(' ', COALESCE(users.first_name,''), COALESCE(users.last_name,''))) ELSE users.username END AS author_name")
            ->join('users', 'users.id = posts.author_id', 'left')
            ->orderBy('posts.created_at', 'DESC');

        if (\in_array($statusFilter, ['active', '1'], true)) {
            $builder->where('posts.status', 1);
        } elseif (\in_array($statusFilter, ['inactive', '0'], true)) {
            $builder->where('posts.status', 0);
        } elseif ($statusFilter === 'scheduled') {
            $builder->where('posts.published_at >', date('Y-m-d H:i:s'));
        } elseif ($statusFilter === 'breaking') {
            $builder->where('posts.is_breaking_news', 1);
        } elseif ($statusFilter === 'trending') {
            $builder->where('posts.is_trending', 1);
        }

        if (!empty($typeFilter)) {
            $builder->where('posts.post_type', $typeFilter);
        }

        if (!empty($categoryFilter) && is_numeric($categoryFilter)) {
            $postIds = array_column(
                $this->postCategoryModel
                    ->select('post_id')
                    ->where('category_id', (int)$categoryFilter)
                    ->findAll(5000),
                'post_id'
            );
            if (!empty($postIds)) {
                $builder->whereIn('posts.id', $postIds);
            } else {
                $builder->where('posts.id', 0); // no posts in this category
            }
        }

        if (!empty($dateFrom)) {
            $builder->where('posts.created_at >=', $dateFrom . ' 00:00:00');
        }
        if (!empty($dateTo)) {
            $builder->where('posts.created_at <=', $dateTo . ' 23:59:59');
        }

        if (!empty($keyword)) {
            $tagPostIds = array_column(
                $this->postTagModel->select('post_tags.post_id')
                    ->join('tags', 'tags.id = post_tags.tag_id')
                    ->like('tags.name', $keyword)
                    ->findAll(1000),
                'post_id'
            );

            // Title + tags column only — a LIKE over the content column
            // full-scans the largest table in the system on every keystroke.
            $builder->groupStart()
                    ->like('posts.title', $keyword)
                    ->orLike('posts.tags', $keyword)
                    ->orWhereIn('posts.id', $tagPostIds)
                    ->groupEnd();
        }

        $data['posts'] = $builder->paginate(20, 'default', $this->request->getGet('page') ?? 1);
        $data['pager'] = $this->postModel->pager;

        $postIds = array_column($data['posts'], 'id');
        $categoriesMap = [];
        if (!empty($postIds)) {
            $cats = $this->postCategoryModel
                ->select('post_categories.post_id, blog_categories.title')
                ->join('blog_categories', 'blog_categories.id = post_categories.category_id')
                ->whereIn('post_categories.post_id', $postIds)
                ->findAll();

            foreach ($cats as $cat) {
                $categoriesMap[$cat['post_id']][] = $cat['title'];
            }
        }
        $data['categories_map'] = $categoriesMap;

        $data['filter_categories'] = $this->categoryModel->orderBy('sort_order', 'ASC')->orderBy('title', 'ASC')->findAll();
        $data['post_types'] = self::POST_TYPES;

        return view('Travo\Admin\Views\Posts\index', $data);
    }

    public function create()
    {
        if ($deny = $this->denyUnless('content.manage')) {
            return $deny;
        }

        $data['page_heading'] = 'Create Post';
        $data['post'] = [];
        $data['cats'] = $this->categoryModel->orderBy('title', 'ASC')->findAll();
        $data['categoryIds'] = [];
        $data['images'] = UploadModel::libraryImages();
        $data['post_types'] = self::POST_TYPES;
        $data['all_tags'] = $this->tagModel->getWithCounts(30);
        return view('Travo\Admin\Views\Posts\form', $data);
    }

    public function save(): RedirectResponse
    {
        if ($deny = $this->denyUnless('content.manage')) {
            return $deny;
        }

        $validation = Services::validation();

        $rules = [
            'title' => 'required|min_length[3]|max_length[255]',
            'content' => 'required',
            'slug' => 'permit_empty|is_unique[posts.slug,id,0]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $validation->getErrors());
        }

        $db = Database::connect();
        $db->transStart();

        try {
            $postData = $this->prepareCommonPostData();
            $this->postModel->save($postData);
            $postId = $this->postModel->getInsertID();

            if (!$postId) {
                throw new \RuntimeException('Failed to insert post');
            }

            $this->processCategories($postId);
            $this->processTags($postId);

            $db->transComplete();
            if (!$db->transStatus()) {
                throw new \RuntimeException('Database transaction failed');
            }

            $this->clearPostCaches($postId);
            log_activity('create', 'posts', $postId, 'Created post: ' . $postData['title']);

            return redirect()->to('/admin/posts')
                ->with('message', 'Post Created Successfully');
        } catch (\Exception $e) {
            $db->transRollback();
            log_message('error', 'Error creating post: ' . $e->getMessage());
            return redirect()->back()->withInput()
                ->with('error', 'Error creating post: ' . $e->getMessage());
        }
    }

    public function update(int $id): RedirectResponse
    {
        if ($deny = $this->denyUnless('content.manage')) {
            return $deny;
        }

        $post = $this->postModel->find($id);
        if (!$post) {
            return redirect()->to('/admin/posts')->with('error', 'Post not found');
        }

        $validation = Services::validation();

        $rules = [
            'title' => 'required|min_length[3]|max_length[255]',
            'content' => 'required',
            'slug' => "permit_empty|is_unique[posts.slug,id,$id]",
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $validation->getErrors());
        }

        $db = Database::connect();
        $db->transStart();

        try {
            $postData = $this->prepareCommonPostData();
            // Slug and author are immutable after creation — keep originals
            $postData['slug'] = $post['slug'];
            $postData['author_id'] = $post['author_id'];
            // Never overwrite created_at unless explicitly posted; keep original timestamp
            if (empty($this->request->getPost('created_at')) && !empty($post['created_at'])) {
                $postData['created_at'] = $post['created_at'];
            }
            $this->postModel->update($id, $postData);
            $this->processCategories($id);
            $this->processTags($id);
            $this->clearPostCaches($id);

            $db->transComplete();
            if (!$db->transStatus()) {
                throw new \RuntimeException('Database transaction failed');
            }

            log_activity('update', 'posts', $id, 'Updated post: ' . $post['title']);

            return redirect()->to('/admin/posts')
                ->with('message', 'Post Updated Successfully');
        } catch (\Exception $e) {
            $db->transRollback();
            log_message('error', "Error updating post ID $id: " . $e->getMessage());
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    protected function prepareCommonPostData(): array
    {
        $title = trim((string) $this->request->getPost('title'));
        $postIdRaw = $this->request->getPost('id');
        $postId = $postIdRaw ? (int)$postIdRaw : null;

        // Current slug style is the numeric date stamp (ymdHis), e.g. 260811034136.
        // The slug is generated once at creation and never changed afterwards
        // (update() forces the original value; the edit form posts it readonly).
        $slug = $this->request->getPost('slug') ?: date('ymdHis');
        $slug = $this->createUniqueSlug($slug, $postId);

        $publishedAtRaw = $this->request->getPost('published_at');
        $publishedAt = (!empty($publishedAtRaw) && strtotime($publishedAtRaw))
            ? date('Y-m-d H:i:s', strtotime($publishedAtRaw))
            : null;

        $breakingExpiresRaw = $this->request->getPost('breaking_news_expires_at');
        $breakingExpires = (!empty($breakingExpiresRaw) && strtotime($breakingExpiresRaw))
            ? date('Y-m-d H:i:s', strtotime($breakingExpiresRaw))
            : null;

        $createdAtRaw = $this->request->getPost('created_at');
        $createdAt = (!empty($createdAtRaw) && strtotime($createdAtRaw))
            ? date('Y-m-d H:i:s', strtotime($createdAtRaw))
            : date('Y-m-d H:i:s');

        $updatedAtRaw = $this->request->getPost('datetime_updated');
        $updatedAt = (!empty($updatedAtRaw) && strtotime($updatedAtRaw))
            ? date('Y-m-d H:i:s', strtotime($updatedAtRaw))
            : date('Y-m-d H:i:s');

        $tags = $this->request->getPost('tags');
        if (\is_array($tags)) {
            $tags = implode(',', $this->tagModel->normalizeNames($tags));
        }

        return [
            'author_id'                => \intval(user_id()),
            'title'                    => $title,
            'slug'                     => $slug,
            'content'                  => \Travo\Admin\Libraries\ContentCleaner::clean($this->request->getPost('content') ?? ''),
            'excerpt'                  => mb_strimwidth(trim((string) ($this->request->getPost('excerpt') ?? '')), 0, 500, '…'),
            'status'                   => (int)((int)($this->request->getPost('status') ?? 0) === 1),
            'thumbnail_id'             => $this->sanitizeMediaId($this->request->getPost('thumbnail_id')),
            'is_featured'              => (int)(bool)($this->request->getPost('is_featured') ?? 0),
            'is_breaking_news'          => (int)(bool)($this->request->getPost('is_breaking_news') ?? 0),
            'show_thumbnail_in_featured'=> (int)(bool)($this->request->getPost('show_thumbnail_in_featured') ?? 1),
            'breaking_news_expires_at'  => $breakingExpires,
            'is_trending'              => (int)(bool)($this->request->getPost('is_trending') ?? 0),
            'is_pinned'                => (int)(bool)($this->request->getPost('is_pinned') ?? 0),
            'post_type'                => \in_array($this->request->getPost('post_type'), self::POST_TYPES, true)
                                            ? $this->request->getPost('post_type')
                                            : 'article',
            'meta_title'               => $this->request->getPost('meta_title') ?: $title,
            'meta_desc'                => mb_strimwidth((string) ($this->request->getPost('meta_desc') ?: strip_tags((string) $this->request->getPost('content'))), 0, 300, '…'),
            'source_name'              => mb_strimwidth(trim((string) ($this->request->getPost('source_name') ?? '')), 0, 255),
            'source_url'               => $this->sanitizeHttpUrl($this->request->getPost('source_url')),
            'featured_video'           => $this->sanitizeEmbedUrl($this->request->getPost('featured_video')),
            'gallery_images'           => $this->sanitizeMediaIds($this->request->getPost('gallery_images')),
            'tags'                     => $tags ?? '',
            'author_name'              => mb_strimwidth(trim((string) ($this->request->getPost('author_name') ?? '')), 0, 255),
            'author_role'              => mb_strimwidth(trim((string) ($this->request->getPost('author_role') ?? '')), 0, 255),
            'author_bio'               => trim((string) ($this->request->getPost('author_bio') ?? '')),
            'author_avatar'            => trim((string) ($this->request->getPost('author_avatar') ?? '')),
            'created_at'               => $createdAt,
            'updated_at'               => $updatedAt,
            'published_at'             => $publishedAt,
        ];
    }

    /**
     * Thumbnail: strict INT FK to uploads.id — null if empty/invalid.
     * Accepts numeric string or int, verifies >0. Optionally verify existence via UploadModel.
     */
    protected function sanitizeMediaId($raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $raw = trim((string)$raw);
        if ($raw === '' || $raw === '0') {
            return null;
        }
        // Allow numeric string only — reject paths/URLs (legacy data handled via get_image_by_id)
        if (!ctype_digit($raw)) {
            return null;
        }
        $id = (int)$raw;
        return $id > 0 ? $id : null;
    }

    /**
     * Only allow internal media paths (uploads/...) or absolute http(s) URLs.
     * @deprecated Kept for backward compatibility — use sanitizeMediaId for thumbnail/gallery
     */
    protected function sanitizeMediaPath(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }
        if (preg_match('#^(https?://|/?uploads/)#i', $path)) {
            return htmlspecialchars(strip_tags($path), ENT_QUOTES, 'UTF-8');
        }
        return null;
    }

    /**
     * Keep only plain http(s) URLs.
     */
    protected function sanitizeHttpUrl(?string $url): string
    {
        $url = trim((string) $url);
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return '';
        }
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
    }

    /**
     * Featured video: accept YouTube/Vimeo watch or embed URLs only.
     */
    protected function sanitizeEmbedUrl(?string $url): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }
        if (filter_var($url, FILTER_VALIDATE_URL)
            && preg_match('#^https://(www\.)?(youtube\.com|youtu\.be|player\.vimeo\.com)/#i', $url)) {
            return $url;
        }
        return '';
    }

    /**
     * Gallery: comma-separated list of media IDs (INTs). Returns CSV of valid ints.
     */
    protected function sanitizeMediaIds($raw): string
    {
        if (empty($raw)) {
            return '';
        }
        // Accept array (mediaPicker multiple) or CSV string
        $parts = \is_array($raw) ? $raw : explode(',', (string)$raw);
        $clean = [];
        foreach ($parts as $part) {
            $id = $this->sanitizeMediaId($part);
            if ($id !== null) {
                $clean[] = (string)$id;
            }
        }
        // Also handle JSON-encoded arrays edge case
        if (empty($clean) && \is_string($raw) && str_starts_with(trim($raw), '[')) {
            $decoded = json_decode($raw, true);
            if (\is_array($decoded)) {
                foreach ($decoded as $part) {
                    $id = $this->sanitizeMediaId($part);
                    if ($id !== null) $clean[] = (string)$id;
                }
            }
        }
        return implode(',', $clean);
    }

    /**
     * Gallery: comma-separated list of internal media paths / http(s) URLs.
     * @deprecated Use sanitizeMediaIds
     */
    protected function sanitizeGalleryPaths(?string $raw): string
    {
        if (empty($raw)) {
            return '';
        }
        $parts = explode(',', (string) $raw);
        $clean = [];
        foreach ($parts as $part) {
            $part = $this->sanitizeMediaPath($part);
            if ($part !== null && $part !== '') {
                $clean[] = $part;
            }
        }
        return implode(',', $clean);
    }

    private function createUniqueSlug(string $originalSlug, ?int $postId = null): string
    {
        $slug = $originalSlug;
        $counter = 1;

        while (true) {
            $builder = $this->postModel->where('slug', $slug);
            if ($postId !== null) {
                $builder->where('id !=', $postId);
            }
            if ($builder->countAllResults() === 0) {
                break;
            }

            $slug = $originalSlug . '-' . $counter++;
            if ($counter > 1000) {
                $slug = $originalSlug . '-' . time();
                break;
            }
        }

        return $slug;
    }

    protected function processCategories(int $postId): void
    {
        $categories = array_filter($this->request->getPost('category_ids') ?? $this->request->getPost('categories') ?? []);

        $existing = array_column(
            $this->postCategoryModel->where('post_id', $postId)->findAll(),
            'category_id'
        );

        $toAdd = array_diff($categories, $existing);
        $toRemove = array_diff($existing, $categories);

        if ($toRemove) {
            $this->postCategoryModel
                ->whereIn('category_id', $toRemove)
                ->where('post_id', $postId)
                ->delete();
        }

        if ($toAdd) {
            $insertData = array_map(fn($c) => [
                'post_id' => $postId,
                'category_id' => (int)$c
            ], $toAdd);

            $this->postCategoryModel->insertBatch($insertData);
        }
    }

    protected function processTags(int $postId): void
    {
        $rawTags = $this->request->getPost('tags') ?? [];
        if (\is_string($rawTags)) {
            $rawTags = explode(',', $rawTags);
        }
        $rawTags = (array)$rawTags;

        $names = $this->tagModel->normalizeNames($rawTags);
        $tagIds = [];
        foreach ($names as $name) {
            $tagIds[] = $this->tagModel->findOrCreate($name);
        }

        $this->postTagModel->replaceForPost($postId, $tagIds);
    }

    protected function clearPostCaches(int $postId): void
    {
        $cache = Services::cache();
        $cacheKeys = [
            "post_details_{$postId}",
            "post_slug_{$postId}",
            "post_categories_{$postId}",
        ];

        foreach ($cacheKeys as $key) {
            $cache->delete($key);
        }

        // Targeted static-HTML invalidation: this post's page, the homepage,
        // blog listing, and every category page the post belongs to. This
        // re-caches only what changed — no full wipe.
        try {
            $post = $this->postModel->withDeleted()->find($postId);
            $static = new \Travo\FrontEnd\Controllers\LIbraries\StaticPageCache();

            if ($post && !empty($post['slug'])) {
                $static->deletePath('news/' . $post['slug']);
                $static->deletePath('index'); // homepage
                $static->deletePath('blog');
            }

            $catIds = array_column(
                $this->postCategoryModel->where('post_id', $postId)->findAll(),
                'category_id'
            );
            if (!empty($catIds)) {
                $categoryModel = new PostCategoryModel();
                $cats = $categoryModel
                    ->whereIn('id', array_map('intval', $catIds))
                    ->findAll();
                foreach ($cats as $cat) {
                    $static->deletePath('category/' . $cat['slug']);
                    $static->deletePath('blog/category/' . $cat['slug']);
                }
            }
        } catch (\Throwable $e) {
            log_message('error', 'clearPostCaches static invalidation failed: ' . $e->getMessage());
        }
    }

    public function edit(int $id)
    {
        if ($deny = $this->denyUnless('content.manage')) {
            return $deny;
        }

        $post = $this->postModel->find($id);
        if (!$post) {
            return redirect()->to('/admin/posts')->with('error', 'Post not found');
        }

        $categories = $this->categoryModel->orderBy('title', 'ASC')->findAll();

        $selectedCategories = array_column(
            $this->postCategoryModel->where('post_id', $id)->findAll(),
            'category_id'
        );

        $data = [
            'post' => $post,
            'page_heading' => 'Update Post',
            'cats' => $categories,
            'categoryIds' => $selectedCategories,
            'images' => UploadModel::libraryImages(),
            'post_types' => self::POST_TYPES,
            'all_tags' => $this->tagModel->getWithCounts(30),
            'selected_tags' => array_column($this->postTagModel->getTagsForPost($id), 'name'),
        ];

        return view('Travo\Admin\Views\Posts\form', $data);
    }

    public function preview(int $id)
    {
        if ($deny = $this->denyUnless('content.manage')) {
            return $deny;
        }

        $post = $this->postModel
            ->select("posts.*, CASE WHEN TRIM(CONCAT_WS(' ', COALESCE(users.first_name,''), COALESCE(users.last_name,''))) <> '' THEN TRIM(CONCAT_WS(' ', COALESCE(users.first_name,''), COALESCE(users.last_name,''))) ELSE users.username END AS author_name")
            ->join('users', 'users.id = posts.author_id', 'left')
            ->find($id);

        if (!$post) {
            return redirect()->to('/admin/posts')->with('error', 'Post not found');
        }

        $categories = $this->postCategoryModel
            ->select('blog_categories.title')
            ->join('blog_categories', 'blog_categories.id = post_categories.category_id')
            ->where('post_categories.post_id', $id)
            ->findAll();

        $data = [
            'post' => $post,
            'author_name' => $post['author_name'],
            'categories' => $categories,
            'page_heading' => 'Preview Post',
        ];

        return view('Travo\Admin\Views\Posts\preview', $data);
    }

    public function updatePublished(): ResponseInterface
    {
        try {
            $json = $this->request->getJSON(true);
            $id = (int)($json['id'] ?? 0);
            $status = $json['status'] ?? null;

            if ($id <= 0 || !\in_array((string)$status, ['0', '1'], true) || !$this->postModel->find($id)) {
                return $this->fail('Invalid input', 400);
            }

            $this->postModel->update($id, ['status' => (int)$status]);
            $this->clearPostCaches($id);
            log_activity('status_change', 'posts', $id, 'Publish status set to ' . (int)$status);

            return $this->respond([
                'status' => 200,
                'message' => 'Post status updated successfully'
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Error updating post status: ' . $e->getMessage());
            return $this->failServerError('Update failed: ' . $e->getMessage());
        }
    }

    /**
     * Shared handler for the boolean flag endpoints.
     */
    protected function toggleFlag(string $field, ?callable $extraData = null): ResponseInterface
    {
        try {
            if (auth()->user()?->can('content.manage') !== true) {
                return $this->failForbidden('Not authorized');
            }

            $data = $this->request->getJSON(true);

            if (!isset($data['id'], $data['status'])) {
                return $this->fail('Invalid request', 400);
            }

            $id = (int)$data['id'];
            $status = (int)(bool)$data['status'];

            if ($id <= 0 || !$this->postModel->find($id)) {
                return $this->fail('Invalid request', 400);
            }

            $updateData = [$field => $status];
            if ($extraData !== null) {
                $extra = $extraData($status, $data);
                if (\is_array($extra)) {
                    $updateData = array_merge($updateData, $extra);
                }
            }

            $this->postModel->update($id, $updateData);
            $this->clearPostCaches($id);

            return $this->respond([
                'status' => 200,
                'message' => 'Updated successfully',
                'active' => $status,
            ]);
        } catch (\Exception $e) {
            log_message('error', "Error toggling {$field}: " . $e->getMessage());
            return $this->failServerError('Update failed');
        }
    }

    public function updateFeatured(): ResponseInterface
    {
        return $this->toggleFlag('is_featured');
    }

    public function updateBreaking(): ResponseInterface
    {
        return $this->toggleFlag('is_breaking_news', function (int $status, array $data): ?array {
            $expiresAt = $data['expires_at'] ?? null;
            if ($status && $expiresAt && strtotime($expiresAt)) {
                return ['breaking_news_expires_at' => date('Y-m-d H:i:s', strtotime($expiresAt))];
            }
            if (!$status) {
                return ['breaking_news_expires_at' => null];
            }
            return null;
        });
    }

    public function updateTrending(): ResponseInterface
    {
        return $this->toggleFlag('is_trending');
    }

    public function delete(int $id): RedirectResponse
    {
        if ($deny = $this->denyUnless('content.manage')) {
            return $deny;
        }

        $post = $this->postModel->find($id);
        if (!$post) {
            return redirect()->to('/admin/posts')->with('error', 'Post not found');
        }

        $db = Database::connect();
        $db->transStart();

        try {
            $this->postCategoryModel->where('post_id', $id)->delete();
            $this->postTagModel->where('post_id', $id)->delete();
            $this->postModel->delete($id);

            $db->transComplete();
            $this->clearPostCaches($id);
            log_activity('delete', 'posts', $id, 'Deleted post: ' . ($post['title'] ?? ''));

            return redirect()->to('/admin/posts')->with('message', 'Post Deleted Successfully');
        } catch (\Exception $e) {
            $db->transRollback();
            log_message('error', 'Error deleting post ID ' . $id . ': ' . $e->getMessage());
            return redirect()->to('/admin/posts')->with('error', $e->getMessage());
        }
    }

    public function bulkDelete(): ResponseInterface
    {
        if (auth()->user()?->can('content.manage') !== true) {
            return $this->failForbidden('Not authorized');
        }

        // IDs must arrive via POST body (form ids[] or JSON) — never query string.
        $ids = $this->request->getPost('ids');
        if (\is_string($ids)) {
            $ids = explode(',', $ids);
        }
        if (!\is_array($ids) || empty($ids)) {
            return $this->fail('No IDs provided', 400);
        }
        $ids = array_values(array_filter(array_map('intval', $ids)));

        if (empty($ids)) {
            return $this->fail('Invalid IDs', 400);
        }

        $db = Database::connect();
        $db->transStart();

        try {
            $this->postCategoryModel->whereIn('post_id', $ids)->delete();
            $this->postTagModel->whereIn('post_id', $ids)->delete();
            $this->postModel->whereIn('id', $ids)->delete();

            $db->transComplete();

            foreach ($ids as $id) {
                $this->clearPostCaches($id);
            }
            log_activity('bulk_delete', 'posts', implode(',', $ids), 'Bulk deleted ' . \count($ids) . ' posts');

            session()->setFlashdata('success', \count($ids) . ' posts deleted successfully.');
            return $this->respond(['status' => 200, 'message' => 'Posts deleted successfully']);
        } catch (\Exception $e) {
            $db->transRollback();
            log_message('error', 'Error bulk deleting posts: ' . $e->getMessage());
            return $this->failServerError($e->getMessage());
        }
    }

    public function tagsSearch(): ResponseInterface
    {
        $q = trim($this->request->getGet('q') ?? '');
        $tags = $this->tagModel->search($q, 15);

        return $this->respond(array_map(fn($t) => [
            'id'   => (int)$t['id'],
            'name' => $t['name'],
            'slug' => $t['slug'],
        ], $tags));
    }

    public function autosave(): ResponseInterface
    {
        if (!$this->request->isAJAX()) {
            return $this->fail('Invalid request', 400);
        }

        $currentUser = auth()->user();
        if (!$currentUser || $currentUser->can('content.manage') !== true) {
            return $this->failForbidden('Not authorized');
        }

        helper('url');

        $id = (int) ($this->request->getPost('id') ?: 0);
        $title = trim((string) $this->request->getPost('title'));

        if ($title === '') {
            return $this->fail('Title is required for autosave', 400);
        }

        // Existing posts may only be autosaved by their author or an editor.
        if ($id > 0) {
            $existing = $this->postModel->find($id);
            if (!$existing) {
                return $this->fail('Post not found', 404);
            }
            if ((int)$existing['author_id'] !== (int)user_id() && $currentUser->can('content.manage') !== true) {
                return $this->failForbidden('Not authorized');
            }
        }

        $tags = $this->request->getPost('tags');
        if (\is_array($tags)) {
            $tags = implode(',', $this->tagModel->normalizeNames($tags));
        }

        $data = [
            'title'    => $title,
            'content'  => \Travo\Admin\Libraries\ContentCleaner::clean($this->request->getPost('content') ?? ''),
            'excerpt'  => mb_strimwidth(trim((string) ($this->request->getPost('excerpt') ?? '')), 0, 500, '…'),
            'tags'     => $tags ?? '',
            'meta_title' => $this->request->getPost('meta_title') ?: $title,
            'meta_desc'  => mb_strimwidth((string) ($this->request->getPost('meta_desc') ?: strip_tags((string) $this->request->getPost('content'))), 0, 300, '…'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        // Persist thumbnail if supplied via autosave (mediaPicker now sends INT)
        $thumbRaw = $this->request->getPost('thumbnail_id');
        if ($thumbRaw !== null && $thumbRaw !== '') {
            $thumbId = $this->sanitizeMediaId($thumbRaw);
            if ($thumbId !== null) {
                $data['thumbnail_id'] = $thumbId;
            }
        }

        if ($id === 0) {
            $data['author_id'] = \intval(user_id());
            $data['status'] = 0;
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['slug'] = $this->createUniqueSlug(date('ymdHis'));

            try {
                $this->postModel->insert($data);
                $id = (int) $this->postModel->getInsertID();
            } catch (\Exception $e) {
                return $this->failServerError($e->getMessage());
            }
        } else {
            // Slug and author are immutable on existing posts — never touch them here.
            unset($data['slug']);

            try {
                $this->postModel->update($id, $data);
            } catch (\Exception $e) {
                return $this->failServerError($e->getMessage());
            }
        }

        try {
            $this->processTags($id);
        } catch (\Exception $e) {
            // Tag sync must never break the autosave response
            log_message('error', 'Autosave tag sync failed for post ' . $id . ': ' . $e->getMessage());
        }

        return $this->respond([
            'status' => 200,
            'message' => 'Draft saved successfully',
            'id' => $id,
            'timestamp' => date('H:i:s')
        ]);
    }
}