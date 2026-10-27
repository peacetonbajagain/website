<?php
namespace Travo\Admin\Controllers;

use App\Controllers\BaseController;
use Travo\Admin\Libraries\ContentCleaner;
use Travo\Admin\Controllers\GalleryController;
use Travo\Admin\Models\{CategoryModel, PostModel};

class PostsController extends BaseController
{
    private function cats(): array
    {
        return (new CategoryModel())->orderBy('sort_order')->findAll();
    }

    public function index()
    {
        $m = new PostModel();
        $status = $this->request->getGet('status');
        $b = $m->baseSelect()->orderBy('posts.id', 'DESC');
        if ($status === '0' || $status === '1') {
            $b->where('posts.status', (int) $status);
        }
        return view('Travo\Admin\Views\Posts\index', [
            'title' => 'Posts', 'posts' => $b->paginate(15), 'pager' => $m->pager, 'status' => $status,
        ]);
    }

    public function new()
    {
        return view('Travo\Admin\Views\Posts\form', ['title' => 'New post', 'post' => [], 'cats' => $this->cats(), 'categoryIds' => [], 'images' => GalleryController::libraryImages()]);
    }

    public function edit(int $id)
    {
        $post = (new PostModel())->baseSelect()->where('posts.id', $id)->first();
        if (! $post) {
            return redirect()->to('admin/posts')->with('error', 'Post not found.');
        }
        return view('Travo\Admin\Views\Posts\form', ['title' => 'Edit post', 'post' => $post, 'cats' => $this->cats(), 'categoryIds' => (new PostModel())->categoryIds($id), 'images' => GalleryController::libraryImages()]);
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
        $rules = [
            'title' => 'required|min_length[3]|max_length[255]',
            'status' => 'required|in_list[0,1]',
            'thumbnail' => 'if_exist|is_image[thumbnail]|max_size[thumbnail,3072]',
            'thumbnail_path' => 'permit_empty|regex_match[/\Auploads\/[a-zA-Z0-9_\/-]+\.(jpg|jpeg|png|gif|webp)\z/i]',
            'remove_thumbnail' => 'permit_empty|in_list[1]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }
        $categoryIds = $this->request->getPost('category_ids') ?? [];
        if (! is_array($categoryIds)) {
            return redirect()->back()->withInput()->with('error', 'Choose valid categories.');
        }
        foreach ($categoryIds as $categoryId) {
            if (! is_string($categoryId) || ! ctype_digit($categoryId) || (int) $categoryId < 1) {
                return redirect()->back()->withInput()->with('error', 'Choose valid categories.');
            }
        }
        $categoryIds = array_values(array_unique(array_map('intval', $categoryIds)));
        if ($categoryIds !== [] && (new CategoryModel())->whereIn('id', $categoryIds)->countAllResults() !== count($categoryIds)) {
            return redirect()->back()->withInput()->with('error', 'One or more selected categories no longer exist.');
        }
        $m = new PostModel();
        $old = $id ? $m->find($id) : null;
        if ($id && ! $old) {
            return redirect()->to('admin/posts')->with('error', 'Post not found.');
        }
        $p = $this->request;
        $status = (int) $p->getPost('status');
        $title = trim($p->getPost('title'));

        $data = [
            'title' => $title,
            'slug' => create_unique_slug('posts', $title, $id),
            'content' => ContentCleaner::clean($p->getPost('content')),
            'excerpt' => trim(strip_tags((string) $p->getPost('excerpt'))),
            'meta_title' => trim((string) $p->getPost('meta_title')),
            'meta_desc' => trim((string) $p->getPost('meta_desc')),
            'author_name' => trim((string) $p->getPost('author_name')) ?: (auth()->user()->username ?? 'Editorial'),
            'is_featured' => (int) (bool) $p->getPost('is_featured'),
            'is_breaking_news' => (int) (bool) $p->getPost('is_breaking_news'),
            'is_trending' => (int) (bool) $p->getPost('is_trending'),
            'status' => $status,
            'published_at' => $status === 1 ? ($old['published_at'] ?? date('Y-m-d H:i:s')) : ($old['published_at'] ?? null),
        ];
        if (! $id) {
            $data['author_id'] = auth()->id();
        } elseif ($old['slug'] !== $data['slug'] && $old['title'] === $title) {
            $data['slug'] = $old['slug']; // keep stable URL when title unchanged
        }

        $file = $p->getFile('thumbnail');
        $thumbnailPath = trim((string) $p->getPost('thumbnail_path'));
        if ($p->getPost('remove_thumbnail')) {
            $data['thumbnail'] = null;
        }
        if ($thumbnailPath !== '') {
            $uploadRoot = realpath(FCPATH . 'uploads');
            $imagePath = realpath(FCPATH . $thumbnailPath);
            $imageInfo = $imagePath !== false ? @getimagesize($imagePath) : false;

            if (
                $uploadRoot === false
                || $imagePath === false
                || ! str_starts_with($imagePath, $uploadRoot . DIRECTORY_SEPARATOR)
                || $imageInfo === false
                || ! \in_array($imageInfo['mime'] ?? '', ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)
            ) {
                return redirect()->back()->withInput()->with('error', 'Choose an image from the media library.');
            }

            $data['thumbnail'] = $thumbnailPath;
        }
        if ($file && $file->isValid() && ! $file->hasMoved()) {
            if (! \in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
                return redirect()->back()->withInput()->with('error', 'Unsupported image type.');
            }
            $dir = 'uploads/' . date('Y/m');
            $name = $file->getRandomName();
            $file->move(FCPATH . $dir, $name);
            $data['thumbnail'] = $dir . '/' . $name;
        }

        $id ? $m->update($id, $data) : $m->insert($data);
        $id = $id ?: (int) $m->getInsertID();
        $m->setCategories($id, $categoryIds);
        log_activity($old ? 'update' : 'create', 'posts', $id, $title);
        forget_site_cache();

        return redirect()->to('admin/posts')->with('success', 'Post saved.');
    }

    public function delete(int $id)
    {
        (new PostModel())->delete($id);
        log_activity('delete', 'posts', $id);
        return redirect()->to('admin/posts')->with('success', 'Post deleted.');
    }
}
