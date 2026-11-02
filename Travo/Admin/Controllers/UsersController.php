<?php
namespace Travo\Admin\Controllers;

use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Models\LoginModel;
use CodeIgniter\Shield\Models\UserIdentityModel;
use ReflectionException;
use Travo\Admin\Entities\User;

use Travo\Admin\Models\UserModel;

/**
 * Class UserController.
 */
class UsersController extends Controller
{
    use ResponseTrait;

    protected $users;

    public function index()
    {
        $userModel    = model(UserModel::class);
        $keyword      = $this->request->getGet('keyword');
        $roleFilter   = $this->request->getGet('role');
        $statusFilter = $this->request->getGet('status');

        $builder = $userModel
            ->select('users.id, users.username, users.first_name, users.last_name, users.active, users.created_at, users.last_active, users.deleted_at')
            ->join('auth_groups_users', 'auth_groups_users.user_id = users.id', 'left')
            ->whereIn('auth_groups_users.group', array_keys(config('AuthGroups')->groups))
            ->groupBy('users.id')
            ->withDeleted()
            ->orderBy('users.created_at', 'DESC');

        if (!empty($keyword)) {
            $builder->groupStart()
                ->like('users.username', $keyword)
                ->orLike('users.first_name', $keyword)
                ->orLike('users.last_name', $keyword)
                ->groupEnd();
        }

        if (!empty($roleFilter)) {
            $builder->where('auth_groups_users.group', $roleFilter);
        }

        if ($statusFilter === 'active') {
            $builder->where('users.active', 1)->where('users.deleted_at IS NULL', null, false);
        } elseif ($statusFilter === 'inactive') {
            $builder->where('users.active', 0);
        }

        $users = $builder->paginate(20);
        $pager = $userModel->pager;

        $authGroups = config('AuthGroups');
        $groups = setting('AuthGroups.groups') ?? $authGroups->groups;
        $matrix = setting('AuthGroups.matrix') ?? $authGroups->matrix;
        $rolePermissions = [];
        foreach ($groups as $groupName => $groupDetails) {
            foreach ($authGroups->permissions as $permission => $description) {
                foreach ($matrix[$groupName] ?? [] as $pattern) {
                    if ($pattern === $permission || str_ends_with($pattern, '.*') && str_starts_with($permission, substr($pattern, 0, -1))) {
                        $rolePermissions[$groupName][] = $description;
                        break;
                    }
                }
            }
        }

        return view('Travo\Admin\Views\Users\index', [
            'page_heading' => 'Manage Users',
            'users'        => $users,
            'pager'        => $pager,
            'keyword'      => $keyword,
            'roleFilter'   => $roleFilter,
            'statusFilter' => $statusFilter,
            'roleOptions' => $groups,
            'rolePermissions' => $rolePermissions,
        ]);
    }


    public function edit(int $userId)
    {
        // check if it's the current user
        $itsMe = (auth()->user()->can('me.edit') || auth()->user()->can('me.security')) && auth()->id() === $userId;
        // check if the user should be granted access
        if (!auth()->user()->can('users.edit') && !$itsMe) {
            return redirect()->back()->with('error', lang('notAuthorized'));
        }

        $users = new UserModel();

        $user = $users->find($userId);
        if ($user === null) {
            return redirect()->back()->with('error', lang('resourceNotFound', [lang('Users.userGenitive')]));
        }

        // Prevent non-superadmin from editing superadmin
        if ($user->inGroup('superadmin') && !auth()->user()->inGroup('superadmin')) {
            return redirect()->back()->with('error', lang('notAuthorized'));
        }

        $groups = setting('AuthGroups.groups');
        asort($groups);

        // Filter groups for non-superadmin
        if (!auth()->user()->inGroup('superadmin')) {
            unset($groups['superadmin']);
            // Admin can assign 'admin' group to others, so we don't unset 'admin' here.
        }

        $canEditGroups = auth()->id() !== $userId;

        helper('form');

        return view('Travo\Admin\Views\User\edit', [
            'user' => $user,
            'availableGroups' => $groups,
            'itsMe' => $itsMe,
            'canEditGroups' => $canEditGroups,
        ]);
    }

    public function create()
    {
        if (!auth()->user()->can('users.create')) {
            return redirect()->to('admin/users')->with('error', lang('notAuthorized'));
        }

        return redirect()->to('admin/users');
    }

