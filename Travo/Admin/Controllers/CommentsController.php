<?php

namespace Travo\Admin\Controllers;

use CodeIgniter\Controller;
use Travo\Admin\Models\CommentModel;

class CommentsController extends Controller
{
    protected $commentModel;

    public function __construct()
    {
        $this->commentModel = new CommentModel();
    }

    public function index()
    {
        if (!auth()->user()?->can('comments.moderate')) {
            return redirect()->to('/admin/dashboard')->with('error', 'Not authorized');
        }

        $data = [
            'page_heading' => 'Comments Management',
            'comments' => $this->commentModel
                ->select('comments.*, posts.title AS post_title')
                ->join('posts', 'posts.id = comments.post_id', 'left')
                ->orderBy('comments.created_at', 'DESC')
                ->paginate(20),
            'pager' => $this->commentModel->pager,
        ];
        return view('Travo\Admin\Views\Comments\index', $data);
    }

    public function details($id)
    {
        $comment = $this->commentModel->find($id);
        if (!$comment) {
            return redirect()->to('admin/comments')->with('error', 'Comment not found.');
        }
        $data = [
            'page_heading' => 'Comment Details',
            'comment' => $comment,
            'pager' => null,
        ];
        $data['comments'] = [$comment];
        return view('Travo\Admin\Views\Comments\index', $data);
    }

    public function status(int $id, string $status)
    {
        if (!\in_array($status, ['approved', 'spam', 'pending'], true)) {
            return redirect()->to('admin/comments')->with('error', 'Invalid comment status.');
        }

        if ($this->commentModel->find($id) === null) {
            return redirect()->to('admin/comments')->with('error', 'Comment not found.');
        }

        $this->commentModel->update($id, ['status' => $status]);
        log_activity('status_change', 'comments', $id, 'Comment status set to ' . $status);

        return redirect()->to('admin/comments')->with('message', 'Comment status updated.');
    }

    public function approve($id)
    {
        $this->commentModel->update((int) $id, ['status' => 'approved']);
        log_activity('approve', 'comments', (int) $id, 'Approved comment');
        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['status' => 200, 'message' => 'Comment approved successfully.']);
        }
        return redirect()->to('admin/comments')->with('message', 'Comment approved successfully.');
    }

    public function reject($id)
    {
        $this->commentModel->update((int) $id, ['status' => 'spam']);
        log_activity('reject', 'comments', (int) $id, 'Marked comment as spam');
        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['status' => 200, 'message' => 'Comment marked as spam.']);
        }
        return redirect()->to('admin/comments')->with('message', 'Comment marked as spam.');
    }

    public function delete($id)
    {
        $this->commentModel->delete((int) $id);
        log_activity('delete', 'comments', (int) $id, 'Deleted comment');
        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['status' => 200, 'message' => 'Comment deleted successfully.']);
        }
        return redirect()->to('admin/comments')->with('message', 'Comment deleted successfully.');
    }

    public function update($id)
    {
        $rules = [
            'author_name' => 'required|string|max_length[255]',
            'author_email' => 'required|valid_email|max_length[255]',
            'body' => 'required|string',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $this->commentModel->update($id, [
            'author_name' => $this->request->getPost('author_name'),
            'author_email' => $this->request->getPost('author_email'),
            'body' => strip_tags((string) $this->request->getPost('body')),
            'status' => \in_array($this->request->getPost('status'), ['approved', 'spam', 'pending'], true)
                ? $this->request->getPost('status')
                : 'approved',
        ]);

        return redirect()->to('admin/comments/' . $id)->with('message', 'Comment updated successfully.');
    }

    public function reply($postId)
    {
        $rules = [
            'author_name' => 'required|string|max_length[255]',
            'author_email' => 'required|valid_email|max_length[255]',
            'body' => 'required|string',
            'parent_id' => 'required|is_natural_no_zero',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        // The parent comment must exist and belong to the post being replied to.
        $parentId = (int) $this->request->getPost('parent_id');
        $parent = $this->commentModel->find($parentId);
        if (!$parent || (int) $parent['post_id'] !== (int) $postId) {
            return redirect()->back()->withInput()->with('error', 'Invalid reply context.');
        }

        $this->commentModel->save([
            'post_id' => (int) $postId,
            'parent_id' => $parentId,
            'author_name' => $this->request->getPost('author_name'),
            'author_email' => $this->request->getPost('author_email'),
            'body' => strip_tags((string) $this->request->getPost('body')),
            'status' => 'approved',
            'ip_address' => $this->request->getIPAddress(),
        ]);

        return redirect()->to('admin/comments')->with('message', 'Reply posted successfully.');
    }
}
