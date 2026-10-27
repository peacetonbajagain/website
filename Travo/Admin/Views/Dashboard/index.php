<?= $this->extend('Travo\Admin\Views\Layout\admin') ?>
<?= $this->section('content') ?>
<section class="row row-cols-2 row-cols-xl-4 g-3 mb-4">
    <?php foreach ($stats as $label => $count): ?>
        <article class="col"><div class="card h-100 shadow-sm"><div class="card-body">
            <p class="small fw-bold text-uppercase text-secondary"><?= esc($label) ?></p>
            <p class="fs-2 fw-bold font-monospace mb-0"><?= (int) $count ?></p>
        </div></div>
        </article>
    <?php endforeach; ?>
</section>

<div class="row g-4">
    <section class="col-xl-8">
        <div class="card shadow-sm">
        <div class="card-header bg-transparent d-flex align-items-center justify-content-between">
            <h2 class="h6 fw-bold mb-0">Recent activity</h2>
            <span class="small text-secondary">Latest 10 actions</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th class="ps-3">Staff member</th><th>Action</th><th>Item</th><th class="text-nowrap pe-3">Time</th></tr></thead>
                <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td class="ps-3 fw-semibold"><?= esc($log['user_name'] ?? 'System') ?></td>
                        <td><?= esc(ucfirst($log['action'])) ?></td>
                        <td class="text-secondary"><?= esc($log['module']) ?><?= $log['target_id'] ? ' · ' . esc($log['target_id']) : '' ?></td>
                        <td class="text-nowrap small text-secondary pe-3"><?= esc(date('M j, H:i', strtotime($log['created_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (! $logs): ?><tr><td class="text-center text-secondary py-4" colspan="4">No activity has been recorded yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        </div>
    </section>

    <aside class="col-xl-4">
        <div class="card shadow-sm"><div class="card-body">
        <h2 class="h6 fw-bold">Quick actions</h2>
        <div class="list-group list-group-flush mt-2">
            <a href="<?= base_url('admin/posts/new') ?>" class="list-group-item list-group-item-action px-0">Write a story <span aria-hidden="true" class="float-end">+</span></a>
            <a href="<?= base_url('admin/gallery') ?>" class="list-group-item list-group-item-action px-0">Manage media <span aria-hidden="true" class="float-end">›</span></a>
            <a href="<?= base_url('admin/categories') ?>" class="list-group-item list-group-item-action px-0">Organize sections <span aria-hidden="true" class="float-end">›</span></a>
            <a href="<?= base_url('admin/comments') ?>" class="list-group-item list-group-item-action px-0">Review comments <span aria-hidden="true" class="float-end">›</span></a>
            <a href="<?= base_url('admin/users') ?>" class="list-group-item list-group-item-action px-0">Manage users <span aria-hidden="true" class="float-end">›</span></a>
        </div>
        </div></div>
    </aside>
</div>
<?= $this->endSection() ?>
