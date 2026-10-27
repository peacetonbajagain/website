<?php
namespace Travo\Admin\Controllers;

use App\Controllers\BaseController;
use Travo\Admin\Models\CommentModel;

class CommentsController extends BaseController
{
    public function index()
    {
        $m = new CommentModel();
        $rows = $m->select('comments.*, p.title AS post_title, p.slug AS post_slug')
            ->join('posts p', 'p.id = comments.post_id', 'left')->orderBy('comments.id', 'DESC')->paginate(20);
        return view('Travo\Admin\Views\Comments\index', ['title' => 'Comments', 'comments' => $rows, 'pager' => $m->pager]);
    }

    public function status(int $id, string $status)
    {
        if (in_array($status, ['approved', 'pending', 'spam'], true)) {
            (new CommentModel())->update($id, ['status' => $status]);
            log_activity($status, 'comments', $id);
        }
        return redirect()->back()->with('success', 'Comment updated.');
    }

    public function delete(int $id)
    {
        (new CommentModel())->delete($id);
        log_activity('delete', 'comments', $id);
        return redirect()->back()->with('success', 'Comment deleted.');
    }
}
