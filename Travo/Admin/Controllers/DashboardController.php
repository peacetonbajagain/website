<?php
namespace Travo\Admin\Controllers;

use App\Controllers\BaseController;

class DashboardController extends BaseController
{
    public function index()
    {
        $db = db_connect();
        return view('Travo\Admin\Views\Dashboard\index', [
            'title' => 'Dashboard',
            'stats' => [
                'Posts' => $db->table('posts')->where('deleted_at', null)->countAllResults(),
                'Published' => $db->table('posts')->where('deleted_at', null)->where('status', 1)->countAllResults(),
                'Pages' => $db->table('pages')->countAllResults(),
                'Pending comments' => $db->table('comments')->where('status', 'pending')->countAllResults(),
            ],
            'logs' => $db->table('activity_logs')->orderBy('id', 'DESC')->limit(10)->get()->getResultArray(),
        ]);
    }
}
