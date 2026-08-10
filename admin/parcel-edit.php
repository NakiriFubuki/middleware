<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();

$id = (int) ($_GET['id'] ?? 0);
$parcel = getParcelById($id);
if (!$parcel) {
    redirect(baseUrl('admin/parcels.php'));
}

$pageTitle = 'Edit Parcel';
$currentPage = 'parcels';
$bodyClass = 'app-layout';
$extraJs = ['location-autofill.js'];
$riders = getActiveRiders();
$error = '';
$backHref = backUrl('admin/parcel-view.php?id=' . $id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $data = sanitizeInput($_POST);
    $result = updateParcel($id, $data, currentUserId());
    if ($result['success']) {
        $return = trim((string) ($_GET['return'] ?? ''));
        $redirect = 'admin/parcel-view.php?id=' . $id . '&updated=1';
        if ($return !== '') {
            $redirect .= '&return=' . rawurlencode($return);
        }
        redirect(baseUrl($redirect));
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

        <div class="toolbar">
            <a href="<?= sanitize($backHref) ?>" class="btn btn-outline">&larr; Back</a>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>Edit Parcel: <?= sanitize($parcel['tracking_number']) ?></h2>
            </div>
            <form method="POST" class="form-grid">
                <?= csrfField() ?>
                <div class="form-section">
                    <h3>Sender Information</h3>
                    <div class="form-group contact-autofill-wrap" data-contact-autofill="sender" data-address-target="#pickup_address">
                        <label for="sender_name">Sender Name *</label>
                        <input type="text" id="sender_name" name="sender_name" required data-contact-name
                               value="<?= sanitize($parcel['sender_name']) ?>">
                        <div class="location-suggestions hidden"></div>
                    </div>
                    <div class="form-group">
                        <label for="sender_phone">Sender Phone *</label>
                        <input type="tel" id="sender_phone" name="sender_phone" required data-contact-phone
                               value="<?= sanitize($parcel['sender_phone']) ?>">
                    </div>
                    <div class="form-group location-autofill-wrap" data-location-autofill="pickup">
                        <label for="pickup_address">Pickup Address *</label>
                        <div class="location-autofill-toolbar">
                            <button type="button" class="btn btn-sm btn-outline" data-use-gps>Use current location</button>
                        </div>
                        <textarea id="pickup_address" name="pickup_address" required rows="2"
                                  data-location-input><?= sanitize($parcel['pickup_address']) ?></textarea>
                        <div class="location-suggestions hidden"></div>
                    </div>
                </div>
                <div class="form-section">
                    <h3>Receiver Information</h3>
                    <div class="form-group contact-autofill-wrap" data-contact-autofill="receiver" data-address-target="#delivery_address">
                        <label for="receiver_name">Receiver Name *</label>
                        <input type="text" id="receiver_name" name="receiver_name" required data-contact-name
                               value="<?= sanitize($parcel['receiver_name']) ?>">
                        <div class="location-suggestions hidden"></div>
                    </div>
                    <div class="form-group">
                        <label for="receiver_phone">Receiver Phone *</label>
                        <input type="tel" id="receiver_phone" name="receiver_phone" required data-contact-phone
                               value="<?= sanitize($parcel['receiver_phone']) ?>">
                    </div>
                    <div class="form-group location-autofill-wrap" data-location-autofill="delivery">
                        <label for="delivery_address">Delivery Address *</label>
                        <div class="location-autofill-toolbar">
                            <button type="button" class="btn btn-sm btn-outline" data-use-gps>Use current location</button>
                        </div>
                        <textarea id="delivery_address" name="delivery_address" required rows="2"
                                  data-location-input><?= sanitize($parcel['delivery_address']) ?></textarea>
                        <div class="location-suggestions hidden"></div>
                    </div>
                </div>
                <div class="form-section">
                    <h3>Parcel Details</h3>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="parcel_description" rows="2"><?= sanitize($parcel['parcel_description'] ?? '') ?></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Weight (kg)</label>
                            <input type="number" name="parcel_weight" step="0.01" min="0" value="<?= $parcel['parcel_weight'] ?>">
                        </div>
                        <div class="form-group">
                            <label>Delivery Fee (₱)</label>
                            <input type="number" name="delivery_fee" step="0.01" min="0" value="<?= $parcel['delivery_fee'] ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Assign Rider</label>
                        <select name="assigned_rider_id">
                            <option value="">Unassigned</option>
                            <?php foreach ($riders as $r): ?>
                                <option value="<?= $r['id'] ?>" <?= $parcel['assigned_rider_id'] == $r['id'] ? 'selected' : '' ?>>
                                    <?= sanitize($r['full_name']) ?> (<?= sanitize($r['rider_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-actions">
                    <a href="<?= sanitize($backHref) ?>" class="btn btn-outline">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
</div>

<?php require __DIR__ . '/../includes/app-shell-end.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
