<?php

namespace Travo\Admin\Controllers;

use CodeIgniter\Controller;
use Config\Database;
use Travo\Admin\Libraries\ContentCleaner;
use Travo\Admin\Libraries\ImageUpload;
use Travo\Admin\Models\PostModel;
use Travo\Admin\Models\StorySubmissionModel;

class StorySubmissionsController extends Controller
{
    public function index()
    {
        $status = (string) ($this->request->getGet('status') ?? 'pending');
        if (! \in_array($status, ['pending', 'approved', 'rejected', 'all'], true)) {
            $status = 'pending';
        }

        $model = new StorySubmissionModel();
        $builder = $model
            ->select('story_submissions.*, users.username AS submitter_name')
            ->join('users', 'users.id = story_submissions.user_id', 'left')
            ->orderBy('story_submissions.created_at', 'DESC');
        if ($status !== 'all') {
            $builder->where('story_submissions.status', $status);
        }

        return view('Travo\\Admin\\Views\\Submissions\\index', [
            'title' => 'Story submissions',
            'submissions' => $builder->paginate(20),
            'pager' => $model->pager,
            'status' => $status,
        ]);
    }

    public function image(int $id)
    {
        $submission = model(StorySubmissionModel::class)->find($id);
        $path = $submission && $submission['status'] === 'pending' && $submission['image_path']
            ? ImageUpload::stagedAbsolutePath($submission['image_path'])
            : false;

        if ($path === false) {
            return $this->response->setStatusCode(404);
        }

        return $this->response
            ->setHeader('Content-Type', mime_content_type($path) ?: 'application/octet-stream')
            ->setHeader('Content-Disposition', 'inline')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody((string) file_get_contents($path));
    }

    public function approve(int $id)
    {
        $submissions = new StorySubmissionModel();
        $submission = $submissions->find($id);
        if ($submission === null || $submission['status'] !== 'pending') {
            return redirect()->to('admin/submissions')->with('error', 'Pending submission not found.');
        }

        $uploadId = null;
        $transactionStarted = false;
        $db = null;
        try {
            if (! empty($submission['image_path'])) {
                $uploadId = ImageUpload::publishStaged(
                    $submission['image_path'],
                    $submission['image_name'] ?? 'story-image',
                    (int) $submission['user_id'],
                );
            }

            helper('url');
            $db = Database::connect();
            $db->transBegin();
            $transactionStarted = true;

            $slug = url_title($submission['title'], '-', true);
            if ($slug === '') {
                $slug = 'story';
            }
            $postModel = new PostModel();
            $postId = $postModel->insert([
                'author_id' => (int) $submission['user_id'],
                'title' => $submission['title'],
                'slug' => $slug . '-' . bin2hex(random_bytes(4)),
                'content' => ContentCleaner::clean($submission['content']),
                'excerpt' => $submission['excerpt'],
                'thumbnail_id' => $uploadId,
                'post_type' => 'article',
                'status' => 1,
                'published_at' => date('Y-m-d H:i:s'),
            ], true);

            if (! $postId || ! $submissions->update($id, [
                'status' => 'approved',
                'post_id' => $postId,
                'image_path' => null,
                'image_name' => null,
            ])) {
                throw new \RuntimeException('The submission could not be approved.');
            }

            if (! $db->transStatus()) {
                throw new \RuntimeException('The approval transaction failed.');
            }
            $db->transCommit();
            $transactionStarted = false;

            log_activity('approve', 'story_submissions', $id, 'Approved story submission #' . $id);
            return redirect()->to('admin/submissions')->with('success', 'Story approved and published.');
        } catch (\Throwable $exception) {
            if ($transactionStarted && $db !== null) {
                $db->transRollback();
            }
            if ($uploadId !== null && ! empty($submission['image_path'])) {
                ImageUpload::restoreStaged($uploadId, $submission['image_path']);
            }

            log_message('error', 'Story approval failed: ' . $exception->getMessage());
            return redirect()->to('admin/submissions')->with('error', 'The story could not be approved. Please try again.');
        }
    }

    public function reject(int $id)
    {
        $submissions = new StorySubmissionModel();
        $submission = $submissions->find($id);
        if ($submission === null || $submission['status'] !== 'pending') {
            return redirect()->to('admin/submissions')->with('error', 'Pending submission not found.');
        }

        if (! $submissions->update($id, [
            'status' => 'rejected',
            'image_path' => null,
            'image_name' => null,
        ])) {
            return redirect()->to('admin/submissions')->with('error', 'The story could not be rejected.');
        }

        ImageUpload::discardStaged($submission['image_path']);
        log_activity('reject', 'story_submissions', $id, 'Rejected story submission #' . $id);

        return redirect()->to('admin/submissions')->with('success', 'Story rejected.');
    }
}