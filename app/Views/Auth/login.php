<?= $this->extend('App\Views\Auth\layout') ?>
<?= $this->section('title') ?>Sign in<?= $this->endSection() ?>
<?= $this->section('main') ?>
<?php $useUsername = old('username') !== null && old('username') !== ''; ?>
<main class="container flex-grow-1 d-flex align-items-center justify-content-center py-5">
    <section class="auth-surface w-100 rounded border p-4 p-sm-5 shadow-sm" style="max-width: 28rem">
        <p class="auth-accent small fw-bold text-uppercase">Newsroom access</p>
        <h1 class="h2 fw-bold mt-2">Sign in</h1>
        <p class="auth-muted">Access your account or staff workspace.</p>

        <?php if (session('error') !== null): ?><div role="alert" class="alert alert-danger mt-4"><?= esc(session('error')) ?></div><?php endif; ?>
        <?php if (session('errors') !== null): ?>
            <div role="alert" class="alert alert-danger mt-4 mb-0">
                <?php foreach ((array) session('errors') as $error): ?><p class="mb-1"><?= esc($error) ?></p><?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (session('message') !== null): ?><div role="status" class="alert alert-success mt-4"><?= esc(session('message')) ?></div><?php endif; ?>

        <form action="<?= url_to('login') ?>" method="post" class="vstack gap-3 mt-4" id="login-form">
            <?= csrf_field() ?>
            <fieldset>
                <legend class="form-label fw-semibold">Sign in with</legend>
                <div class="btn-group w-100" role="group" aria-label="Login identifier">
                    <button type="button" data-login-mode="email" aria-pressed="<?= $useUsername ? 'false' : 'true' ?>" class="btn <?= $useUsername ? 'btn-outline-secondary' : 'btn-success' ?>">Email</button>
                    <button type="button" data-login-mode="username" aria-pressed="<?= $useUsername ? 'true' : 'false' ?>" class="btn <?= $useUsername ? 'btn-success' : 'btn-outline-secondary' ?>">Username</button>
                </div>
            </fieldset>
            <div><label for="login-identifier" class="form-label fw-semibold">Email address or username</label>
                <input id="login-identifier" name="<?= $useUsername ? 'username' : 'email' ?>" type="<?= $useUsername ? 'text' : 'email' ?>" value="<?= esc(old($useUsername ? 'username' : 'email', ''), 'attr') ?>" autocomplete="<?= $useUsername ? 'username' : 'email' ?>" required class="form-control"></div>
            <div><label for="login-password" class="form-label fw-semibold">Password</label>
                <input id="login-password" name="password" type="password" autocomplete="current-password" required class="form-control"></div>

            <?php if (setting('Auth.sessionConfig')['allowRemembering']): ?>
                <label class="form-check"><input class="form-check-input" type="checkbox" name="remember" value="1" <?= old('remember') ? 'checked' : '' ?>> <span class="form-check-label">Remember me</span></label>
            <?php endif; ?>
            <button class="btn btn-success w-100 py-2 fw-semibold">Sign in</button>
        </form>

        <?php if (setting('Auth.allowRegistration')): ?>
            <p class="auth-muted mt-4 text-center small">New here? <a class="auth-accent fw-bold" href="<?= url_to('register') ?>">Create an account</a></p>
        <?php endif; ?>
        <a href="<?= base_url() ?>" class="auth-muted d-block text-center small">Back to the front page</a>
    </section>
</main>
<script>
const identifier = document.getElementById('login-identifier');
document.querySelectorAll('[data-login-mode]').forEach((button) => {
    button.addEventListener('click', () => {
        const useUsername = button.dataset.loginMode === 'username';
        document.querySelectorAll('[data-login-mode]').forEach((option) => {
            const selected = option === button;
            option.setAttribute('aria-pressed', String(selected));
            option.classList.toggle('btn-success', selected);
            option.classList.toggle('btn-outline-secondary', !selected);
        });
        identifier.name = useUsername ? 'username' : 'email';
        identifier.type = useUsername ? 'text' : 'email';
        identifier.autocomplete = useUsername ? 'username' : 'email';
    });
});
</script>
<?= $this->endSection() ?>