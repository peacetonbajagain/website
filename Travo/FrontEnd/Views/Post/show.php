<?= $this->extend('Travo\FrontEnd\Views\Layout\App') ?>
<?= $this->section('content') ?>

<div class="container py-5" style="max-width: 58rem">

    <!-- Breadcrumb -->
    <nav class="small fw-semibold text-secondary mb-4" aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="<?= base_url('blog') ?>">News</a></li>
        <?php if (! empty($post['category_slug'])): ?>
            <li class="breadcrumb-item"><a href="<?= base_url('blog/category/' . esc($post['category_slug'], 'url')) ?>"><?= esc($post['category_title']) ?></a></li>
        <?php endif; ?>
        <li class="breadcrumb-item active text-truncate" aria-current="page"><?= esc($post['title']) ?></li>
        </ol>
    </nav>

    <!-- Article Header -->
    <header class="text-center mx-auto mb-4" style="max-width: 48rem">
        <?php if (! empty($post['category_title'])): ?>
            <span class="badge text-bg-primary mb-3"><?= esc($post['category_title']) ?></span>
        <?php endif; ?>
        <h1 class="display-5 fw-bold text-body"><?= esc($post['title']) ?></h1>
        <div class="d-flex flex-wrap align-items-center justify-content-center gap-2 small text-secondary mt-3">
            <span>By <strong class="text-body"><?= esc($post['author_name'] ?: 'Editorial Desk') ?></strong></span>
            <span aria-hidden="true">&bull;</span>
            <span><?= esc(date('F j, Y', strtotime($post['published_at'] ?: $post['created_at']))) ?></span>
            <span aria-hidden="true">&bull;</span>
            <span><?= (int) ($post['view_count'] ?? 0) ?> views</span>
        </div>
    </header>

    <?php if (! empty($post['thumbnail'])): ?>
        <img src="<?= esc(post_img($post['thumbnail']), 'attr') ?>" alt="<?= esc($post['title'], 'attr') ?>" class="story-image w-100 rounded mb-4">
    <?php else: ?>
        <div class="story-image w-100 rounded mb-4" aria-hidden="true"></div>
    <?php endif; ?>

    <?php if (! empty($post['excerpt'])): ?>
        <div class="alert alert-primary border-start border-4 fst-italic mb-4"><?= esc($post['excerpt']) ?></div>
    <?php endif; ?>

    <!-- Body: sanitised by ContentCleaner when saved in admin (the only raw echo on this page) -->
    <div class="prose mb-5"><?= $post['content'] ?></div>

    <!-- Footer / Share -->
    <div class="py-4 border-top d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4" x-data>
        <div class="d-flex align-items-center gap-2">
            <span class="small fw-semibold text-secondary">Share this story:</span>
            <button type="button" @click="navigator.clipboard && navigator.clipboard.writeText(window.location.href); $el.textContent='Copied!'"
                    class="btn btn-sm btn-outline-secondary">Copy link</button>
        </div>
        <a href="<?= base_url('blog') ?>" class="small fw-semibold">&larr; Back to all news</a>
    </div>

    <?php if ((bool) (setting('Travo.commentsEnabled') ?? true)): ?>
    <!-- Comments -->
    <section class="pt-4 border-top" id="comments-section">
        <h2 class="h4 fw-bold mb-4">Reader Comments (<?= count($comments ?? []) ?>)</h2>

        <div id="comments-list" class="d-grid gap-3 mb-4">
            <?php if (! empty($comments)): ?>
                <?php foreach ($comments as $c): ?>
                    <div class="card"><div class="card-body d-flex align-items-start gap-3">
                        <div class="comment-avatar rounded-circle bg-primary text-white fw-bold small d-flex align-items-center justify-content-center flex-shrink-0">
                            <?= esc(strtoupper(mb_substr($c['author_name'] ?? 'R', 0, 1))) ?>
                        </div>
                        <div class="min-w-0 flex-grow-1">
                            <div class="d-flex align-items-center justify-content-between gap-2">
                                <span class="fw-bold small text-body"><?= esc($c['author_name']) ?></span>
                                <span class="small text-secondary" title="<?= esc($c['created_at'], 'attr') ?>"><?= esc(date('M d, Y', strtotime($c['created_at']))) ?></span>
                            </div>
                            <p class="small text-body-secondary mt-2 mb-0"><?= nl2br(esc($c['body'])) ?></p>
                        </div>
                    </div></div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-4 bg-body-tertiary rounded text-secondary small">No comments yet. Be the first to share your thoughts below.</div>
            <?php endif; ?>
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-4">
            <h3 class="h6 fw-bold mb-3">Leave a comment</h3>
            <form id="comment-form" action="<?= base_url('blog/comment') ?>" method="post" class="vstack gap-3">
                <?= csrf_field() ?>
                <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
                <input type="text" name="website" tabindex="-1" autocomplete="off" class="d-none" aria-hidden="true"><!-- honeypot -->

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Your name <span class="text-danger">*</span></label>
                        <input type="text" name="author_name" required maxlength="80" value="<?= auth()->loggedIn() ? esc(auth()->user()->username ?? '', 'attr') : '' ?>" placeholder="Your name"
                               class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Email <span class="text-danger">*</span> <span class="text-secondary fw-normal">(Never published)</span></label>
                        <input type="email" name="author_email" required value="<?= auth()->loggedIn() ? esc(auth()->user()->email ?? '', 'attr') : '' ?>" placeholder="you@example.com"
                               class="form-control">
                    </div>
                </div>
                <div>
                    <label class="form-label small fw-semibold">Comment <span class="text-danger">*</span></label>
                    <textarea name="body" rows="3" required maxlength="2000" placeholder="Share your view…"
                              class="form-control"></textarea>
                </div>
                <div id="comment-feedback"></div>
                <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2">
                    <span class="small text-secondary">Comments are reviewed before they appear.</span>
                    <button type="submit" id="comment-submit-btn" class="btn btn-primary">Post comment</button>
                </div>
            </form>
            </div>
        </div>
    </section>

    <script>
    document.getElementById('comment-form')?.addEventListener('submit', function (e) {
        e.preventDefault();
        const form = this, btn = document.getElementById('comment-submit-btn'), feedback = document.getElementById('comment-feedback');
        btn.disabled = true; btn.textContent = 'Posting...';
        fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.text())
            .then(html => {
                feedback.innerHTML = html;   // server returns esc()'d text only
                btn.disabled = false; btn.textContent = 'Post Comment';
                if (html.includes('alert-success')) form.querySelector('textarea[name="body"]').value = '';
            })
            .catch(() => {
                feedback.textContent = 'Failed to post comment. Please try again.';
                btn.disabled = false; btn.textContent = 'Post Comment';
            });
    });
    </script>
    <?php endif; ?>

    <?php if (! empty($related)): ?>
        <div class="pt-4 border-top mt-5">
            <h2 class="h4 fw-bold mb-3">More in this section</h2>
            <div class="row row-cols-1 row-cols-sm-3 g-3">
                <?php foreach ($related as $r): ?>
                    <article class="col"><div class="card h-100"><div class="card-body">
                            <span class="small fw-bold text-uppercase text-primary"><?= esc($r['category_title'] ?? 'News') ?></span>
                            <h3 class="h6 fw-bold line-clamp-2 mt-2"><a href="<?= base_url('blog/' . esc($r['slug'], 'url')) ?>"><?= esc($r['title']) ?></a></h3>
                            <a href="<?= base_url('blog/' . esc($r['slug'], 'url')) ?>" class="small fw-semibold">Read &rarr;</a>
                        </div>
                    </div></article>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
