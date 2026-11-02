<?php

namespace Travo\Admin\Models;

use CodeIgniter\Model;
use CodeIgniter\Cache\CacheInterface;

class PostCategoryModel extends Model
{
    protected $table = 'blog_categories';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields = [
        'title', 'slug', 'parent_id', 'sort_order', 'content', 'status',
        'thumbnail', 'meta_title', 'meta_desc', 'created_at', 'updated_at',
        'author_id', 'level', 'on_menu', 'icon', 'color', 'is_featured',
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    public function getJobCategories()
    {
        return $this->where('status', 1)
                    ->whereIn('id', [1, 3, 4, 5, 6, 7])
                    ->findAll();
    }

    /**
     * All top-level categories (parent_id NULL or 0) with nested children.
     */
    public function getCategories()
    {
        $builder = $this->db->table($this->table);
        $builder->where('status', 1);
        $builder->groupStart()
                ->where('parent_id', 0)
                ->orWhere('parent_id IS NULL')
                ->groupEnd();
        $builder->orderBy('sort_order', 'ASC');
        $builder->orderBy('title', 'ASC');
        $categories = $builder->get()->getResultArray();

        foreach ($categories as &$category) {
            $category['children'] = $this->getSubTaxonomies($category['id']);
        }

        return $categories;
    }

    public function getSubTaxonomies($parentId)
    {
        $builder = $this->db->table($this->table);
        $builder->where('parent_id', $parentId);
        $builder->where('status', 1);
        $builder->orderBy('sort_order', 'ASC');
        $builder->orderBy('title', 'ASC');
        $subCategories = $builder->get()->getResultArray();

        foreach ($subCategories as &$subCategory) {
            $subCategory['children'] = $this->getSubTaxonomies($subCategory['id']);
        }

        return $subCategories;
    }

    /**
     * id => title map for every category (used for the parent column).
     */
    public function getTitleMap(): array
    {
        $rows = $this->select('id, title')->findAll();
        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['id']] = $row['title'];
        }
        return $map;
    }

    /**
     * category_id => number of PUBLISHED posts linked via the pivot table.
     */
    public function getPostCounts(): array
    {
        $db = \Config\Database::connect();
        $rows = $db->table('post_categories pc')
            ->select('pc.category_id, COUNT(*) AS total')
            ->join('posts p', 'p.id = pc.post_id AND p.status = 1', 'inner')
            ->groupBy('pc.category_id')
            ->get()
            ->getResultArray();

        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['category_id']] = (int)$row['total'];
        }
        return $map;
    }

    /**
     * Unique slug generator for category titles (Devanagari-friendly).
     */
    public function uniqueSlug(string $name, ?int $excludeId = null): string
    {
        if (function_exists('create_unique_slug')) {
            return create_unique_slug($name, 'blog_categories', $excludeId);
        }

        $slug = strtolower(trim(preg_replace('/[^a-z0-9-]+/', '-', $name), '-'));
        if ($slug === '') {
            $slug = 'cat-' . substr(md5($name), 0, 8);
        }

        $base = $slug;
        $i = 1;
        $builder = $this->where('slug', $slug);
        if ($excludeId) {
            $builder->where('id !=', $excludeId);
        }
        while ($builder->countAllResults() > 0) {
            $slug = $base . '-' . ++$i;
            $builder = $this->where('slug', $slug);
            if ($excludeId) {
                $builder->where('id !=', $excludeId);
            }
        }

        return $slug;
    }

    public function getCategoryWithTranslation(int $id, string $lang = 'en')
    {
        return $this->select('categories.*, ct.title, ct.slug, ct.content, ct.meta_title, ct.meta_desc')
            ->join('category_translations ct', 'ct.category_id = categories.id')
            ->where('categories.id', $id)
            ->where('ct.language_code', $lang)
            ->first();
    }

}