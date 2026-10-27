<?php
namespace Travo\FrontEnd\Controllers;

use App\Controllers\BaseController;
use Travo\Admin\Libraries\ContentCleaner;
use Travo\Admin\Models\{CategoryModel, CommentModel, PostModel};

class PostController extends BaseController
{
    public function index()
    {
        $m = new PostModel();
        $posts = $m->published()->paginate(site_posts_per_page());
        return view('Travo\FrontEnd\Views\Post\list', [
            'title' => 'All News — ' . site_name(), 'heading' => 'All News', 'posts' => $posts, 'pager' => $m->pager,
        ]);
    }

    public function category(string $slug)
    {
        $cat = (new CategoryModel())->where(['slug' => $slug, 'status' => 1])->first();
        if (! $cat) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $m = new PostModel();
        $posts = $m->published()->forCategory((int) $cat['id'])->paginate(site_posts_per_page());
        return view('Travo\FrontEnd\Views\Post\list', [
            'title' => $cat['title'] . ' — ' . site_name(), 'heading' => $cat['title'], 'posts' => $posts, 'pager' => $m->pager,
        ]);
    }

    public function show(string $slug)
    {
        $m = new PostModel();
        $post = $m->published()->where('posts.slug', $slug)->first();
        if (! $post) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $post['content'] = ContentCleaner::clean($post['content']);
        db_connect()->table('posts')->where('id', $post['id'])->set('view_count', 'view_count+1', false)->update();
        $post['view_count']++;

        $related = [];
        if ($post['category_id']) {
            $related = (new PostModel())->published()->forCategory((int) $post['category_id'])
                ->where('posts.id !=', $post['id'])->findAll(3);
        }
        $comments = (new CommentModel())->where(['post_id' => $post['id'], 'status' => 'approved'])->orderBy('id', 'ASC')->findAll();

        return view('Travo\FrontEnd\Views\Post\show', [
            'title' => ($post['meta_title'] ?: $post['title']) . ' — ' . site_name(),
            'metaDesc' => $post['meta_desc'] ?: $post['excerpt'],
            'post' => $post, 'related' => $related, 'comments' => $comments,
        ]);
    }

    public function comment()
    {
        $reply = function (bool $ok, string $msg) {
            if ($this->request->isAJAX()) {
                $cls = $ok ? 'alert-success' : 'alert-danger';
                return $this->response->setBody('<div class="alert ' . $cls . ' mb-0">' . esc($msg) . '</div>');
            }
            return redirect()->back()->with($ok ? 'success' : 'error', $msg);
        };

        if (! (bool) (setting('Travo.commentsEnabled') ?? true)) {
            return $reply(false, 'Comments are currently closed.');
        }
        if ($this->request->getPost('website')) { // honeypot
            return $reply(true, 'Thanks! Your comment is awaiting moderation.');
        }
        if (! service('throttler')->check('comment_' . md5($this->request->getIPAddress()), 3, MINUTE)) {
            return $reply(false, 'Too many comments. Please wait a minute.');
        }
        $rules = [
            'post_id' => 'required|is_natural_no_zero',
            'author_name' => 'required|min_length[2]|max_length[80]',
            'author_email' => 'required|valid_email|max_length[255]',
            'body' => 'required|min_length[3]|max_length[2000]',
        ];
        if (! $this->validate($rules)) {
            return $reply(false, implode(' ', $this->validator->getErrors()));
        }
        $post = (new PostModel())->where(['id' => (int) $this->request->getPost('post_id'), 'status' => 1])->first();
        if (! $post) {
            return $reply(false, 'Article not found.');
        }
        (new CommentModel())->insert([
            'post_id' => $post['id'],
            'author_name' => trim($this->request->getPost('author_name')),
            'author_email' => trim($this->request->getPost('author_email')),
            'body' => trim($this->request->getPost('body')),
            'status' => 'pending',
            'ip_address' => $this->request->getIPAddress(),
        ]);
        return $reply(true, 'Thanks! Your comment is awaiting moderation.');
    }
}
