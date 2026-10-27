<?= $this->extend('Travo\\Admin\\Views\\Layout\\admin') ?>
<?= $this->section('content') ?>
<div class="row g-4 align-items-start">
    <section class="col-12 col-xl-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h2 class="h5 mb-3">Add user</h2>
                <form method="post" action="<?= base_url('admin/users/create') ?>" class="vstack gap-3">
                    <?= csrf_field() ?>
                    <div>
                        <label for="username" class="form-label">Username</label>
                        <input id="username" name="username" required minlength="2" maxlength="30" value="<?= esc(old('username'), 'attr') ?>" class="form-control">
                    </div>
                    <div>
                        <label for="email" class="form-label">Email</label>
                        <input id="email" name="email" type="email" required maxlength="254" value="<?= esc(old('email'), 'attr') ?>" class="form-control">
                    </div>
                    <div>
                        <label for="password" class="form-label">Temporary password</label>
                        <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password" class="form-control">
                        <div class="form-text">At least 8 characters.</div>
                    </div>
                    <div>
                        <label for="group" class="form-label">User group</label>
                        <select id="group" name="group" required class="form-select">
                            <?php foreach ($roleOptions as $name => $group): ?>
                                <option value="<?= esc($name, 'attr') ?>" <?= old('group', 'user') === $name ? 'selected' : '' ?>><?= esc($group['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn btn-primary">Create user</button>
                </form>
            </div>
        </div>
    </section>

    <section class="col-12 col-xl-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="d-flex justify-content-between align-items-center gap-3 p-4">
                    <h2 class="h5 mb-0">User accounts</h2>
                    <span class="text-secondary small"><?= count($users) ?> shown</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light"><tr><th class="ps-4">Account</th><th>Group</th><th>Status</th><th class="text-end pe-4">Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($users as $user): ?>
                            <?php $groups = $user->getGroups() ?? []; $group = reset($groups) ?: 'user'; $canEdit = auth()->user()->can('users.edit') && (int) auth()->user()->id !== (int) $user->id && (auth()->user()->can('users.manage-admins') || ! $user->inGroup('admin', 'superadmin', 'developer')); $canDelete = auth()->user()->can('users.delete') && $canEdit; ?>
                            <tr>
                                <td class="ps-4"><div class="fw-semibold"><?= esc($user->username ?? '—') ?></div><div class="small text-secondary"><?= esc($user->getEmail() ?? '') ?></div></td>
                                <td>
                                    <?php if ($canEdit): ?>
                                        <form method="post" action="<?= base_url('admin/users/role/' . (int) $user->id) ?>" class="d-flex gap-2">
                                            <?= csrf_field() ?>
                                            <select name="group" aria-label="Group for <?= esc($user->username ?? 'user', 'attr') ?>" class="form-select form-select-sm">
                                                <?php foreach ($roleOptions as $name => $option): ?>
                                                    <option value="<?= esc($name, 'attr') ?>" <?= $group === $name ? 'selected' : '' ?>><?= esc($option['title']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button class="btn btn-sm btn-outline-primary">Save</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary"><?= esc(config('AuthGroups')->groups[$group]['title'] ?? $group) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge <?= $user->active ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $user->active ? 'Active' : 'Inactive' ?></span></td>
                                <td class="text-end pe-4">
                                    <?php if ($canEdit): ?>
                                        <div class="d-flex justify-content-end gap-2">
                                            <form method="post" action="<?= base_url('admin/users/status/' . (int) $user->id) ?>">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-sm <?= $user->active ? 'btn-outline-danger' : 'btn-outline-success' ?>"><?= $user->active ? 'Deactivate' : 'Activate' ?></button>
                                            </form>
                                            <?php if ($canDelete): ?>
                                                <form method="post" action="<?= base_url('admin/users/delete/' . (int) $user->id) ?>" onsubmit="return confirm('Delete <?= esc($user->username ?? 'this user', 'attr') ?>? This account will be soft-deleted.')">
                                                    <?= csrf_field() ?>
                                                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?><span class="text-secondary small">—</span><?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (! $users): ?><tr><td colspan="4" class="text-center text-secondary py-5">No user accounts found.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="p-3"><?= $pager->links() ?></div>
            </div>
        </div>
    </section>
</div>
<section class="mt-4">
    <details class="border-top pt-3">
        <summary class="fw-semibold">Permissions by role</summary>
        <div class="table-responsive mt-3">
            <table class="table table-sm align-middle">
                <thead class="table-light"><tr><th scope="col">Role</th><th scope="col">Capabilities</th></tr></thead>
                <tbody>
                <?php foreach (config('AuthGroups')->groups as $name => $role): ?>
                    <tr>
                        <th scope="row"><?= esc($role['title']) ?></th>
                        <td>
                            <?php if (! empty($rolePermissions[$name])): ?>
                                <?= esc(implode('; ', $rolePermissions[$name])) ?>
                            <?php else: ?>
                                <span class="text-secondary">No administrative capabilities</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </details>
</section>
<?= $this->endSection() ?>