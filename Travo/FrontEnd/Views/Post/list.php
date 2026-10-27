<?= $this->extend('Travo\FrontEnd\Views\Layout\App') ?>
<?= $this->section('content') ?>
<div class="container-xl py-5">
    <h1 class="display-6 fw-bold mb-4"><?= esc($heading) ?></h1>
    <?php if (empty($posts)): ?>
        <p class="text-secondary">No stories here yet.</p>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4">
            <?php foreach ($posts as $p): ?><?= view('Travo\FrontEnd\Views\Partials\card', ['p' => $p]) ?><?php endforeach; ?>
        </div>
        <div class="pager"><?= $pager->links() ?></div>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
