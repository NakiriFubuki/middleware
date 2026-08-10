<?php
require_once __DIR__ . '/includes/bootstrap-minimal.php';

if (isLoggedIn()) {
    redirect(isAdmin() ? baseUrl('admin/dashboard.php') : baseUrl('rider/dashboard.php'));
}

$pageTitle = 'Rider Registration';
$bodyClass = 'login-page register-page';
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
                <h2>Rider Registration</h2>
            </div>

            <div id="registerAlert" class="alert alert-danger hidden" role="alert"></div>

            <form id="registerForm" class="login-form" novalidate>
            <?= csrfField() ?>
            <div class="form-row">
                <div class="form-group">
                    <label for="full_name">Full Name *</label>
                    <input type="text" id="full_name" name="full_name" required>
                </div>
                <div class="form-group">
                    <label for="username">Username *</label>
                    <input type="text" id="username" name="username" required pattern="[a-zA-Z0-9_]{3,50}">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input type="tel" id="phone" name="phone">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="vehicle_type">Vehicle Type</label>
                    <select id="vehicle_type" name="vehicle_type">
                        <option value="Motorcycle">Motorcycle</option>
                        <option value="Bicycle">Bicycle</option>
                        <option value="Car">Car</option>
                        <option value="Van">Van</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="plate_number">License Plate</label>
                    <input type="text" id="plate_number" name="plate_number">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password *</label>
                    <input type="password" id="password" name="password" required minlength="6">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-block" id="registerBtn">
                <span class="btn-text">Register</span>
                <span class="btn-loader hidden"></span>
            </button>
        </form>

            <div class="login-footer">
                <p><a href="<?= baseUrl('login.php') ?>">Sign in</a></p>
            </div>
        </div>
    </section>
</div>

<?php require __DIR__ . '/includes/copyright-footer.php'; ?>

<script src="<?= assetUrl('js/ajax.js') ?>"></script>
<script src="<?= assetUrl('js/notifications.js') ?>"></script>
<script src="<?= assetUrl('js/login-showcase-effect.js') ?>"></script>
<script>
document.getElementById('registerForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('registerBtn');
    const alertBox = document.getElementById('registerAlert');
    const pw = document.getElementById('password').value;
    const cpw = document.getElementById('confirm_password').value;

    alertBox.classList.add('hidden');
    alertBox.textContent = '';

    if (pw !== cpw) {
        alertBox.textContent = 'Passwords do not match.';
        alertBox.classList.remove('hidden');
        Toast.error('Passwords do not match.');
        return;
    }

    if (pw.length < 6) {
        alertBox.textContent = 'Password must be at least 6 characters.';
        alertBox.classList.remove('hidden');
        return;
    }

    btn.disabled = true;
    btn.querySelector('.btn-text').classList.add('hidden');
    btn.querySelector('.btn-loader').classList.remove('hidden');

    try {
        const res = await Ajax.post('<?= baseUrl('api/register.php') ?>', new FormData(this));
        if (res.success) {
            Toast.success(res.message);
            setTimeout(() => window.location.href = '<?= baseUrl('login.php') ?>?registered=1', 1500);
        } else {
            alertBox.textContent = res.message || 'Registration failed. Please check your details.';
            alertBox.classList.remove('hidden');
            Toast.error(res.message);
        }
    } catch (err) {
        alertBox.textContent = 'Connection error. Please try again.';
        alertBox.classList.remove('hidden');
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
