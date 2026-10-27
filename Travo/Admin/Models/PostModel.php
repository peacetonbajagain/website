<?php
namespace Travo\Admin\Models;

use CodeIgniter\Model;

class PostModel extends Model
{
    protected $table = 'posts';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $allowedFields = ['author_id', 'title', 'slug', 'content', 'excerpt', 'thumbnail_id', 'is_featured',
        'is_breaking_news', 'is_trending', 'meta_title', 'meta_desc', 'author_name', 'view_count', 'status', 'published_at'];

    /** Posts with a stable first category for existing display surfaces. */
    public function baseSelect(): static
    {
        return $this->select("posts.*, uploads.path AS thumbnail_path,
            (SELECT c.title FROM post_categories pc JOIN blog_categories c ON c.id = pc.category_id WHERE pc.post_id = posts.id ORDER BY pc.category_id LIMIT 1) AS category_title,
            (SELECT c.slug FROM post_categories pc JOIN blog_categories c ON c.id = pc.category_id WHERE pc.post_id = posts.id ORDER BY pc.category_id LIMIT 1) AS category_slug,
            (SELECT pc.category_id FROM post_categories pc WHERE pc.post_id = posts.id ORDER BY pc.category_id LIMIT 1) AS category_id", false)
            ->join('uploads', 'uploads.id = posts.thumbnail_id', 'left');
    }

    public function forCategory(int $categoryId): static
    {
        return $this->whereIn('posts.id', static function ($builder) use ($categoryId): void {
            $builder->select('post_id')->from('post_categories')->where('category_id', $categoryId);
        });
    }

    public function published(): static
    {
        return $this->baseSelect()->where('posts.status', 1)->where('posts.published_at <=', date('Y-m-d H:i:s'))
            ->orderBy('posts.published_at', 'DESC');
    }

    public function categoryIds(int $postId): array
    {
        $rows = db_connect()->table('post_categories')->select('category_id')->where('post_id', $postId)->get()->getResultArray();

        return array_map('intval', array_column($rows, 'category_id'));
    }

    public function setCategories(int $postId, array $categoryIds): void
    {
        $db = db_connect();
        $db->transStart();
        $db->table('post_categories')->where('post_id', $postId)->delete();
        foreach ($categoryIds as $categoryId) {
            $db->table('post_categories')->insert(['post_id' => $postId, 'category_id' => $categoryId]);
        }
        $db->transComplete();
    }
}
