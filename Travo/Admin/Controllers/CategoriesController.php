<?php
namespace Travo\Admin\Controllers;

use App\Controllers\BaseController;
use Travo\Admin\Models\CategoryModel;

class CategoriesController extends BaseController
{
    public function index()
    {
        return view('Travo\Admin\Views\Categories\index', [
            'title' => 'Categories', 'cats' => (new CategoryModel())->orderBy('sort_order')->findAll(),
        ]);
    }

    public function create()
    {
        if (! $this->validate(['title' => 'required|min_length[2]|max_length[255]', 'sort_order' => 'permit_empty|integer'])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }
        $title = trim($this->request->getPost('title'));
        $m = new CategoryModel();
        $m->insert([
            'title' => $title, 'slug' => create_unique_slug('blog_categories', $title),
            'sort_order' => (int) $this->request->getPost('sort_order'),
            'on_menu' => (int) (bool) $this->request->getPost('on_menu'), 'status' => 1,
        ]);
        log_activity('create', 'categories', $m->getInsertID(), $title);
        forget_site_cache();
        return redirect()->to('admin/categories')->with('success', 'Category added.');
    }

    public function delete(int $id)
    {
        (new CategoryModel())->delete($id);
        db_connect()->table('post_categories')->where('category_id', $id)->delete();
        log_activity('delete', 'categories', $id);
        forget_site_cache();
        return redirect()->to('admin/categories')->with('success', 'Category deleted.');
    }
}
