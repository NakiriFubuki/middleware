<?php
require_once __DIR__ . '/includes/bootstrap-minimal.php';

if (isLoggedIn()) {
    redirect(isAdmin() ? baseUrl('admin/dashboard.php') : baseUrl('rider/dashboard.php'));
}

$pageTitle = 'Login';
$bodyClass = 'login-page';
$extraCss = ['pwa.css'];
$loginBg = loginBackgroundAssetUrl();
require __DIR__ . '/includes/header.php';
?>

<div class="login-split">
    <aside class="login-showcase" style="background-image: url('<?= sanitize($loginBg) ?>')" aria-hidden="true">
        <div id="showcaseCanvas"></div>
    </aside>

    <section class="login-panel">
        <div class="login-panel-inner">
            <div class="login-header">
                <h2>Login</h2>
            </div>

            <?php if (isset($_GET['registered'])): ?>
                <div class="alert alert-success">Registration submitted! Please wait for admin approval before logging in.</div>
            <?php endif; ?>

            <?php if (isset($_GET['timeout'])): ?>
                <div class="alert alert-warning">Your session has expired. Please log in again.</div>
            <?php endif; ?>

            <?php if (!empty($_SESSION['flash_error'])): ?>
                <div class="alert alert-danger"><?= sanitize($_SESSION['flash_error']) ?></div>
                <?php unset($_SESSION['flash_error']); ?>
            <?php endif; ?>

            <form id="loginForm" class="login-form" novalidate>
                <?= csrfField() ?>
                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <input type="text" id="username" name="username" required autocomplete="username">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                </div>
                <div class="form-group form-check">
                    <label class="checkbox-label" for="remember">
                        <input type="checkbox" id="remember" name="remember" value="1">
                        <span>Remember me</span>
                    </label>
                </div>
                <button type="submit" class="btn btn-primary btn-block" id="loginBtn">
                    <span class="btn-text">Sign In</span>
                    <span class="btn-loader hidden"></span>
                </button>
            </form>

            <div class="login-footer">
                <p class="login-register-link"><a href="<?= baseUrl('register.php') ?>">Register as Rider</a></p>

                <div class="login-demo-accounts">
                    <p class="login-demo-title">Demo Accounts</p>
                    <button type="button" class="login-demo-item" data-user="admin" data-pass="admin123">
                        <span class="login-demo-role">Admin</span>
                        <span class="login-demo-creds"><code>admin</code>, <code>admin123</code></span>
                    </button>
                    <button type="button" class="login-demo-item" data-user="user" data-pass="user123">
                        <span class="login-demo-role">Rider</span>
                        <span class="login-demo-creds"><code>user</code>, <code>user123</code></span>
                    </button>
                </div>
            </div>
        </div>
    </section>
</div>

<?php require __DIR__ . '/includes/copyright-footer.php'; ?>
<?php require __DIR__ . '/includes/pwa-install-modal.php'; ?>

<script src="<?= assetUrl('js/ajax.js') ?>"></script>
<script src="<?= assetUrl('js/notifications.js') ?>"></script>
<script src="<?= assetUrl('js/login-showcase-effect.js') ?>"></script>
<script src="<?= assetUrl('js/pwa-install.js') ?>"></script>
<script>
document.querySelectorAll('.login-demo-item').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.getElementById('username').value = btn.dataset.user || '';
        document.getElementById('password').value = btn.dataset.pass || '';
        document.getElementById('username').focus();
    });
});

document.getElementById('loginForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('loginBtn');
    const formData = new FormData(this);
    btn.disabled = true;
    btn.querySelector('.btn-text').classList.add('hidden');
    btn.querySelector('.btn-loader').classList.remove('hidden');

    try {
        const response = await Ajax.post('<?= baseUrl('api/login.php') ?>', formData);
        if (response.success) {
            Toast.success(response.message);
            setTimeout(() => window.location.href = response.redirect, 500);
        } else {
            Toast.error(response.message);
        }
    } catch (err) {
        Toast.error('Connection error. Please try again.');
    } finally {
        btn.disabled = false;
        btn.querySelector('.btn-text').classList.remove('hidden');
        btn.querySelector('.btn-loader').classList.add('hidden');
    }
});
</script>
</body>
</html>
