<?php

namespace Travo\FrontEnd\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\Exceptions\PageNotFoundException;
use Travo\Admin\Libraries\ContentCleaner;
use Travo\Admin\Libraries\ImageUpload;
use Travo\Admin\Models\PostModel;
use Travo\Admin\Models\PostCategoryModel;
use Travo\Admin\Models\PostCategoryRelation;
use Travo\Admin\Models\CommentModel;
use Travo\Admin\Models\StorySubmissionModel;
use Travo\FrontEnd\Traits\CacheTrait;

class PostController extends Controller
{
    use CacheTrait;

    public function submit()
    {
        if (! auth()->loggedIn()) {
            return redirect()->to('login')->with('error', 'Sign in to submit a story.');
        }

        return view('Travo\\FrontEnd\\Views\\Post\\submit', [
            'title' => 'Submit a story',
        ]);
    }

    public function saveSubmission()
    {
        if (! auth()->loggedIn()) {
            return redirect()->to('login')->with('error', 'Sign in to submit a story.');
        }

        $rules = [
            'title' => 'required|min_length[3]|max_length[255]',
            'excerpt' => 'permit_empty|max_length[500]',
            'content' => 'required|min_length[50]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $title = trim((string) $this->request->getPost('title'));
        $excerpt = trim((string) $this->request->getPost('excerpt'));
        $body = trim((string) $this->request->getPost('content'));

        $stagedImage = null;
        try {
            $thumbnail = $this->request->getFile('thumbnail');
            if ($thumbnail !== null && $thumbnail->getError() !== UPLOAD_ERR_NO_FILE) {
                $stagedImage = ImageUpload::stage($thumbnail);
            }

            $submissionId = model(StorySubmissionModel::class)->insert([
                'user_id' => (int) auth()->id(),
                'title' => $title,
                'content' => ContentCleaner::clean($body),
                'excerpt' => $excerpt === '' ? null : $excerpt,
                'image_path' => $stagedImage['path'] ?? null,
                'image_name' => $stagedImage['name'] ?? null,
                'status' => 'pending',
            ], true);

            if (! $submissionId) {
                    throw new \RuntimeException('The story could not be saved.');
            }

            return redirect()->to('blog/submit')->with('success', 'Your story was submitted for review.');
        } catch (\Throwable $exception) {
            ImageUpload::discardStaged($stagedImage['path'] ?? null);
            log_message('error', 'Member story submission failed: ' . $exception->getMessage());

            return redirect()->back()->withInput()->with('error', 'Your story could not be submitted. Please check the image and try again.');
        }
    }

    public function index()
    {
        $postModel = new PostModel();
        $postModel->published();
        $posts = $postModel->paginate(12);

        return view('Travo\FrontEnd\Views\Post\list', [
            'heading' => 'Latest news',
            'posts' => $posts,
            'pager' => $postModel->pager,
        ]);
    }

    public function PostDetails(?string $slug = null): string
    {
        $this->serveStaticPage();
        $postModel = new PostModel();
        $catModel = new PostCategoryModel();
        $catRelation = new PostCategoryRelation();

        $post = $postModel
            ->baseSelect()
            ->select("CASE WHEN TRIM(CONCAT_WS(' ', COALESCE(users.first_name,''), COALESCE(users.last_name,''))) <> '' THEN TRIM(CONCAT_WS(' ', COALESCE(users.first_name,''), COALESCE(users.last_name,''))) ELSE users.username END AS author_name", false)
            ->join('users', 'users.id = posts.author_id', 'left')
            ->where('posts.slug', $slug)
            ->where('posts.status', 1)
            ->groupStart()
                ->where('posts.published_at', null)
                ->orWhere('posts.published_at <=', date('Y-m-d H:i:s'))
            ->groupEnd()
            ->first();

        if (!$post) {
            throw PageNotFoundException::forPageNotFound();
        }

        $postModel->incrementViewCount($post['id']);

        $catIds = array_column($catRelation->where('post_id', $post['id'])->findAll(), 'category_id');
        $categories = !empty($catIds) ? $catModel->whereIn('id', $catIds)->findAll() : [];

        // Shared layout data
        $allCats = $catModel->where('status', 1)->orderBy('title', 'ASC')->findAll();

        // Fetch Related Posts
        $relatedPosts = [];
        if (!empty($catIds)) {
            $rawRelated = $postModel
                ->baseSelect()
                ->select("CASE WHEN TRIM(CONCAT_WS(' ', COALESCE(users.first_name,''), COALESCE(users.last_name,''))) <> '' THEN TRIM(CONCAT_WS(' ', COALESCE(users.first_name,''), COALESCE(users.last_name,''))) ELSE users.username END AS author_name", false)
                ->join('users', 'users.id = posts.author_id', 'left')
                ->join('post_categories', 'post_categories.post_id = posts.id', 'inner')
                ->whereIn('post_categories.category_id', $catIds)
                ->where('posts.id !=', $post['id'])
                ->where('posts.status', 1)
                ->groupStart()
                    ->where('posts.published_at', null)
                    ->orWhere('posts.published_at <=', date('Y-m-d H:i:s'))
                ->groupEnd()
                ->groupBy('posts.id')
                ->orderBy('posts.created_at', 'DESC')
                ->limit(3)
                ->find();

            $relatedPosts = $rawRelated;
        }

        // Fetch Trending Posts
        $trendingPosts = $postModel
            ->select('posts.id, posts.title, posts.slug, posts.created_at')
            ->where('posts.status', 1)
            ->groupStart()
                ->where('posts.published_at', null)
                ->orWhere('posts.published_at <=', date('Y-m-d H:i:s'))
            ->groupEnd()
            ->orderBy('view_count', 'DESC')
            ->limit(5)
            ->find();

        // Approved comment count
        $commentModel = new CommentModel();
        $commentCount = $commentModel
            ->where('post_id', $post['id'])
            ->where('status', 'approved')
            ->countAllResults();
        $comments = $commentModel
            ->where('post_id', $post['id'])
            ->where('status', 'approved')
            ->orderBy('created_at', 'ASC')
            ->findAll(100);

        // Prev / Next navigation (dense pagination footer)
        $publishedClause = fn($dir) => $postModel
            ->where('posts.status', 1)
            ->groupStart()
                ->where('posts.published_at', null)
                ->orWhere('posts.published_at <=', date('Y-m-d H:i:s'))
            ->groupEnd()
            ->where('posts.created_at ' . $dir, $post['created_at'])
            ->orderBy('posts.created_at', $dir === '>' ? 'ASC' : 'DESC')
            ->select('posts.title, posts.slug')
            ->first();

        $nextPost = $publishedClause('>');
        $prevPost = $publishedClause('<');

        $output = view('Travo\FrontEnd\Views\Post\show', [
            'page_title'      => !empty($post['meta_title']) ? $post['meta_title'] : $post['title'],
            'meta_description' => !empty($post['meta_desc']) ? $post['meta_desc'] : (!empty($post['excerpt']) ? $post['excerpt'] : strip_tags(mb_strimwidth($post['content'] ?? '', 0, 150))),
            'post'            => $post,
            'categories'      => $categories,
            'author_name'     => $post['author_name'] ?? 'Admin',
            'allCats'         => $allCats,
            'relatedPosts'    => $relatedPosts,
            'trendingPosts'   => $trendingPosts,
            'commentCount'    => $commentCount,
            'comments'        => $comments,
            'nextPost'        => $nextPost,
            'prevPost'        => $prevPost,
        ]);
        $this->storeStaticPage($output);
        return $output;
    }

    public function comment(): string
    {
        $postId = (int) $this->request->getPost('post_id');
        $postModel = new PostModel();
        $post = $postModel->where('status', 1)->find($postId);
        if ($post === null) {
            return '<div class="alert alert-danger" role="alert">This story is no longer available.</div>';
        }

        if (trim((string) $this->request->getPost('website')) !== '') {
            return '<div class="alert alert-success" role="status">Your comment is awaiting review.</div>';
        }

        $rules = [
            'author_name' => 'required|max_length[80]',
            'author_email' => 'required|valid_email|max_length[255]',
            'body' => 'required|max_length[2000]',
        ];
        if (! $this->validate($rules)) {
            return '<div class="alert alert-danger" role="alert">Enter your name, a valid email address, and a comment of up to 2,000 characters.</div>';
        }

        $commentModel = new CommentModel();
        $commentModel->insert([
            'post_id' => $postId,
            'author_name' => trim((string) $this->request->getPost('author_name')),
            'author_email' => trim((string) $this->request->getPost('author_email')),
            'body' => strip_tags((string) $this->request->getPost('body')),
            'status' => 'pending',
            'ip_address' => $this->request->getIPAddress(),
        ]);

        return '<div class="alert alert-success" role="status">Your comment is awaiting review.</div>';
    }

    /**
     * Lightweight view-count endpoint hit by a beacon on the cached post page.
     * Returns 204 so it never disturbs the page.
     */
    public function recordView(int $id)
    {
        if ($id > 0) {
            $postModel = new PostModel();
            $postModel->incrementViewCount($id);
        }
        return $this->response->setStatusCode(204);
    }
}
