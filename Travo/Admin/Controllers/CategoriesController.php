<?php

namespace Travo\Admin\Controllers;

use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Config\Services;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Travo\Admin\Models\PostCategoryModel;
use Travo\Admin\Models\PostCategoryRelation;

class CategoriesController extends Controller
{
    use ResponseTrait;

    public function index()
    {
        if (!auth()->user()?->can('content.manage')) {
            return redirect()->to('/admin/dashboard')->with('error', 'Not authorized');
        }

        $categoryModel = new PostCategoryModel();
        $categories = $categoryModel->orderBy('sort_order', 'ASC')->orderBy('title', 'ASC')->findAll();

        $titleMap = $categoryModel->getTitleMap();
        $postCounts = $categoryModel->getPostCounts();

        foreach ($categories as &$category) {
            $category['parent_name'] = $titleMap[(int)$category['parent_id']] ?? null;
            $category['post_count'] = $postCounts[(int)$category['id']] ?? 0;
        }
        unset($category);

        return view('Travo\Admin\Views\Categories\index', [
            'page_heading' => 'Manage Post Categories',
            'categories'   => $categories,
            'cats'         => $categories,
            'entry'        => null,
        ]);
    }

    public function create()
    {
        if (!auth()->user()?->can('content.manage')) {
            return redirect()->to('/admin/posts/categories')->with('error', 'Not authorized');
        }

        $categoryModel = new PostCategoryModel();
        $categories = $categoryModel->orderBy('title')->findAll();

        return view('Travo\Admin\Views\Categories\index', [
            'page_heading' => 'Create Post Category',
            'categories'   => $categories,
            'cats'         => $categories,
            'entry'        => null,
        ]);
    }

    public function save(): RedirectResponse
    {
        if (!auth()->user()?->can('content.manage')) {
            return redirect()->to('/admin/posts/categories')->with('error', 'Not authorized');
        }

        $model = new PostCategoryModel();

        $rules = ['title' => 'required|min_length[2]'];
        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $title = trim((string)$this->request->getPost('title'));
        $parentId = $this->normalizeParentId($this->request->getPost('parent_id'));
        $slug = trim((string)$this->request->getPost('slug'));

        $level = 1;
        $sortOrder = 0;
        if ($parentId !== null) {
            $parent = $model->find($parentId);
            if (!$parent) {
                return redirect()->back()->withInput()->with('error', 'Parent category not found.');
            }
            $level = (int)$parent['level'] + 1;
            $sortOrder = (int)$model->where('parent_id', $parentId)->countAllResults();
        } else {
            $sortOrder = (int)$model->where('parent_id IS NULL')->orWhere('parent_id', 0)->countAllResults();
        }

        $data = [
            'author_id'   => \intval(user_id()),
            'title'       => $title,
            'slug'        => $slug !== '' ? $slug : $model->uniqueSlug($title),
            'parent_id'   => $parentId,
            'level'       => min($level, 3),
            'sort_order'  => (int) ($this->request->getPost('sort_order') ?? $sortOrder),
            'content'     => \Travo\Admin\Libraries\ContentCleaner::clean($this->request->getPost('content') ?? ''),
            'thumbnail'   => $this->sanitizeMediaId($this->request->getPost('image')),
            'meta_title'  => $this->request->getPost('meta_title') ?: $title,
            'meta_desc'   => $this->request->getPost('meta_desc') ?: '',
            'icon'        => $this->request->getPost('icon') ?? '',
            'color'       => $this->request->getPost('color') ?? '',
            'is_featured' => (int)($this->request->getPost('is_featured') ?? 0),
            'status'      => 1,
            'on_menu'     => $this->request->getPost('on_menu') === '1' ? 1 : 0,
        ];

        if (!$model->save($data)) {
            return redirect()->back()->withInput()->with('error', 'Failed to save category.');
        }

        $this->invalidateStaticCache($data['slug'] ?? null);
        Services::cache()->clean();
        log_activity('create', 'categories', (int) $model->getInsertID(), 'Created category: ' . $title);

        return redirect()->to('/admin/posts/categories')->with('message', 'Category created successfully.');
    }

