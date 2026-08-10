<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();

$pageTitle = 'Riders';
$currentPage = 'riders';
$bodyClass = 'app-layout';

$search = $_GET['search'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$result = getAllRiders($search ?: null, $page);
$riders = $result['riders'];
$pagination = $result['pagination'];

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar-admin.php';
?>

<?php require __DIR__ . '/../includes/topbar.php'; ?>

<div class="content-area content-wrap">
        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success">Rider deleted successfully.</div>
        <?php endif; ?>

        <div class="toolbar">
            <form class="search-form" method="GET">
                <input type="text" name="search" value="<?= sanitize($search) ?>" class="search-input">
                <button type="submit" class="btn btn-primary">Search</button>
            </form>
            <a href="<?= baseUrl('admin/pending-riders.php') ?>" class="btn btn-outline">Pending Approvals</a>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>All Riders</h2>
                <span class="badge badge-info"><?= $pagination['total'] ?> total</span>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Phone</th>
                            <th>Vehicle</th>
                            <th>Account</th>
                            <th>Online</th>
                            <th>Deliveries</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($riders)): ?>
                            <tr><td colspan="10" class="text-center">—</td></tr>
                        <?php else: ?>
                            <?php foreach ($riders as $r): ?>
                            <tr data-rider-id="<?= $r['id'] ?>">
                                <td><strong><?= sanitize($r['rider_code']) ?></strong></td>
                                <td>
                                    <div class="user-cell">
                                        <?php if ($r['profile_photo']): ?>
                                            <img src="<?= baseUrl($r['profile_photo']) ?>" class="avatar-sm" alt="">
                                        <?php endif; ?>
                                        <?= sanitize($r['full_name']) ?>
                                    </div>
                                </td>
                                <td><?= sanitize($r['username']) ?></td>
                                <td><?= sanitize($r['phone'] ?? '—') ?></td>
                                <td><?= sanitize($r['vehicle_type']) ?></td>
                                <td>
                                    <?php if ($r['is_active']): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($r['is_online']): ?>
                                        <span class="badge badge-success">Online</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Offline</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $r['total_deliveries'] ?></td>
                                <td><?= formatDate($r['last_login']) ?></td>
                            <td class="actions-cell">
                                <a href="<?= sanitize(linkWithReturn('admin/rider-view.php', ['id' => (int) $r['id']])) ?>" class="btn btn-sm btn-outline">View</a>
                                <?php $activeDeliveries = (int) ($r['active_deliveries'] ?? 0); ?>
                                <button type="button"
                                        class="btn btn-sm btn-danger delete-rider-btn"
                                        data-id="<?= $r['id'] ?>"
                                        data-name="<?= sanitize($r['full_name']) ?>"
                                        data-code="<?= sanitize($r['rider_code']) ?>"
                                        <?= $activeDeliveries > 0 ? 'disabled title="Active delivery in progress"' : '' ?>>Delete</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?= buildPaginationHtml($pagination['current_page'], $pagination['total_pages'], baseUrl('admin/riders.php') . '?search=' . urlencode($search)) ?>
        </div>
</div>

<div class="modal" id="deleteRiderModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Delete Rider</h3>
            <button class="modal-close" data-close-modal>&times;</button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to delete rider <strong id="deleteRiderName"></strong> (<span id="deleteRiderCode"></span>)?</p>
            <p class="text-muted">This will permanently remove the account and unassign their parcels. This action cannot be undone.</p>
            <div class="alert alert-danger hidden" id="deleteRiderError"></div>
            <input type="hidden" id="deleteRiderId">
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" data-close-modal>Cancel</button>
            <button class="btn btn-danger" id="confirmDeleteRiderBtn">Delete Rider</button>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.delete-rider-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        if (btn.disabled) return;
        document.getElementById('deleteRiderId').value = btn.dataset.id;
        document.getElementById('deleteRiderName').textContent = btn.dataset.name;
        document.getElementById('deleteRiderCode').textContent = btn.dataset.code;
        document.getElementById('deleteRiderError')?.classList.add('hidden');
        document.getElementById('deleteRiderModal').classList.add('active');
    });
});

document.getElementById('confirmDeleteRiderBtn').addEventListener('click', async () => {
    const riderId = document.getElementById('deleteRiderId').value;
    const btn = document.getElementById('confirmDeleteRiderBtn');
    const errorBox = document.getElementById('deleteRiderError');
    btn.disabled = true;
    errorBox?.classList.add('hidden');

    try {
        const res = await Ajax.post('<?= baseUrl('api/delete_rider.php') ?>', { rider_id: riderId });
        if (res.success) {
            Toast.success(res.message);
            document.getElementById('deleteRiderModal').classList.remove('active');
            const row = document.querySelector(`tr[data-rider-id="${riderId}"]`);
            if (row) row.remove();
        } else {
            const message = res.message || 'Unable to delete rider.';
            if (errorBox) {
                errorBox.textContent = message;
                errorBox.classList.remove('hidden');
            }
            Toast.error(message);
        }
    } catch (e) {
        const message = 'Failed to delete rider.';
        if (errorBox) {
            errorBox.textContent = message;
            errorBox.classList.remove('hidden');
        }
        Toast.error(message);
    } finally {
        btn.disabled = false;
    }
});
</script>

<?php require __DIR__ . '/../includes/app-shell-end.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
