<?php
namespace Travo\Admin\Controllers;

use App\Controllers\BaseController;
use Travo\Admin\Libraries\ContentCleaner;
use Travo\Admin\Models\PageModel;

class PagesController extends BaseController
{
    public function index()
    {
        return view('Travo\Admin\Views\Pages\index', ['title' => 'Pages', 'pages' => (new PageModel())->orderBy('id', 'DESC')->findAll()]);
    }

    public function new()
    {
        return view('Travo\Admin\Views\Pages\form', ['title' => 'New page', 'page' => []]);
    }

    public function edit(int $id)
    {
        $page = (new PageModel())->find($id);
        if (! $page) {
            return redirect()->to('admin/pages')->with('error', 'Page not found.');
        }
        return view('Travo\Admin\Views\Pages\form', ['title' => 'Edit page', 'page' => $page]);
    }

    public function create()
    {
        return $this->store(null);
    }

    public function update(int $id)
    {
        return $this->store($id);
    }

    private function store(?int $id)
    {
        if (! $this->validate(['title' => 'required|min_length[2]|max_length[150]', 'status' => 'required|in_list[0,1]'])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }
        $p = $this->request;
        $title = trim($p->getPost('title'));
        $data = [
            'title' => $title,
            'slug' => create_unique_slug('pages', $title, $id),
            'content' => ContentCleaner::clean($p->getPost('content')),
            'meta_title' => trim((string) $p->getPost('meta_title')),
            'meta_desc' => trim((string) $p->getPost('meta_desc')),
            'status' => (int) $p->getPost('status'),
        ];
        $m = new PageModel();
        $id ? $m->update($id, $data) : $m->insert($data);
        log_activity($id ? 'update' : 'create', 'pages', $id ?: $m->getInsertID(), $title);
        forget_site_cache();
        return redirect()->to('admin/pages')->with('success', 'Page saved.');
    }

    public function delete(int $id)
    {
        (new PageModel())->delete($id);
        log_activity('delete', 'pages', $id);
        forget_site_cache();
        return redirect()->to('admin/pages')->with('success', 'Page deleted.');
    }
}