    public function edit(int $id)
    {
        if (!auth()->user()?->can('content.manage')) {
            return redirect()->to('/admin/dashboard')->with('error', 'Not authorized');
        }

        $model = new PostCategoryModel();
        $entry = $model->find($id);
        if (!$entry) {
            return redirect()->to('/admin/posts/categories')->with('error', 'Category not found.');
        }

        return view('Travo\Admin\Views\Categories\index', [
            'page_heading' => 'Update Category',
            'categories'   => $model->orderBy('title')->findAll(),
            'cats'         => $model->orderBy('title')->findAll(),
            'entry'        => $entry,
        ]);
    }

    public function update(): RedirectResponse
    {
        if (!auth()->user()?->can('content.manage')) {
            return redirect()->to('/admin/posts/categories')->with('error', 'Not authorized');
        }

        $model = new PostCategoryModel();
        $categoryId = (int)$this->request->getPost('entry_id');
        $entry = $model->find($categoryId);
        if (!$entry) {
            return redirect()->to('/admin/posts/categories')->with('error', 'Category not found.');
        }

        $rules = ['title' => 'required|min_length[2]'];
        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $title = trim((string)$this->request->getPost('title'));
        $parentId = $this->normalizeParentId($this->request->getPost('parent_id'));
        if ($parentId !== null && $parentId === $categoryId) {
            return redirect()->back()->withInput()->with('error', 'A category cannot be its own parent.');
        }
        // Prevent cycles: a category cannot be moved under one of its own children
        if ($parentId !== null && $this->isDescendant($model, $categoryId, $parentId)) {
            return redirect()->back()->withInput()->with('error', 'Cannot move a category under its own subcategory.');
        }

        $slug = trim((string)$this->request->getPost('slug'));
        if ($slug === '' || $slug === $entry['slug']) {
            $slug = $entry['slug'];
        }
        // Enforce uniqueness (exclude self)
        $existing = $model->where('slug', $slug)->where('id !=', $categoryId)->first();
        if ($existing) {
            $slug = $model->uniqueSlug($title, $categoryId);
        }

        $level = (int)$entry['level'];
        if ($parentId !== null) {
            $parent = $model->find($parentId);
            $level = $parent ? (int)$parent['level'] + 1 : 1;
        } else {
            $level = 1;
        }

        $data = [
            'title'       => $title,
            'slug'        => $slug,
            'parent_id'   => $parentId,
            'level'       => min($level, 3),
            'content'     => \Travo\Admin\Libraries\ContentCleaner::clean($this->request->getPost('content') ?? ''),
            'thumbnail'   => $this->sanitizeMediaId($this->request->getPost('image')),
            'meta_title'  => $this->request->getPost('meta_title') ?: $title,
            'meta_desc'   => $this->request->getPost('meta_desc') ?: '',
            'icon'        => $this->request->getPost('icon') ?? $entry['icon'] ?? '',
            'color'       => $this->request->getPost('color') ?? $entry['color'] ?? '',
            'is_featured' => (int)($this->request->getPost('is_featured') ?? $entry['is_featured'] ?? 0),
        ];

        $model->update($categoryId, $data);

        // Keep children levels consistent after reparenting
        $this->syncChildrenLevels($model, $categoryId, (int)$data['level']);

        $this->invalidateStaticCache($slug);
        Services::cache()->clean();
        log_activity('update', 'categories', $categoryId, 'Updated category: ' . $title);

        return redirect()->to('/admin/posts/categories')->with('message', 'Category updated successfully.');
    }

