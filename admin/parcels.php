<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();

$pageTitle = 'Parcels';
$currentPage = 'parcels';
$bodyClass = 'app-layout';

$filters = [
    'status' => $_GET['status'] ?? '',
    'search' => $_GET['search'] ?? '',
];
$page = max(1, (int) ($_GET['page'] ?? 1));
$result = getParcels(array_filter($filters), $page);
$parcels = $result['parcels'];
$pagination = $result['pagination'];
$assignRiders = getActiveRiders();
$extraJs = ['assign-rider.js'];

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar-admin.php';
?>

<?php require __DIR__ . '/../includes/topbar.php'; ?>

<div class="content-area content-wrap">
        <div class="toolbar">
            <form class="search-form" id="parcelSearchForm" method="GET">
                <input type="text" name="search" value="<?= sanitize($filters['search']) ?>" class="search-input">
                <select name="status" class="filter-select">
                    <option value="">All Status</option>
                    <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="out_for_delivery" <?= $filters['status'] === 'out_for_delivery' ? 'selected' : '' ?>>Out For Delivery</option>
                    <option value="delivered" <?= $filters['status'] === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                    <option value="failed" <?= $filters['status'] === 'failed' ? 'selected' : '' ?>>Failed</option>
                </select>
                <button type="submit" class="btn btn-primary">Search</button>
            </form>
            <a href="<?= baseUrl('admin/parcel-create.php') ?>" class="btn btn-primary">+ New Parcel</a>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="data-table" id="parcelsTable">
                    <thead>
                        <tr>
                            <th>Tracking #</th>
                            <th>Sender</th>
                            <th>Receiver</th>
                            <th>Rider</th>
                            <th>Fee</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($parcels)): ?>
                            <tr><td colspan="8" class="text-center">—</td></tr>
                        <?php else: ?>
                            <?php foreach ($parcels as $p): ?>
                            <tr data-parcel-id="<?= $p['id'] ?>">
                                <td><strong><?= sanitize($p['tracking_number']) ?></strong></td>
                                <td><?= sanitize($p['sender_name']) ?></td>
                                <td><?= sanitize($p['receiver_name']) ?></td>
                                <td>
                                    <?php if ($p['assigned_rider_id']): ?>
                                        <?= sanitize($p['rider_name'] ?? $p['rider_code']) ?>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-primary assign-btn" data-id="<?= (int) $p['id'] ?>">Assign</button>
                                    <?php endif; ?>
                                </td>
                                <td>₱<?= number_format($p['delivery_fee'], 2) ?></td>
                                <td><span class="badge <?= statusBadgeClass($p['status']) ?>"><?= formatStatus($p['status']) ?></span></td>
                                <td><?= formatDate($p['created_at']) ?></td>
                                <td class="actions-cell">
                                    <a href="<?= baseUrl('admin/parcel-view.php?id=' . $p['id']) ?>" class="btn btn-sm btn-outline">View</a>
                                    <a href="<?= baseUrl('admin/parcel-edit.php?id=' . $p['id']) ?>" class="btn btn-sm btn-outline">Edit</a>
                                    <button class="btn btn-sm btn-danger delete-parcel-btn" data-id="<?= $p['id'] ?>" data-tracking="<?= sanitize($p['tracking_number']) ?>">Delete</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?= buildPaginationHtml($pagination['current_page'], $pagination['total_pages'], baseUrl('admin/parcels.php') . '?' . http_build_query(array_filter($filters))) ?>
        </div>
</div>

<?php require __DIR__ . '/../includes/assign-rider-modal.php'; ?>

<script>
document.querySelectorAll('.delete-parcel-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        if (!confirm('Delete parcel ' + btn.dataset.tracking + '?')) return;
        const res = await Ajax.post('<?= baseUrl('api/delete_parcel.php') ?>', { id: btn.dataset.id });
        if (res.success) { Toast.success(res.message); btn.closest('tr').remove(); }
        else Toast.error(res.message);
    });
});
</script>

<?php require __DIR__ . '/../includes/app-shell-end.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
