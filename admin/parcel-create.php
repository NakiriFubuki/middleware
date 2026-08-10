<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();

$pageTitle = 'Create Parcel';
$currentPage = 'parcel-create';
$bodyClass = 'app-layout';
$extraJs = ['location-autofill.js'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $data = sanitizeInput($_POST);
    $result = createParcel($data, currentUserId());
    if ($result['success']) {
        redirect(baseUrl('admin/parcel-view.php?id=' . $result['parcel_id'] . '&created=1'));
    }
    $error = $result['message'];
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar-admin.php';
?>

<?php require __DIR__ . '/../includes/topbar.php'; ?>

<div class="content-area content-wrap">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= sanitize($error) ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header"><h2>New Parcel</h2></div>
            <form method="POST" class="form-grid" id="parcelForm" style="max-width: 520px;">
                <?= csrfField() ?>
                <div class="form-group contact-autofill-wrap" data-contact-autofill="receiver" data-address-target="#delivery_address">
                    <label for="receiver_name">Receiver Name *</label>
                    <input type="text" id="receiver_name" name="receiver_name" required
                           data-contact-name
                           value="<?= sanitize($_POST['receiver_name'] ?? '') ?>">
                    <div class="location-suggestions hidden"></div>
                </div>
                <div class="form-group location-autofill-wrap" data-location-autofill="delivery">
                    <label for="delivery_address">Address *</label>
                    <div class="location-autofill-toolbar">
                        <button type="button" class="btn btn-sm btn-outline" data-use-gps>Use current location</button>
                    </div>
                    <textarea id="delivery_address" name="delivery_address" required rows="3"
                              data-location-input><?= sanitize($_POST['delivery_address'] ?? '') ?></textarea>
                    <div class="location-suggestions hidden"></div>
                </div>
                <div class="form-group">
                    <label for="parcel_weight">Weight (kg) *</label>
                    <input type="number" id="parcel_weight" name="parcel_weight" step="0.01" min="0" required
                           value="<?= sanitize($_POST['parcel_weight'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="parcel_description">Remarks</label>
                    <textarea id="parcel_description" name="parcel_description" rows="3"><?= sanitize($_POST['parcel_description'] ?? '') ?></textarea>
                </div>
                <div class="form-actions">
                    <a href="<?= baseUrl('admin/parcels.php') ?>" class="btn btn-outline">Cancel</a>
                    <button type="submit" class="btn btn-primary">Create Parcel</button>
                </div>
            </form>
        </div>
</div>

<?php require __DIR__ . '/../includes/app-shell-end.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
