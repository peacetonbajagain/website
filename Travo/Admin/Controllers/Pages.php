<?php

namespace Travo\Admin\Controllers;

use CodeIgniter\API\ResponseTrait;
use Travo\Admin\Models\PageModel;
use CodeIgniter\Controller;

class Pages extends Controller
{
    use ResponseTrait;
    public function index()
    {
        if (!auth()->user()?->can('content.manage')) {
            return redirect()->to('/admin/dashboard')->with('error', 'Not authorized');
        }
        $data['page_heading'] = 'Manage Pages';
        $pagemodel = new PageModel();
        $data['pages'] = $pagemodel->findAll();
        echo view('Travo\Admin\Views\Pages\index', $data);
    }
    public function create()
    {
        if (!auth()->user()?->can('content.manage')) {
            return redirect()->to('/admin/pages')->with('error', 'Not authorized');
        }
        $data['page_heading'] = 'Create New Page';
        $data['page'] = [];
        return view('Travo\Admin\Views\Pages\form', $data);
    }
    public function save()
    {
        if (!auth()->user()?->can('content.manage')) {
            return redirect()->to('/admin/pages')->with('error', 'Not authorized');
        }

        $title = $this->request->getPost("title");
        
        // Preserve provided slug, or generate new one from title
        $slug = $this->request->getPost("slug");
        if (empty($slug)) {
            $slug = create_unique_slug($title, 'pages');
        }
        
        $data = [
            'author_id'  => intval(user_id()),
            'title'      => $title,
            'slug'       => $slug,
            'content'    => \Travo\Admin\Libraries\ContentCleaner::clean((string) $this->request->getPost("content")),
            'meta_title' => $this->request->getPost("meta_title"),
            'meta_desc'  => $this->request->getPost("meta_desc"),
        ];

        // Handle manual creation date if provided
        $createdAt = $this->request->getPost("created_at");
        if (!empty($createdAt)) {
            $data['created_at'] = $createdAt;
        }

        $rules = [
            'title'      => 'required|min_length[3]',
            'content'    => 'required',
            'meta_title' => 'required',
            'meta_desc'  => 'required'
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        } else {
            $pagemodel = new PageModel();
            $pagemodel->save($data);
            log_activity('create', 'pages', (int) $pagemodel->getInsertID(), 'Created page: ' . $title);
            return redirect()->to('/admin/pages')->with('message', 'Page Created Successfully');
        }
    }

    public function Edit(int $id)
    {
        if (!auth()->user()?->can('content.manage')) {
            return redirect()->to('/admin/pages')->with('error', 'Not authorized');
        }
        $data['page_heading'] = 'Update This Page';
        $pagemodel = new PageModel();
        $data['page'] = $pagemodel->find($id) ?? [];
        return view('Travo\Admin\Views\Pages\form', $data);
    }

    public function update($id)
    {
        if (!auth()->user()?->can('content.manage')) {
            return redirect()->to('/admin/pages')->with('error', 'Not authorized');
        }

        $title = $this->request->getPost("title");
        
        // Preserve existing slug if not provided, or generate new one
        $slug = $this->request->getPost("slug");
        if (empty($slug)) {
            $slug = create_unique_slug($title, 'pages', $id);
        }

        $data = [
            'title'      => $title,
            'slug'       => $slug,
            'content'    => \Travo\Admin\Libraries\ContentCleaner::clean((string) $this->request->getPost("content")),
            'meta_title' => $this->request->getPost("meta_title"),
            'meta_desc'  => $this->request->getPost("meta_desc"),
        ];

        // Ensure user can update the created_at date manually
        $createdAt = $this->request->getPost("created_at");
        if (!empty($createdAt)) {
            $data['created_at'] = $createdAt;
        }

        $rules = [
            'title'      => 'required|min_length[3]',
            'content'    => 'required',
            'meta_title' => 'required',
            'meta_desc'  => 'required'
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        } else {
            $pagemodel = new PageModel();
            $pagemodel->update($id, $data);
            log_activity('update', 'pages', (int) $id, 'Updated page: ' . $title);
            return redirect()->to('/admin/pages')->with('message', 'Page Updated Successfully');
        }
    }

    public function updatePublished()
    {
        if (!auth()->user()?->can('content.manage')) {
            return $this->failForbidden('Not authorized');
        }

        $id = $this->request->getPost('id');
        $status = $this->request->getPost('status');

        if (!is_numeric($id) || !in_array((string) $status, ['0', '1'], true)) {
            return $this->failValidationErrors('Invalid parameters');
        }

        $pagemodel = new PageModel();
        $pagemodel->update((int) $id, ['status' => (int) $status]);
        $response = ['status' => 200];
        return $this->respond($response);
    }

    public function delete($id)
    {
        if (!auth()->user()?->can('content.manage')) {
            return redirect()->to('/admin/pages')->with('error', 'Not authorized');
        }

        $pagemodel = new PageModel();
        $result = $pagemodel->delete($id);
        if ($result) {
            log_activity('delete', 'pages', (int) $id, 'Deleted page #' . $id);
            return redirect()->to('/admin/pages')->with('message', 'Page Deleted Successfully');
        } else {
            return redirect()->to('/admin/pages')->with('message', 'Page Only Deleted Error');
        }
    }

    public function autosave()
    {
        $actor = auth()->user();
        if (!$actor || $actor->can('content.manage') !== true) {
            return $this->failForbidden('Not authorized');
        }

        // Simple Autosave Logic
        $id = $this->request->getPost('id');
        $title = $this->request->getPost('title') ?? 'Untitled';
        $content = $this->request->getPost('content');
        
        // Generate slug if new and title present
        $slug = $this->request->getPost('slug');
        if(!$slug && $title) {
            $slug = create_unique_slug($title, 'pages', $id);
        }
        
        $pagemodel = new PageModel();
        
        $data = [
            'author_id'  => intval(user_id()),
            'title'      => $title,
            'slug'       => $slug,
            'content'    => \Travo\Admin\Libraries\ContentCleaner::clean((string) $content),
            'meta_title' => $this->request->getPost('meta_title'),
            'meta_desc'  => $this->request->getPost('meta_desc'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Handle created_at if provided
        $createdAt = $this->request->getPost('created_at');
        if($createdAt) {
            $data['created_at'] = $createdAt;
        }

        if ($id && is_numeric($id)) {
            // Update
            $pagemodel->update($id, $data);
            $response = [
                'status' => 200,
                'id' => $id,
                'timestamp' => date('H:i:s')
            ];
        } else {
            // Create New Draft
             $data['status'] = 0; // Default to draft
             $id = $pagemodel->insert($data);
             $response = [
                'status' => 200,
                'id' => $id,
                'timestamp' => date('H:i:s')
            ];
        }

        return $this->response->setJSON($response);
    }
}
