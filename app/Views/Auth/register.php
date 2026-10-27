<?= $this->extend('App\Views\Auth\layout') ?>
<?= $this->section('title') ?>Create account<?= $this->endSection() ?>
<?= $this->section('main') ?>
<main class="container flex-grow-1 d-flex align-items-center justify-content-center py-5">
    <section class="auth-surface w-100 rounded border p-4 p-sm-5 shadow-sm" style="max-width: 28rem">
        <p class="auth-accent small fw-bold text-uppercase">Reader account</p>
        <h1 class="h2 fw-bold mt-2">Create an account</h1>
        <p class="auth-muted">New accounts are readers. Staff access is granted separately.</p>

        <?php if (session('error') !== null): ?><div role="alert" class="alert alert-danger mt-4"><?= esc(session('error')) ?></div><?php endif; ?>
        <?php if (session('errors') !== null): ?>
            <div role="alert" class="alert alert-danger mt-4 mb-0">
                <?php foreach ((array) session('errors') as $error): ?><p class="mb-1"><?= esc($error) ?></p><?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form action="<?= url_to('register') ?>" method="post" class="vstack gap-3 mt-4">
            <?= csrf_field() ?>
            <div><label class="form-label fw-semibold" for="register-username">Username</label>
                <input id="register-username" name="username" value="<?= esc(old('username', ''), 'attr') ?>" autocomplete="username" required minlength="3" maxlength="30" class="form-control"></div>
            <div><label class="form-label fw-semibold" for="register-email">Email address</label>
                <input id="register-email" name="email" type="email" value="<?= esc(old('email', ''), 'attr') ?>" autocomplete="email" required maxlength="254" class="form-control"></div>
            <div><label class="form-label fw-semibold" for="register-password">Password</label>
                <input id="register-password" name="password" type="password" autocomplete="new-password" required minlength="8" class="form-control"></div>
            <div><label class="form-label fw-semibold" for="register-password-confirm">Confirm password</label>
                <input id="register-password-confirm" name="password_confirm" type="password" autocomplete="new-password" required minlength="8" class="form-control"></div>
            <button class="btn btn-success w-100 py-2 fw-semibold">Create account</button>
        </form>
        <p class="auth-muted mt-4 text-center small">Already have an account? <a class="auth-accent fw-bold" href="<?= url_to('login') ?>">Sign in</a></p>
        <a href="<?= base_url() ?>" class="auth-muted d-block text-center small">Back to the front page</a>
    </section>
</main>
<?= $this->endSection() ?>