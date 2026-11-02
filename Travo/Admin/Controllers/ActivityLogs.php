<?php

namespace Travo\Admin\Controllers;

use CodeIgniter\Controller;
use Travo\Admin\Models\ActivityLogModel;

class ActivityLogs extends Controller
{
    public function index()
    {
        if (!auth()->user()->can('admin.access')) {
            return redirect()->to('/admin/dashboard')->with('error', 'Access denied.');
        }

        $activityLogModel = new ActivityLogModel();
        
        $module = $this->request->getGet('module');
        $action = $this->request->getGet('action');
        $user_id = $this->request->getGet('user_id');

        $query = $activityLogModel->orderBy('created_at', 'DESC');

        if (!empty($module)) {
            $query->where('module', $module);
        }
        if (!empty($action)) {
            $query->where('action', $action);
        }
        if (!empty($user_id)) {
            $query->where('user_id', $user_id);
        }

        $data['page_heading'] = 'Activity Logs';
        $data['logs'] = $query->paginate(25);
        $data['pager'] = $activityLogModel->pager;
        $data['filters'] = [
            'module' => $module,
            'action' => $action,
            'user_id' => $user_id
        ];

        return view('Travo\Admin\Views\ActivityLogs\index', $data);
    }
}
