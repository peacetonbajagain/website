<?php

namespace Travo\FrontEnd\Controllers;

use CodeIgniter\Controller;
use Travo\Admin\Models\PostCategoryModel;
use Travo\Admin\Models\PostModel;
use Travo\Admin\Models\PostTagModel;

class SearchController extends Controller
{
    public function index()
    {
        $q = trim((string)($this->request->getGet('q') ?? ''));

        $postModel     = new PostModel();
        $categoryModel = new PostCategoryModel();

        // Load all active categories for suggestions
        $allCats = $categoryModel->where('status', 1)->orderBy('title', 'ASC')->findAll();

        $posts = [];
        if ($q !== '') {
            $postTagModel = new PostTagModel();
            $tagPostIds = array_column(
                $postTagModel->select('post_tags.post_id')
                    ->join('tags', 'tags.id = post_tags.tag_id')
                    ->like('tags.name', $q)
                    ->findAll(500),
                'post_id'
            );

            $builder = $postModel->baseSelect()
                ->select("CASE WHEN TRIM(CONCAT_WS(' ', COALESCE(users.first_name,''), COALESCE(users.last_name,''))) <> '' THEN TRIM(CONCAT_WS(' ', COALESCE(users.first_name,''), COALESCE(users.last_name,''))) ELSE users.username END AS author_name", false)
                ->join('users', 'users.id = posts.author_id', 'left')
                ->where('posts.status', 1)
                ->groupStart()
                    ->groupStart()
                    ->like('posts.title', $q)
                    ->orLike('posts.content', $q)
                    ->orLike('posts.excerpt', $q)
                    ->orLike('posts.tags', $q)
                    ->groupEnd();
            if (! empty($tagPostIds)) {
                $builder->orWhereIn('posts.id', $tagPostIds);
            }
            $builder
                ->groupEnd();

            $posts = $builder->orderBy('posts.created_at', 'DESC')
                ->paginate(12, 'default');
        }

        return view('Travo\FrontEnd\Views\Search\index', [
            'page_title' => $q !== '' ? 'Search Results for: "' . esc($q) . '"' : 'Search Eyewear Guides & Articles',
            'meta_desc'  => 'Search glasses hacks, anti-fog guides, frame styling, and spectacles survival tips.',
            'posts'      => $posts,
            'pager'      => $postModel->pager,
            'allCats'    => $allCats,
            'q'          => $q,
            'query'      => $q,
        ]);
    }
}
