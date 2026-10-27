<?php
namespace Travo\FrontEnd\Controllers;

use App\Controllers\BaseController;
use Travo\Admin\Libraries\ContentCleaner;
use Travo\Admin\Models\PageModel;

class PageController extends BaseController
{
    public function show(string $slug)
    {
        $page = (new PageModel())->where(['slug' => $slug, 'status' => 1])->first();
        if (! $page) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $page['content'] = ContentCleaner::clean($page['content']);

        return view('Travo\FrontEnd\Views\Page\show', [
            'title' => ($page['meta_title'] ?: $page['title']) . ' — ' . site_name(),
            'metaDesc' => $page['meta_desc'], 'page' => $page,
        ]);
    }
}
