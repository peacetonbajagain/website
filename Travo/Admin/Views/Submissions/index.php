<?= $this->extend('Travo\\Admin\\Views\\Layout\\admin') ?>
<?= $this->section('content') ?>
<nav class="nav nav-tabs mb-4" aria-label="Submission status">
    <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $value => $label): ?>
        <a class="nav-link <?= $status === $value ? 'active' : '' ?>" href="<?= base_url('admin/submissions?status=' . $value) ?>"><?= esc($label) ?></a>
    <?php endforeach; ?>
</nav>

<div class="vstack gap-3">
    <?php foreach ($submissions as $submission): ?>
        <article class="card shadow-sm"><div class="card-body">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div>
                    <h2 class="h5 mb-1"><?= esc($submission['title']) ?></h2>
                    <p class="small text-secondary mb-2">By <?= esc($submission['submitter_name'] ?? 'Unknown') ?> · <?= esc($submission['created_at'] ?? '') ?></p>
                    <span class="badge <?= $submission['status'] === 'approved' ? 'text-bg-success' : ($submission['status'] === 'rejected' ? 'text-bg-danger' : 'text-bg-warning') ?>"><?= esc(ucfirst($submission['status'])) ?></span>
                </div>
                <?php if ($submission['status'] === 'pending'): ?>
                    <div class="d-flex gap-2">
                        <form method="post" action="<?= base_url('admin/submissions/approve/' . (int) $submission['id']) ?>" onsubmit="return confirm('Approve and publish this story?')">
                            <?= csrf_field() ?><button class="btn btn-sm btn-success">Approve and publish</button>
                        </form>
                        <form method="post" action="<?= base_url('admin/submissions/reject/' . (int) $submission['id']) ?>" onsubmit="return confirm('Reject this story?')">
                            <?= csrf_field() ?><button class="btn btn-sm btn-outline-danger">Reject</button>
                        </form>
                    </div>
                <?php elseif (! empty($submission['post_id'])): ?>
                    <a class="btn btn-sm btn-outline-primary" href="<?= base_url('admin/posts/edit/' . (int) $submission['post_id']) ?>">Edit published post</a>
                <?php endif; ?>
            </div>
            <?php if (! empty($submission['excerpt'])): ?><p class="mt-3 mb-2 fw-semibold"><?= esc($submission['excerpt']) ?></p><?php endif; ?>
            <details class="mt-3">
                <summary>Read submitted story</summary>
                <div class="border rounded p-3 mt-2"><?= $submission['content'] ?></div>
            </details>
            <?php if ($submission['status'] === 'pending' && ! empty($submission['image_path'])): ?>
                <figure class="mt-3 mb-0" style="max-width: 22rem">
                    <img src="<?= base_url('admin/submissions/image/' . (int) $submission['id']) ?>" alt="Cover image submitted with this story" class="img-fluid rounded">
                    <figcaption class="small text-secondary mt-1"><?= esc($submission['image_name'] ?? 'Submitted cover image') ?></figcaption>
                </figure>
            <?php endif; ?>
        </div></article>
    <?php endforeach; ?>
    <?php if (! $submissions): ?><p class="text-secondary">No submissions in this view.</p><?php endif; ?>
</div>
<?= $pager->links() ?>
<?= $this->endSection() ?>