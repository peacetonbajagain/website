<?php
namespace Travo\Admin\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\Shield\Authorization\PermissionMatcher;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Exceptions\ValidationException;
use CodeIgniter\Shield\Models\UserModel;

class UsersController extends BaseController
{
    public function index()
    {
        $model = new UserModel();
        $users = $model->withGroups()->orderBy('id', 'DESC')->paginate(20);
        $authGroups = config('AuthGroups');

        return view('Travo\Admin\Views\Users\index', [
            'title' => 'Users',
            'users' => $users,
            'pager' => $model->pager,
            'roleOptions' => $this->roleOptions(),
            'rolePermissions' => $this->rolePermissions($authGroups),
        ]);
    }

    public function create()
    {
        if (! $this->validate([
            'username' => 'required|min_length[2]|max_length[30]',
            'email' => 'required|valid_email|max_length[254]',
            'password' => 'required|min_length[8]',
            'group' => 'required',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $username = trim((string) $this->request->getPost('username'));
        $email = strtolower(trim((string) $this->request->getPost('email')));
        $password = (string) $this->request->getPost('password');
        $model = new UserModel();
        if ($model->findByCredentials(['email' => $email]) !== null || $model->findByCredentials(['username' => $username]) !== null) {
            return redirect()->back()->withInput()->with('error', 'That email address or username is already registered.');
        }

        $group = (string) $this->request->getPost('group');
        if (! $this->canAssignGroup($group)) {
            return redirect()->back()->withInput()->with('error', 'You cannot assign that user group.');
        }

        $passwordUser = $model->createNewUser(['username' => $username]);
        $passwordUser->setEmail($email);
        $passwordCheck = service('passwords')->check($password, $passwordUser);
        if (! $passwordCheck->isOK()) {
            return redirect()->back()->withInput()->with('error', $passwordCheck->reason());
        }

        $user = $model->createNewUser([
            'username' => $username,
            'active' => true,
        ]);
        $user->setEmail($email);
        $user->setPassword($password);
        try {
            $model->save($user);
        } catch (ValidationException) {
            return redirect()->back()->withInput()->with('error', implode(' ', $model->errors()));
        }

        $user = $model->findById($model->getInsertID());
        if (! $user) {
            return redirect()->to('admin/users')->with('error', 'User was created but could not be loaded.');
        }
        $user->syncGroups($group);

        log_activity('create', 'users', (int) $user->id, $email);

        return redirect()->to('admin/users')->with('success', 'User created.');
    }

    public function role(int $id)
    {
        $group = (string) $this->request->getPost('group');
        $model = new UserModel();
        $user = $model->withGroups()->find($id);

        if (! $user || ! $this->canManageUser($user, 'users.edit') || ! $this->canAssignGroup($group)) {
            return redirect()->to('admin/users')->with('error', 'You cannot change this user group.');
        }

        if ((int) auth()->user()->id === (int) $user->id) {
            return redirect()->to('admin/users')->with('error', 'You cannot change your own group.');
        }

        if ($user->inGroup('superadmin') && $group !== 'superadmin' && $this->countOtherSuperadmins($id) === 0) {
            return redirect()->to('admin/users')->with('error', 'The site must retain at least one superadmin.');
        }

        $user->syncGroups($group);
        log_activity('update group', 'users', $id, $group);

        return redirect()->to('admin/users')->with('success', 'User group updated.');
    }

    public function status(int $id)
    {
        $model = new UserModel();
        $user = $model->withGroups()->find($id);

        if (! $user || ! $this->canManageUser($user, 'users.edit')) {
            return redirect()->to('admin/users')->with('error', 'You cannot change this user status.');
        }

        if ((int) auth()->user()->id === (int) $user->id) {
            return redirect()->to('admin/users')->with('error', 'You cannot deactivate your own account.');
        }

        $user->active ? $user->deactivate() : $user->activate();
        log_activity($user->active ? 'deactivate' : 'activate', 'users', $id);

        return redirect()->to('admin/users')->with('success', 'User status updated.');
    }

    public function delete(int $id)
    {
        $model = new UserModel();
        $user = $model->withGroups()->find($id);

        if (! $user || ! $this->canManageUser($user, 'users.delete')) {
            return redirect()->to('admin/users')->with('error', 'You cannot delete this user.');
        }

        if ((int) auth()->user()->id === (int) $user->id) {
            return redirect()->to('admin/users')->with('error', 'You cannot delete your own account.');
        }

        if ($user->inGroup('superadmin') && $this->countOtherSuperadmins($id) === 0) {
            return redirect()->to('admin/users')->with('error', 'The site must retain at least one superadmin.');
        }

        $model->delete($id);
        log_activity('delete', 'users', $id, $user->getEmail() ?? '');

        return redirect()->to('admin/users')->with('success', 'User deleted.');
    }

    private function roleOptions(): array
    {
        $groups = config('AuthGroups')->groups;
        if (! auth()->user()->can('users.manage-admins')) {
            $groups = array_intersect_key($groups, ['user' => true, 'beta' => true]);
        }

        return $groups;
    }

    private function rolePermissions(object $authGroups): array
    {
        $rolePermissions = [];
        foreach ($authGroups->groups as $name => $group) {
            foreach ($authGroups->permissions as $permission => $description) {
                if (PermissionMatcher::matches($permission, $authGroups->matrix[$name] ?? [])) {
                    $rolePermissions[$name][] = $description;
                }
            }
        }

        return $rolePermissions;
    }

    private function canAssignGroup(string $group): bool
    {
        return array_key_exists($group, $this->roleOptions());
    }

    private function canManageUser(User $user, string $permission): bool
    {
        $currentUser = auth()->user();

        return $currentUser->can($permission)
            && ($currentUser->can('users.manage-admins') || ! $user->inGroup('admin', 'superadmin', 'developer'));
    }

    private function countOtherSuperadmins(int $exceptId): int
    {
        $users = (new UserModel())->withGroups()->findAll();
        $count = 0;
        foreach ($users as $user) {
            if ((int) $user->id !== $exceptId && $user->inGroup('superadmin')) {
                $count++;
            }
        }

        return $count;
    }
}