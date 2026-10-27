<?= $this->extend('Travo\\Admin\\Views\\Layout\\admin') ?>
<?= $this->section('content') ?>
<div class="row g-4 align-items-start">
    <section class="col-12 col-xl-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h2 class="h5 mb-1">Site identity</h2>
                <p class="small text-secondary mb-4">Control the name and metadata shown across the public site.</p>
                <form method="post" action="<?= base_url('admin/settings') ?>" class="vstack gap-3">
                    <?= csrf_field() ?>
                    <div>
                        <label for="site-name" class="form-label">Site name</label>
                        <input id="site-name" name="site_name" required minlength="2" maxlength="100" value="<?= esc(old('site_name', $settings['siteName']), 'attr') ?>" class="form-control">
                    </div>
                    <div>
                        <label for="site-tagline" class="form-label">Tagline</label>
                        <input id="site-tagline" name="site_tagline" maxlength="160" value="<?= esc(old('site_tagline', $settings['tagline']), 'attr') ?>" class="form-control">
                    </div>
                    <div>
                        <label for="site-description" class="form-label">Default search description</label>
                        <textarea id="site-description" name="site_description" maxlength="300" rows="3" class="form-control"><?= esc(old('site_description', $settings['description'])) ?></textarea>
                    </div>
                    <div>
                        <label for="contact-email" class="form-label">Contact email</label>
                        <input id="contact-email" name="contact_email" type="email" maxlength="254" value="<?= esc(old('contact_email', $settings['contactEmail']), 'attr') ?>" class="form-control">
                    </div>
                    <div>
                        <label for="posts-per-page" class="form-label">Articles per page</label>
                        <input id="posts-per-page" name="posts_per_page" type="number" min="1" max="50" required value="<?= esc(old('posts_per_page', (string) $settings['postsPerPage']), 'attr') ?>" class="form-control">
                    </div>
                    <?php if ($canManagePermissions): ?>
                        <fieldset class="border-top pt-3">
                            <legend class="h6">Account and reader options</legend>
                            <div class="form-check mb-2">
                                <input id="allow-registration" name="allow_registration" type="checkbox" value="1" class="form-check-input" <?= old('allow_registration', $settings['allowRegistration'] ? '1' : '') === '1' ? 'checked' : '' ?>>
                                <label for="allow-registration" class="form-check-label">Allow new account registration</label>
                            </div>
                            <div class="form-check">
                                <input id="comments-enabled" name="comments_enabled" type="checkbox" value="1" class="form-check-input" <?= old('comments_enabled', $settings['commentsEnabled'] ? '1' : '') === '1' ? 'checked' : '' ?>>
                                <label for="comments-enabled" class="form-check-label">Allow reader comments</label>
                            </div>
                        </fieldset>
                    <?php endif; ?>
                    <button class="btn btn-primary align-self-start">Save settings</button>
                </form>
            </div>
        </div>
    </section>

    <section class="col-12 col-xl-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h2 class="h5 mb-1">Role permissions</h2>
                        <p class="small text-secondary mb-0">Choose which capabilities each role receives.</p>
                    </div>
                    <?php if (! $canManagePermissions): ?><span class="badge text-bg-secondary">Superadmin only</span><?php endif; ?>
                </div>
                <form method="post" action="<?= base_url('admin/settings/permissions') ?>">
                    <?= csrf_field() ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead class="table-light">
                                <tr><th scope="col">Capability</th><?php foreach ($groups as $group => $details): ?><th scope="col" class="text-center"><?= esc($details['title']) ?></th><?php endforeach; ?></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($permissions as $permission => $description): ?>
                                <tr>
                                    <th scope="row"><span class="d-block small"><?= esc($description) ?></span><code class="small"><?= esc($permission) ?></code></th>
                                    <?php foreach ($groups as $group => $details): ?>
                                        <td class="text-center">
                                            <?php if ($canManagePermissions && $group !== 'superadmin'): ?>
                                                <input type="checkbox" class="form-check-input" name="permissions[<?= esc($group, 'attr') ?>][]" value="<?= esc($permission, 'attr') ?>" aria-label="<?= esc($details['title'] . ': ' . $description, 'attr') ?>" <?= $permissionMatrix[$group][$permission] ? 'checked' : '' ?>>
                                            <?php else: ?>
                                                <span aria-label="<?= $permissionMatrix[$group][$permission] ? 'Granted' : 'Not granted' ?>"><?= $permissionMatrix[$group][$permission] ? '&#10003;' : '&mdash;' ?></span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($canManagePermissions): ?>
                        <p class="form-text">Superadmin permissions are fixed to preserve full site access.</p>
                        <button class="btn btn-outline-primary">Save role permissions</button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </section>
</div>
<?= $this->endSection() ?>