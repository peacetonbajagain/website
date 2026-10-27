<?= $this->extend('Travo\FrontEnd\Views\Layout\App') ?>
<?= $this->section('content') ?>
<div class="container py-5" style="max-width: 52rem">
    <h1 class="display-5 fw-bold mb-4"><?= esc($page['title']) ?></h1>
    <!-- content was sanitised by ContentCleaner on save -->
    <div class="prose"><?= $page['content'] ?></div>
</div>
<?= $this->endSection() ?>
