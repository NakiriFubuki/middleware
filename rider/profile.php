<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRider();

$rider = getRiderProfile();
$user = currentUser();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    requireCsrf();
    $result = updateRiderProfile(currentUserId(), sanitizeInput($_POST));
    $message = $result['success'] ? $result['message'] : '';
    $error = !$result['success'] ? $result['message'] : '';
    $rider = getRiderProfile();
    $user = currentUser();
}

$pageTitle = 'Profile';
$currentPage = 'profile';
$bodyClass = 'app-layout';

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar-rider.php';
?>

<?php require __DIR__ . '/../includes/topbar.php'; ?>

<div class="content-area content-wrap">
        <?php if ($message): ?><div class="alert alert-success"><?= sanitize($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif; ?>

        <div class="detail-grid">
            <div class="card">
                <div class="card-header"><h2>Profile Photo</h2></div>
                <div class="profile-photo-section">
                    <div class="profile-photo-large">
                        <?php if ($user['profile_photo']): ?>
                            <img src="<?= baseUrl($user['profile_photo']) ?>" alt="Profile" id="profilePhotoImg">
                        <?php else: ?>
                            <span class="photo-placeholder" id="profilePhotoImg"><?= strtoupper(substr($user['full_name'], 0, 1)) ?></span>
                        <?php endif; ?>
                    </div>
                    <form id="photoUploadForm" enctype="multipart/form-data">
                        <label class="btn btn-outline">
                            Change Photo
                            <input type="file" name="profile_photo" id="profilePhotoInput" accept="image/jpeg,image/png,image/webp" hidden>
                        </label>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2>Profile Information</h2></div>
                <form method="POST" class="form-grid">
                    <?= csrfField() ?>
                    <input type="hidden" name="update_profile" value="1">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" required value="<?= sanitize($user['full_name']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" required value="<?= sanitize($user['email']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone" value="<?= sanitize($user['phone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Vehicle Type</label>
                        <input type="text" name="vehicle_type" value="<?= sanitize($rider['vehicle_type']) ?>">
                    </div>
                    <div class="form-group">
                        <label>License Plate</label>
                        <input type="text" name="plate_number" value="<?= sanitize($rider['license_number'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Rider Code</label>
                        <input type="text" value="<?= sanitize($rider['rider_code']) ?>" disabled>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Profile</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>Change Password</h2></div>
            <form id="changePasswordForm" class="form-grid" style="max-width:400px">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" id="currentPassword" required>
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" id="newPassword" required minlength="8">
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" id="confirmPassword" required>
                </div>
                <button type="submit" class="btn btn-primary">Change Password</button>
            </form>
        </div>
</div>

<script>
document.getElementById('profilePhotoInput').addEventListener('change', async function() {
    if (!this.files[0]) return;
    const formData = new FormData();
    formData.append('profile_photo', this.files[0]);
    const res = await Ajax.post('<?= baseUrl('api/profile.php') ?>', formData);
    if (res.success) {
        Toast.success(res.message);
        const img = document.getElementById('profilePhotoImg');
        if (img.tagName === 'IMG') img.src = '<?= baseUrl() ?>/' + res.file_path;
        else { const newImg = document.createElement('img'); newImg.id = 'profilePhotoImg'; newImg.src = '<?= baseUrl() ?>/' + res.file_path; img.replaceWith(newImg); }
    } else Toast.error(res.message);
});

document.getElementById('changePasswordForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const newPass = document.getElementById('newPassword').value;
    const confirm = document.getElementById('confirmPassword').value;
    if (newPass !== confirm) { Toast.error('Passwords do not match.'); return; }
    const res = await Ajax.post('<?= baseUrl('api/profile.php') ?>', {
        change_password: 1,
        current_password: document.getElementById('currentPassword').value,
        new_password: newPass
    });
    if (res.success) { Toast.success(res.message); this.reset(); }
    else Toast.error(res.message);
});
</script>

<?php require __DIR__ . '/../includes/app-shell-end.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
