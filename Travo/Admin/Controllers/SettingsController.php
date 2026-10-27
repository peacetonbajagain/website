<?php

namespace Travo\Admin\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\Shield\Authorization\PermissionMatcher;

class SettingsController extends BaseController
{
    public function index()
    {
        $authGroups = config('AuthGroups');
        $matrix = setting('AuthGroups.matrix');
        $matrix = is_array($matrix) ? $matrix : $authGroups->matrix;
        $permissionMatrix = [];

        foreach ($authGroups->groups as $group => $details) {
            foreach ($authGroups->permissions as $permission => $description) {
                $permissionMatrix[$group][$permission] = PermissionMatcher::matches($permission, $matrix[$group] ?? []);
            }
        }

        return view('Travo\Admin\Views\Settings\index', [
            'title' => 'Site settings',
            'settings' => [
                'siteName' => site_name(),
                'tagline' => site_tagline(),
                'description' => (string) (setting('Travo.siteDescription') ?? ''),
                'contactEmail' => (string) (setting('Travo.contactEmail') ?? ''),
                'postsPerPage' => site_posts_per_page(),
                'allowRegistration' => (bool) setting('Auth.allowRegistration'),
                'commentsEnabled' => (bool) (setting('Travo.commentsEnabled') ?? true),
            ],
            'groups' => $authGroups->groups,
            'permissions' => $authGroups->permissions,
            'permissionMatrix' => $permissionMatrix,
            'canManagePermissions' => auth()->user()->can('users.manage-admins'),
        ]);
    }

    public function update()
    {
        if (! $this->validate([
            'site_name' => 'required|min_length[2]|max_length[100]',
            'site_tagline' => 'permit_empty|max_length[160]',
            'site_description' => 'permit_empty|max_length[300]',
            'contact_email' => 'permit_empty|valid_email|max_length[254]',
            'posts_per_page' => 'required|is_natural_no_zero|less_than_equal_to[50]',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        setting('Travo.siteName', trim((string) $this->request->getPost('site_name')));
        setting('Travo.siteTagline', trim((string) $this->request->getPost('site_tagline')));
        setting('Travo.siteDescription', trim((string) $this->request->getPost('site_description')));
        setting('Travo.contactEmail', trim((string) $this->request->getPost('contact_email')));
        setting('Travo.postsPerPage', (int) $this->request->getPost('posts_per_page'));

        if (auth()->user()->can('users.manage-admins')) {
            setting('Auth.allowRegistration', $this->request->getPost('allow_registration') === '1');
            setting('Travo.commentsEnabled', $this->request->getPost('comments_enabled') === '1');
        }

        return redirect()->to('admin/settings')->with('success', 'Site settings updated.');
    }

    public function updatePermissions()
    {
        if (! auth()->user()->can('users.manage-admins')) {
            return redirect()->to('admin/settings')->with('error', 'You cannot manage role permissions.');
        }

        $authGroups = config('AuthGroups');
        $matrix = setting('AuthGroups.matrix');
        $matrix = is_array($matrix) ? $matrix : $authGroups->matrix;
        $submitted = $this->request->getPost('permissions');
        $submitted = is_array($submitted) ? $submitted : [];
        $availablePermissions = array_keys($authGroups->permissions);

        foreach ($authGroups->groups as $group => $details) {
            if ($group === 'superadmin') {
                continue;
            }

            $selected = $submitted[$group] ?? [];
            $selected = is_array($selected) ? array_map('strval', $selected) : [];
            $matrix[$group] = array_values(array_intersect($availablePermissions, $selected));
        }

        setting('AuthGroups.matrix', $matrix);

        return redirect()->to('admin/settings')->with('success', 'Role permissions updated.');
    }
}