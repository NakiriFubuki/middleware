<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRider();

$rider = getRiderProfile();
$history = getRiderDeliveryHistory((int) $rider['id']);

$pageTitle = 'Delivery History';
$currentPage = 'history';
$bodyClass = 'app-layout';

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar-rider.php';
?>

<?php require __DIR__ . '/../includes/topbar.php'; ?>

<div class="content-area content-wrap">
        <div class="card">
            <div class="card-header"><h2>Delivery History</h2></div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Tracking #</th>
                            <th>Receiver</th>
                            <th>Address</th>
                            <th>Status</th>
                            <th>Fee</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($history)): ?>
                            <tr><td colspan="6" class="text-center">—</td></tr>
                        <?php else: ?>
                            <?php foreach ($history as $p): ?>
                            <tr>
                                <td><strong><?= sanitize($p['tracking_number']) ?></strong></td>
                                <td><?= sanitize($p['receiver_name']) ?></td>
                                <td><?= sanitize($p['delivery_address']) ?></td>
                                <td><span class="badge <?= statusBadgeClass($p['status']) ?>"><?= formatStatus($p['status']) ?></span></td>
                                <td>₱<?= number_format($p['delivery_fee'], 2) ?></td>
                                <td><?= formatDate($p['updated_at']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
</div>

<?php require __DIR__ . '/../includes/app-shell-end.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
