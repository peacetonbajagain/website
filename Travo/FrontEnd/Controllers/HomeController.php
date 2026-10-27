<?php
namespace Travo\FrontEnd\Controllers;

use App\Controllers\BaseController;
use Travo\Admin\Models\PostModel;

class HomeController extends BaseController
{
    public function index()
    {
        $m = new PostModel();
        $latest = $m->published()->findAll(13);
        $hero = null;
        foreach ($latest as $i => $p) {
            if ($p['is_featured']) { $hero = $p; unset($latest[$i]); break; }
        }
        $hero ??= array_shift($latest);

        $breaking = (new PostModel())->published()->where('posts.is_breaking_news', 1)->findAll(5);
        $trending = (new PostModel())->published()->orderBy('posts.view_count', 'DESC')->findAll(5);

        return view('Travo\FrontEnd\Views\Home\index', [
            'title' => site_name() . ' — Latest News',
            'hero' => $hero, 'latest' => array_slice($latest, 0, 9), 'breaking' => $breaking, 'trending' => $trending,
        ]);
    }
}
