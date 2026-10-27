<?= $this->extend('Travo\Admin\Views\Layout\admin') ?>
<?= $this->section('content') ?>
<div class="d-grid gap-3">
<?php foreach ($comments as $c): ?>
    <div class="card shadow-sm"><div class="card-body">
        <div class="d-flex flex-wrap justify-content-between gap-2"><span><strong><?= esc($c['author_name']) ?></strong> <span class="text-secondary">&lt;<?= esc($c['author_email']) ?>&gt; on <?= esc($c['post_title'] ?? '(deleted post)') ?></span></span>
            <span class="badge <?= $c['status'] === 'approved' ? 'text-bg-success' : ($c['status'] === 'spam' ? 'text-bg-danger' : 'text-bg-warning') ?>"><?= esc($c['status']) ?></span></div>
        <p class="my-3"><?= nl2br(esc($c['body'])) ?></p>
        <div class="d-flex flex-wrap gap-2">
            <?php foreach (['approved' => 'Approve', 'spam' => 'Spam', 'pending' => 'Unapprove'] as $s => $label): ?>
                <form method="post" action="<?= base_url('admin/comments/status/' . (int) $c['id'] . '/' . $s) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-primary"><?= $label ?></button></form>
            <?php endforeach; ?>
            <form method="post" action="<?= base_url('admin/comments/delete/' . (int) $c['id']) ?>" onsubmit="return confirm('Delete comment?')"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger">Delete</button></form>
        </div>
    </div></div>
<?php endforeach; if (! $comments): ?><p class="text-secondary">No comments yet.</p><?php endif; ?>
</div>
<?= $pager->links() ?>
<?= $this->endSection() ?>
