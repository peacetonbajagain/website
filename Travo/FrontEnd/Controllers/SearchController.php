<?php
namespace Travo\FrontEnd\Controllers;

use App\Controllers\BaseController;
use Travo\Admin\Models\PostModel;

class SearchController extends BaseController
{
    public function index()
    {
        $q = trim((string) $this->request->getGet('q'));
        $m = new PostModel();
        $posts = [];
        if (mb_strlen($q) >= 2) {
            $posts = $m->published()->groupStart()->like('posts.title', $q)->orLike('posts.excerpt', $q)->groupEnd()->paginate(site_posts_per_page());
        }
        return view('Travo\FrontEnd\Views\Search\index', [
            'title' => 'Search — ' . site_name(), 'q' => $q, 'posts' => $posts, 'pager' => $m->pager,
        ]);
    }
}