    /**
     * Creates or saves the basic user details.
     *
     * @return RedirectResponse|void
     *
     * @throws ReflectionException
     */
    public function save(?int $userId = null)
    {
        $creating = $userId === null;
        // check if it's the current user
        $itsMe = auth()->user()->can('me.edit') && auth()->id() === $userId;
        // check if the user should be permitted access
        $allowedToSave = $creating
            ? auth()->user()->can('users.create')
            : auth()->user()->can('users.edit') || $itsMe;
        if (!$allowedToSave) {
            return redirect()->back()->with('error', lang('notAuthorized'));
        }

        $users = new UserModel();
        /**
         * @var User
         */
        if ($userId !== null) {
            $user = $users->find($userId);
            if ($user === null) {
                return redirect()->back()->withInput()->with('error', lang('resourceNotFound', [lang('Users.userGenitive')]));
            }
        } else {
            $user = new User();
        }

        // Prevent non-superadmin from editing superadmin
        if ($user->inGroup('superadmin') && !auth()->user()->inGroup('superadmin')) {
            return redirect()->back()->with('error', lang('notAuthorized'));
        }

        // Validation - using dynamic rules from UserModel if available, or defining here.
        // Username is set once at creation and is never changeable afterwards,
        // so it is only validated/required on create.
        $rules = [
            'first_name' => 'permit_empty|max_length[191]',
            'last_name' => 'permit_empty|max_length[191]',
            'email' => 'required|valid_email|max_length[254]',
        ];
        if (!$userId) {
            $rules['username'] = 'required|min_length[3]|max_length[128]|is_unique[users.username]';
        }
        if (!$userId) {
            $rules['password'] = 'required|min_length[8]';
            $rules['pass_confirm'] = 'matches[password]';
        } else {
            // Optional on update, but must still be strong if provided.
            $rules['password'] = 'permit_empty|min_length[8]';
            $rules['pass_confirm'] = 'permit_empty|matches[password]';
        }

        // Enforce email uniqueness across the identities table ourselves —
        // Shield stores the email there, not on the users table.
        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);
        $email = strtolower(trim((string) $this->request->getPost('email')));
        $clashingIdentity = $identityModel
            ->where('type', 'email_password')
            ->where('secret', $email)
            ->first();
        if ($clashingIdentity && (!$userId || (int) $clashingIdentity->user_id !== $userId)) {
            return redirect()->back()->withInput()
                ->with('error', 'That email address is already registered to another user.');
        }

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        // Fill in basic details (username is set once on create and never changed)
        $fillData = [
            'first_name' => $this->request->getPost('first_name') ?: ($this->request->getPost('username') ?? $user->first_name),
            'last_name' => $this->request->getPost('last_name'),
            'mobile' => $this->request->getPost('mobile'),
        ];
        if ($userId === null) {
            $fillData['username'] = $this->request->getPost('username') ?? $this->request->getPost('name'); // Handle both inputs
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            $user->fill($fillData);

            // Mark the user active if it is created by admin, or if it is marked active by admin
            $shouldActivate = $userId === null;
            if (!$shouldActivate) {
                $shouldActivate = $user->isNotActivated()
                    && auth()->user()->can('users.edit')
                    && (int) $this->request->getPost('status') === 1;
            }
            if ($shouldActivate) {
                $user->active = 1;
            } else if (auth()->user()->can('users.edit')) {
                $user->active = (int) $this->request->getPost('status');
            }

            // Save basic details
            $users->save($user);

            // We need an ID to on the entity to save groups.
            if ($user->id === null) {
                $user->id = $users->getInsertID();
            }

            // Save the new user's email/password
            $password = $this->request->getPost('password');

            $identity = $user->getEmailIdentity();
            if ($identity === null) {
                helper('text');
                $user->createEmailIdentity([
                    'email' => $email,
                    'password' => empty($password) ? random_string('alnum', 12) : $password,
                ]);
            }
            // Update existing user's email identity
            else {
                $identity->secret = $email;
                if (!empty($password)) {
                    $identity->secret2 = service('passwords')->hash($password);
                }
                if ($identity->hasChanged()) {
                    model(UserIdentityModel::class)->save($identity);
                }
            }

            // Save the user's groups if the user has right permissions
            // AND if not editing self (users cannot change their own groups)
            $canManageGroups = $creating
                ? auth()->user()->can('users.create')
                : auth()->user()->can('users.edit') && auth()->id() !== $userId;
            if ($canManageGroups) {
                $groups = $this->request->getPost('groups') ?? [$this->request->getPost('group')];
                $groups = array_values(array_filter((array) $groups, 'is_string'));

                // Only known groups may ever be assigned
                $knownGroups = array_keys(setting('AuthGroups.groups') ?? []);
                $groups = array_values(array_intersect($groups, $knownGroups));

                // If not superadmin, ensure they cannot add superadmin group
                // AND ensure they don't accidentally remove existing superadmin group
                if (!auth()->user()->inGroup('superadmin')) {
                    // 1. Remove restricted groups from input (security against tampering)
                    if (($key = array_search('superadmin', $groups)) !== false)
                        unset($groups[$key]);
                    $groups = array_values($groups);

                    // Admin CAN assign 'admin' group to others, so we allow it.

                    // 2. Preserve existing restricted groups
                    $currentGroups = $user->getGroups();
                    if (\in_array('superadmin', $currentGroups)) {
                        $groups[] = 'superadmin';
                    }
                }

                // Never leave a user without a group — fall back to the default group.
                if (empty($groups)) {
                    $defaultGroup = setting('AuthGroups.defaultGroup');
                    if ($defaultGroup && \in_array($defaultGroup, $knownGroups, true)) {
                        $groups = [$defaultGroup];
                    }
                }

                $user->syncGroups(...$groups);
            }

            log_activity($userId ? 'update' : 'create', 'users', (int) ($user->id ?? 0), ($userId ? 'Updated' : 'Created') . ' user: ' . ($user->username ?? ''));

            $db->transComplete();
        } catch (\Exception $e) {
            $db->transRollback();
            log_message('error', 'User save failed for id ' . ($userId ?? 'new') . ': ' . $e->getMessage());
            return redirect()->back()->withInput()->with('error', lang('unknownError', [], 'en'));
        }

