<?php

namespace Travo\FrontEnd\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\Exceptions\PageNotFoundException;
use Travo\Admin\Models\PostCategoryModel;
use Travo\Admin\Models\PostCategoryRelation;
use Travo\Admin\Models\PostModel;
use Travo\FrontEnd\Traits\CacheTrait;

class CategoryController extends Controller
{
    use CacheTrait;

    /**
     * Legacy category slugs (pre-2026-08 restructure) => new slug.
     * Keeps old URLs working with a 301 redirect.
     */
    private const LEGACY_SLUG_MAP = [
        'rajneeti'             => 'nepal',
        'artha-banijya'        => 'nepal',
        'khelkud'              => 'nepal',
        'samaaj'               => 'nepal',
        'shiksha-swashthya'    => 'nepal',
        'pravidhi'             => 'nepal',
        'pradesh-sthaniya'     => 'nepal',
        'byapar'               => 'nepal',
        'vishwa'               => 'ramaailo-vishwa',
        'manoranjan'           => 'ramaailo-vishwa',
        'pravas'               => 'migration',
        'australia'            => 'migration',
        'middle-east'          => 'migration',
        'europe-america'       => 'migration',
        'nz-news'              => 'newzealand',
        'nz-education'         => 'newzealand',
        'nz-sports'            => 'newzealand',
    ];

    public function details($slug)
    {
        $this->serveStaticPage();
        $catModel = new PostCategoryModel();
        $relationModel = new PostCategoryRelation();
        $postModel = new PostModel();

        $category = $catModel->where('slug', $slug)->where('status', 1)->first();

        if (!$category && isset(self::LEGACY_SLUG_MAP[$slug])) {
            return redirect()->to('/category/' . self::LEGACY_SLUG_MAP[$slug], 301);
        }

        if (!$category) {
            throw PageNotFoundException::forPageNotFound();
        }

        $relations = $relationModel->where('category_id', $category['id'])->findAll();
        $postIds = array_column($relations, 'post_id');

        $posts = [];
        if (!empty($postIds)) {
            $rawPosts = $postModel
                ->baseSelect()
                ->whereIn('posts.id', $postIds)
                ->where('posts.status', 1)
                ->orderBy('posts.created_at', 'DESC')
                ->paginate(15);

            $posts = $rawPosts;
        }

        $output = view('Travo\FrontEnd\Views\Post\list', [
            'heading' => $category['title'],
            'posts' => $posts,
            'pager' => $postModel->pager,
        ]);
        $this->storeStaticPage($output);
        return $output;
    }
}
