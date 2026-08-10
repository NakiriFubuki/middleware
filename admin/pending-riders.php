<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();

$pageTitle = 'Pending Riders';
$currentPage = 'pending-riders';
$bodyClass = 'app-layout';

$pendingRiders = getPendingRiders();

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar-admin.php';
?>

<?php require __DIR__ . '/../includes/topbar.php'; ?>

<div class="content-area content-wrap">
        <div class="card">
            <div class="card-header">
                <h2>Pending Rider Approvals</h2>
                <span class="badge badge-warning"><?= count($pendingRiders) ?> pending</span>
            </div>
            <p class="card-note">New rider registrations waiting for admin approval. This is not the same as pending parcels — assign parcels from <a href="<?= baseUrl('admin/parcels.php?status=pending') ?>">Parcels</a>.</p>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Rider Code</th>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Vehicle</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="pendingRidersBody">
                        <?php if (empty($pendingRiders)): ?>
                            <tr><td colspan="8" class="text-center">No pending registrations.</td></tr>
                        <?php else: ?>
                            <?php foreach ($pendingRiders as $r): ?>
                            <tr data-user-id="<?= $r['user_id'] ?>">
                                <td><strong><?= sanitize($r['rider_code']) ?></strong></td>
                                <td><?= sanitize($r['full_name']) ?></td>
                                <td><?= sanitize($r['username']) ?></td>
                                <td><?= sanitize($r['email']) ?></td>
                                <td><?= sanitize($r['phone'] ?? '—') ?></td>
                                <td><?= sanitize($r['vehicle_type']) ?></td>
                                <td><?= formatDate($r['registered_at']) ?></td>
                                <td class="actions-cell">
                                    <button class="btn btn-sm btn-primary approve-btn" data-id="<?= $r['user_id'] ?>" data-name="<?= sanitize($r['full_name']) ?>">Approve</button>
                                    <button class="btn btn-sm btn-danger reject-btn" data-id="<?= $r['user_id'] ?>" data-name="<?= sanitize($r['full_name']) ?>">Reject</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
</div>

<script>
document.querySelectorAll('.approve-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        if (!confirm('Approve rider ' + btn.dataset.name + '?')) return;
        const res = await Ajax.post('<?= baseUrl('api/approve_rider.php') ?>', { user_id: btn.dataset.id });
        if (res.success) {
            Toast.success(res.message);
            btn.closest('tr').remove();
        } else Toast.error(res.message);
    });
});

document.querySelectorAll('.reject-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        if (!confirm('Reject registration for ' + btn.dataset.name + '? This cannot be undone.')) return;
        const res = await Ajax.post('<?= baseUrl('api/reject_rider.php') ?>', { user_id: btn.dataset.id });
        if (res.success) {
            Toast.success(res.message);
            btn.closest('tr').remove();
        } else Toast.error(res.message);
    });
});
</script>

<?php require __DIR__ . '/../includes/app-shell-end.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
