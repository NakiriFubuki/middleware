<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();

$pageTitle = 'Activity Logs';
$currentPage = 'activity';
$bodyClass = 'app-layout';

$action = $_GET['action'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$result = getActivityLogs($page, 25, $action ?: null);
$logs = $result['logs'];
$pagination = $result['pagination'];

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar-admin.php';
?>

<?php require __DIR__ . '/../includes/topbar.php'; ?>

<div class="content-area content-wrap">
        <div class="toolbar">
            <form method="GET" class="search-form">
                <select name="action" class="filter-select">
                    <option value="">All Actions</option>
                    <option value="login" <?= $action === 'login' ? 'selected' : '' ?>>Login</option>
                    <option value="page_access" <?= $action === 'page_access' ? 'selected' : '' ?>>Page Access</option>
                    <option value="logout" <?= $action === 'logout' ? 'selected' : '' ?>>Logout</option>
                    <option value="parcel_create" <?= $action === 'parcel_create' ? 'selected' : '' ?>>Parcel Create</option>
                    <option value="parcel_assign" <?= $action === 'parcel_assign' ? 'selected' : '' ?>>Parcel Assign</option>
                    <option value="parcel_status_update" <?= $action === 'parcel_status_update' ? 'selected' : '' ?>>Status Update</option>
                    <option value="location_update" <?= $action === 'location_update' ? 'selected' : '' ?>>Location Update</option>
                    <option value="profile_update" <?= $action === 'profile_update' ? 'selected' : '' ?>>Profile Update</option>
                </select>
                <button type="submit" class="btn btn-primary">Filter</button>
            </form>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Action</th>
                            <th>Description</th>
                            <th>IP Address</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= sanitize($log['full_name'] ?? 'System') ?></td>
                            <td><?= sanitize($log['role'] ?? '—') ?></td>
                            <td><span class="badge badge-info"><?= sanitize($log['action']) ?></span></td>
                            <td><?= sanitize($log['description'] ?? '') ?></td>
                            <td><?= sanitize(formatIpAddress($log['ip_address'] ?? null)) ?></td>
                            <td><?= formatDate($log['created_at']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?= buildPaginationHtml($pagination['current_page'], $pagination['total_pages'], baseUrl('admin/activity-logs.php') . '?action=' . urlencode($action)) ?>
        </div>
</div>

<?php require __DIR__ . '/../includes/app-shell-end.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