        return redirect()->to('admin/users')->with('message', lang('resourceSaved', [lang('Users.user')]));
    }

    public function role(int $userId)
    {
        $actor = auth()->user();
        $canManageAdmins = $this->canManageAdmins();
        $userModel = new UserModel();
        $user = $userModel->find($userId);
        $group = (string) $this->request->getPost('group');
        $knownGroups = setting('AuthGroups.groups') ?? config('AuthGroups')->groups;

        if (! $actor?->can('users.edit') || $user === null || ! isset($knownGroups[$group])) {
            return redirect()->to('admin/users')->with('error', lang('notAuthorized'));
        }
        if (($user->inGroup('superadmin', 'admin', 'developer') || \in_array($group, ['superadmin', 'admin', 'developer'], true)) && ! $canManageAdmins) {
            return redirect()->to('admin/users')->with('error', lang('notAuthorized'));
        }

        $user->syncGroups($group);
        log_activity('role_change', 'users', $userId, 'Changed user group to ' . $group);

        return redirect()->to('admin/users')->with('message', 'User role updated.');
    }

    public function status(int $userId)
    {
        $actor = auth()->user();
        $canManageAdmins = $this->canManageAdmins();
        $userModel = new UserModel();
        $user = $userModel->find($userId);

        if (! $actor?->can('users.edit') || $user === null) {
            return redirect()->to('admin/users')->with('error', lang('notAuthorized'));
        }
        if ($user->inGroup('superadmin', 'admin', 'developer') && ! $canManageAdmins) {
            return redirect()->to('admin/users')->with('error', lang('notAuthorized'));
        }

        $user->active = ! $user->active;
        $userModel->save($user);
        log_activity('status_change', 'users', $userId, $user->active ? 'Activated user' : 'Deactivated user');

        return redirect()->to('admin/users')->with('message', 'User status updated.');
    }

    /**
     * Change user's password.
     *
     * @return RedirectResponse|void
     *
     * @throws ReflectionException
     */
    public function changePassword(?int $userId = null)
    {
        $itsMe = auth()->user()->can('me.security') && auth()->id() === $userId;
        if (!auth()->user()->can('users.edit') && !$itsMe) {
            return redirect()->back()->with('error', lang('notAuthorized'));
        }

        $users = new UserModel();
        /**
         * @var User
         */
        if ($userId !== null) {
            $user = $users->find($userId);
            if ($user === null) {
                return redirect()->back()->withInput()->with('error', lang('resourceNotFound', [lang('Users.userGenitive')]));
            }
        } else {
            $user = new User();
        }

        // Prevent non-superadmin from editing superadmin
        if ($user->inGroup('superadmin') && !auth()->user()->inGroup('superadmin')) {
            return redirect()->back()->with('error', lang('notAuthorized'));
        }

        if (!$this->validate(['password' => 'required|strong_password', 'pass_confirm' => 'required|matches[password]'])) {
            return redirect()->back()->withInput()->with('errors', service('validation')->getErrors());
        }

        // Save the new user's password
        $password = $this->request->getPost('password');
        $identity = $user->getEmailIdentity();

        if ($identity === null) {
            // User has no email identity yet — create one with the new password.
            helper('text');
            $user->createEmailIdentity([
                'email' => $this->request->getPost('email') ?? ($user->username . '@example.com'),
                'password' => $password,
            ]);
        } else {
            if ($password !== null) {
                $identity->secret2 = service('passwords')->hash($password);
            }

            if ($identity->hasChanged()) {
                model(UserIdentityModel::class)->save($identity);
            }
        }
        log_activity('password_change', 'users', (int) $userId, 'Password changed for user #' . $userId);

        return redirect()->to('admin/users')->with('message', lang('resourceSaved', [lang('Users.user')]));
    }

    /**
     * Delete the specified user.
     *
     * @return RedirectResponse
     */
    public function delete(int $userId)
    {
        $actor = auth()->user();
        if (!$actor->can('users.delete') || (int) $actor->id === $userId) {
            return redirect()->back()->with('error', lang('notAuthorized'));
        }

        $users = model(UserModel::class);
        /** @var User|null $user */
        $user = $users->find($userId);

        if ($user === null) {
            return redirect()->back()->with('error', lang('resourceNotFound', [lang('Users.userGenitive')]));
        }

        if ($user->inGroup('admin', 'superadmin', 'developer') && !$this->canManageAdmins()) {
            return redirect()->back()->with('error', lang('notAuthorized'));
        }

        if (!$users->delete($user->id)) {
            log_message('error', implode(' ', $users->errors()));

            return redirect()->back()->with('error', lang('unknownError'));
        }
        log_activity('delete', 'users', (int) $user->id, 'Deleted user: ' . $user->username);

        return redirect()->back()->with('message', lang('resourceDeleted', [lang('Users.user')]));
    }

    /**
     * Deletes multiple users from the database.
     * Called via the checked() records in the table.
     */
    public function deleteBatch()
    {
        if (!auth()->user()->can('users.delete')) {
            return redirect()->back()->with('error', lang('notAuthorized'));
        }

        $ids = $this->request->getPost('selects');

        if (empty($ids)) {
            return redirect()->back()->with('error', lang('resourcesNotSelected', [lang('Users.users')]));
        }
        $ids = array_map('intval', array_keys($ids));

        $users = model(UserModel::class);
        $actor = auth()->user();
        $canManageAdmins = $this->canManageAdmins();

        // Apply the same protections as single delete: protect the acting user
        // and administrative accounts unless the actor can manage admins.
        $protectedIds = array_filter([(int) ($actor->id ?? 0)]);
        if (!$canManageAdmins) {
            foreach ($users->withDeleted()->whereIn('id', $ids)->findAll() as $candidate) {
                if ($candidate->inGroup('admin', 'superadmin', 'developer')) {
                    $protectedIds[] = (int) $candidate->id;
                }
            }
        }
        $ids = array_values(array_diff($ids, $protectedIds));

        if (empty($ids)) {
            return redirect()->back()->with('error', lang('notAuthorized'));
        }

        if (!$users->delete($ids)) {
            log_message('error', implode(' ', $users->errors()));

            return redirect()->back()->with('error', lang('unknownError'));
        }
        log_activity('bulk_delete', 'users', implode(',', $ids), 'Bulk deleted ' . \count($ids) . ' users');

        return redirect()->back()->with('message', lang('resourcesDeleted', [lang('Users.users')]));
    }

    /**
     * Displays basic security info, like previous login info,
     * and ability to force a password reset, ban, etc.
     *
     * @return RedirectResponse|string
     */
    public function security(int $userId)
    {
        $itsMe = auth()->user()->can('me.security') && auth()->id() === $userId;
        if (!auth()->user()->can('users.edit') && !$itsMe) {
            return redirect()->to('admin/dashboard')->with('error', lang('notAuthorized'));
        }

        $users = model(UserModel::class);
        /** @var User|null $user */
        $user = $users->find($userId);
        if ($user === null) {
            return redirect()->back()->with('error', lang('resourceNotFound', [lang('Users.userGenitive')]));
        }

        // Prevent non-superadmin from viewing security of superadmin
        if ($user->inGroup('superadmin') && !auth()->user()->inGroup('superadmin')) {
            return redirect()->back()->with('error', lang('notAuthorized'));
        }

        /** @var LoginModel $loginModel */
        $loginModel = model(LoginModel::class);
        $logins = $loginModel->where('identifier', $user->email)->orderBy('date', 'desc')->findAll(20);

        return view('Travo\Admin\Views\User\security', [
            'user' => $user,
            'logins' => $logins,
        ]);
    }

    /**
     * Displays basic security info, like previous login info,
     * and ability to force a password reset, ban, etc.
     *
     * @return RedirectResponse|string
     */
    public function permissions(int $userId)
    {
        if (!auth()->user()->can('users.view')) {
            return redirect()->to('admin')->with('error', lang('notAuthorized'));
        }

        $users = model(UserModel::class);
        $user = $users->find($userId);
        if ($user === null) {
            return redirect()->back()->with('error', lang('resourceNotFound', [lang('Users.userGenitive')]));
        }

        // Prevent non-superadmin from viewing permissions of superadmin
        if ($user->inGroup('superadmin') && !auth()->user()->inGroup('superadmin')) {
            return redirect()->back()->with('error', lang('notAuthorized'));
        }

        $permissions = setting('AuthGroups.permissions');
        if (\is_array($permissions)) {
            ksort($permissions);
        }

        return view('Travo\Admin\Views\User\permissions', [
            'user' => $user,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Display the logged-in user's profile.
     *
     * @return string|RedirectResponse
     */
    public function profile()
    {
        if (!auth()->loggedIn()) {
            return redirect()->to('login')->with('error', 'Please login to view your profile.');
        }

        // Fetch user from UserModel to ensure we get our custom User entity
        $users = new UserModel();
        $user = $users->find(auth()->id());

        if (!$user) {
            return redirect()->to('login')->with('error', 'User not found.');
        }

        return view('Travo\Admin\Views\User\profile', [
            'page_heading' => 'My Profile',
            'user' => $user,
        ]);
    }

    /**
     * Updates the permissions for a single user.
     *
     * @return RedirectResponse
     * @throws ReflectionException
     */
    public function savePermissions(int $userId)
    {
        if (!auth()->user()->can('users.edit')) {
            return redirect()->to('admin')->with('error', lang('notAuthorized'));
        }

        // Users must never grant or revoke their own permissions.
        if (auth()->id() === $userId) {
            return redirect()->back()->with('error', lang('notAuthorized'));
        }

        $users = model(UserModel::class);
        /** @var User|null $user */
        $user = $users->find($userId);
        if ($user === null) {
            return redirect()->back()->with('error', lang('resourceNotFound', [lang('Users.userGenitive')]));
        }

        if ($user->inGroup('admin', 'superadmin', 'developer') && !$this->canManageAdmins()) {
            return redirect()->back()->with('error', lang('notAuthorized'));
        }

        $permissions = $this->request->getPost('permissions') ?? [];

        // if the administrator cannot manage admins, remove all user-management related permissions
        // unless they have been set previously
        if (!auth()->user()->can('users.manage-admins')) {
            foreach ($permissions as $key => $permission) {
                if (
                    !$user->hasPermission($permission)
                    && explode('.', $permission)[0] === 'users'
                ) {
                    unset($permissions[$key]);
                }
            }
        }

        $user->syncPermissions(...$permissions);
        log_activity('permissions_update', 'users', (int) $userId, 'Synced ' . \count($permissions) . ' direct permissions');

        return redirect()->back()->with('message', lang('resourceSaved', [lang('Users.permissions')]));
    }

    private function canManageAdmins(): bool
    {
        $actor = auth()->user();

        return $actor !== null && ($actor->inGroup('superadmin') || $actor->can('users.manage-admins'));
    }
}