    public function updatePublished(): ResponseInterface
    {
        if (auth()->user()?->can('content.manage') !== true) {
            return $this->failForbidden('Not authorized');
        }

        $json = $this->request->getJSON(true);
        $id = $json['id'] ?? $this->request->getPost('id');
        $status = $json['status'] ?? $this->request->getPost('status');

        if (!$id || !is_numeric($status)) {
            return $this->fail('Invalid input', 400);
        }

        $categoryModel = new PostCategoryModel();
        $categoryModel->update((int)$id, ['status' => (int)$status]);
        $cat = $categoryModel->find((int)$id);
        $this->invalidateStaticCache($cat['slug'] ?? null);
        Services::cache()->clean();

        return $this->respond(['status' => 200, 'message' => 'Status updated successfully.']);
    }

    public function delete(int $id): RedirectResponse
    {
        if (!auth()->user()?->can('content.manage')) {
            return redirect()->to('/admin/posts/categories')->with('error', 'Not authorized');
        }

        $model = new PostCategoryModel();
        $category = $model->find($id);
        if (!$category) {
            return redirect()->to('/admin/posts/categories')->with('error', 'Category not found.');
        }

        $childCount = $model->where('parent_id', $id)->countAllResults();
        if ($childCount > 0) {
            return redirect()->to('/admin/posts/categories')
                ->with('error', 'This category has ' . $childCount . ' subcategories. Please remove or reassign them first.');
        }

        $db = \Config\Database::connect();
        $db->transStart();
        try {
            $relationModel = new PostCategoryRelation();
            $relationModel->where('category_id', $id)->delete();
            $model->delete($id);
            $db->transComplete();
        } catch (\Exception $e) {
            $db->transRollback();
            log_message('error', 'Error deleting category ' . $id . ': ' . $e->getMessage());
            return redirect()->to('/admin/posts/categories')->with('error', 'Failed to delete category.');
        }

        $this->invalidateStaticCache($category['slug'] ?? null);
        Services::cache()->clean();
        log_activity('delete', 'categories', $id, 'Deleted category: ' . ($category['title'] ?? ''));

        return redirect()->to('/admin/posts/categories')->with('message', 'Category deleted successfully.');
    }

    /**
     * Invalidate static HTML for a category change: the category page,
     * its blog/category page, the homepage and the blog listing.
     */
    private function invalidateStaticCache(?string $slug = null): void
    {
        try {
            $static = new \Travo\FrontEnd\LIbraries\StaticPageCache();
            $static->deletePath('index');
            $static->deletePath('blog');
            if (!empty($slug)) {
                $static->deletePath('category/' . $slug);
                $static->deletePath('blog/category/' . $slug);
            }
        } catch (\Throwable $e) {
            log_message('error', 'Category static invalidation failed: ' . $e->getMessage());
        }
    }

    private function normalizeParentId($value): ?int    {
        if ($value === null || $value === '' || (int)$value <= 0) {
            return null;
        }
        return (int)$value;
    }

    private function isDescendant(PostCategoryModel $model, int $categoryId, int $candidateParentId): bool
    {
        $cursor = $candidateParentId;
        $guard = 0;
        while ($cursor !== null && $guard++ < 10) {
            if ($cursor === $categoryId) {
                return true;
            }
            $row = $model->select('parent_id')->find($cursor);
            $cursor = $row ? $this->normalizeParentId($row['parent_id']) : null;
        }
        return false;
    }

    private function syncChildrenLevels(PostCategoryModel $model, int $categoryId, int $parentLevel): void
    {
        $children = $model->where('parent_id', $categoryId)->findAll();
        foreach ($children as $child) {
            $newLevel = min($parentLevel + 1, 3);
            $model->update($child['id'], ['level' => $newLevel]);
            $this->syncChildrenLevels($model, (int)$child['id'], $newLevel);
        }
    }

    private function sanitizeMediaId($raw): ?int
    {
        if ($raw === null || $raw === '') return null;
        $raw = trim((string)$raw);
        if ($raw === '' || $raw === '0') return null;
        if (!ctype_digit($raw)) return null;
        $id = (int)$raw;
        return $id > 0 ? $id : null;
    }
}
