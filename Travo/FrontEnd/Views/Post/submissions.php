<?= $this->extend('Travo\\FrontEnd\\Views\\Layout\\App') ?>
<?= $this->section('content') ?>
<div class="container py-5" style="max-width: 58rem">
    <header class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h2 fw-bold mb-1">My story submissions</h1>
            <p class="text-secondary mb-0">Track the review status of your stories.</p>
        </div>
        <a href="<?= base_url('blog/submit') ?>" class="btn btn-primary">Submit another story</a>
    </header>

    <div class="vstack gap-3">
        <?php foreach ($submissions as $submission): ?>
            <?php
                $statusLabel = match ($submission['status']) {
                    'approved' => 'Published',
                    'rejected' => 'Not approved',
                    default => 'Pending review',
                };
                $statusClass = match ($submission['status']) {
                    'approved' => 'text-bg-success',
                    'rejected' => 'text-bg-danger',
                    default => 'text-bg-warning',
                };
            ?>
            <article class="card shadow-sm"><div class="card-body">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
                    <div>
                        <h2 class="h5 mb-1"><?= esc($submission['title']) ?></h2>
                        <p class="small text-secondary mb-2">Submitted <?= esc(date('M j, Y', strtotime($submission['created_at'] ?? 'now'))) ?></p>
                    </div>
                    <span class="badge <?= $statusClass ?>"><?= esc($statusLabel) ?></span>
                </div>
                <?php if (! empty($submission['excerpt'])): ?><p class="mb-0 mt-2"><?= esc($submission['excerpt']) ?></p><?php endif; ?>
                <?php if ($submission['status'] === 'approved' && ! empty($submission['published_slug'])): ?>
                    <a class="btn btn-sm btn-outline-primary mt-3" href="<?= base_url('blog/' . esc($submission['published_slug'], 'url')) ?>">View published story</a>
                <?php endif; ?>
            </div></article>
        <?php endforeach; ?>
        <?php if (! $submissions): ?>
            <div class="border rounded p-5 text-center">
                <h2 class="h5 fw-bold">No submissions yet</h2>
                <p class="text-secondary mb-3">Stories you send for review will appear here.</p>
                <a href="<?= base_url('blog/submit') ?>" class="btn btn-primary">Submit a story</a>
            </div>
        <?php endif; ?>
    </div>
    <?= $pager->links() ?>
</div>
<?= $this->endSection() ?>