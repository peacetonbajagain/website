<?php

namespace Travo\Admin\Controllers;

use CodeIgniter\Controller;
use Travo\Admin\Models\PageModel;
use Travo\Admin\Models\PostModel;
use Travo\Admin\Models\UploadModel;
use Travo\Admin\Models\UserModel;
use Travo\Admin\Models\ActivityLogModel;

class Dashboard extends Controller
{
    public function index()
    {
        if (!auth()->user()?->can('admin.access')) {
            return redirect()->to('/')->with('error', 'Not authorized');
        }

        $postModel           = new PostModel();
        $pageModel           = new PageModel();
        $uploadModel         = new UploadModel();
        $userModel           = new UserModel();
        $activityLogModel = new ActivityLogModel();

        $data['stats'] = [
            'Users' => $userModel->countAllResults(),
            'Posts' => $postModel->countAllResults(),
            'Pages' => $pageModel->countAllResults(),
            'Media' => $uploadModel->countAllResults(),
        ];
        $data['logs'] = $activityLogModel->orderBy('created_at', 'DESC')->limit(10)->find();

        return view('Travo\Admin\Views\Dashboard\index', $data);
    }
}
