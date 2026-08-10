<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();

$pageTitle = 'Reports';
$currentPage = 'reports';
$bodyClass = 'app-layout';

$reportType = $_GET['type'] ?? 'orders';
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$export = $_GET['export'] ?? '';

$data = [];
$headers = [];
$title = '';
$orderRows = [];

switch ($reportType) {
    case 'orders':
        $title = 'Delivery Orders Report';
        $headers = ['Tracking', 'Receiver', 'Phone', 'Delivery Address', 'Remarks', 'Rider', 'Route Distance', 'GPS Points', 'Status', 'Delivered At'];
        $orderRows = getOrderDeliveryReport(
            $dateFrom,
            $dateTo,
            $search !== '' ? $search : null,
            $statusFilter !== '' ? $statusFilter : null
        );
        foreach ($orderRows as $r) {
            $data[] = [
                $r['tracking_number'],
                $r['receiver_name'],
                $r['receiver_phone'],
                $r['delivery_address'],
                $r['parcel_description'] ?: '—',
                $r['rider_name'] ? $r['rider_name'] . ' (' . $r['rider_code'] . ')' : '—',
                formatRouteDistance((float) $r['route_distance_km'], (int) $r['route_points']),
                (int) $r['route_points'],
                formatStatus($r['status']),
                $r['delivered_at'] ? formatDate($r['delivered_at']) : '—',
            ];
        }
        break;
    case 'rider_performance':
        $title = 'Rider Performance Report';
        $headers = ['Rider Code', 'Name', 'Total', 'Successful', 'Failed', 'Success Rate %'];
        $rows = getRiderPerformanceReport($dateFrom, $dateTo);
        foreach ($rows as $r) {
            $data[] = [$r['rider_code'], $r['full_name'], $r['total_deliveries'], $r['successful'], $r['failed'], $r['success_rate']];
        }
        break;
    case 'parcel_delivery':
        $title = 'Parcel Delivery Report';
        $headers = ['Tracking', 'Sender', 'Receiver', 'Address', 'Status', 'Fee', 'Rider', 'Created', 'Delivered'];
        $rows = getParcelDeliveryReport($dateFrom, $dateTo);
        foreach ($rows as $r) {
            $data[] = [$r['tracking_number'], $r['sender_name'], $r['receiver_name'], $r['delivery_address'], formatStatus($r['status']), $r['delivery_fee'], $r['rider_name'] ?? '—', $r['created_at'], $r['delivered_at'] ?? '—'];
        }
        break;
    case 'daily':
        $title = 'Daily Deliveries - ' . ($dateFrom);
        $headers = ['Tracking', 'Receiver', 'Address', 'Status', 'Rider', 'Delivered At'];
        $rows = getDailyDeliveriesReport($dateFrom);
        foreach ($rows as $r) {
            $data[] = [$r['tracking_number'], $r['receiver_name'], $r['delivery_address'], formatStatus($r['status']), $r['rider_name'] ?? '—', $r['delivered_at'] ?? '—'];
        }
        break;
    case 'monthly':
        $year = (int) ($_GET['year'] ?? date('Y'));
        $month = (int) ($_GET['month'] ?? date('m'));
        $title = 'Monthly Deliveries - ' . date('F Y', mktime(0, 0, 0, $month, 1, $year));
        $headers = ['Date', 'Total', 'Delivered', 'Failed', 'Total Fees'];
        $rows = getMonthlyDeliveriesReport($year, $month);
        foreach ($rows as $r) {
            $data[] = [$r['delivery_date'], $r['total'], $r['delivered'], $r['failed'], $r['total_fees']];
        }
        break;
    case 'failed':
        $title = 'Failed Deliveries Report';
        $headers = ['Tracking', 'Receiver', 'Phone', 'Address', 'Rider', 'Reason', 'Date'];
        $rows = getFailedDeliveriesReport($dateFrom, $dateTo);
        foreach ($rows as $r) {
            $data[] = [$r['tracking_number'], $r['receiver_name'], $r['receiver_phone'], $r['delivery_address'], $r['rider_name'] ?? '—', $r['failure_reason'] ?? '—', $r['updated_at']];
        }
        break;
}

if ($export === 'csv') {
    exportCsv(str_replace(' ', '_', strtolower($title)) . '.csv', $headers, $data);
}
if ($export === 'excel') {
    exportExcel(str_replace(' ', '_', strtolower($title)) . '.xls', $headers, $data);
}
if ($export === 'pdf') {
    generateReportPdf($title, $headers, $data);
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar-admin.php';
?>

<?php require __DIR__ . '/../includes/topbar.php'; ?>

<div class="content-area content-wrap">
        <div class="card">
            <div class="card-header"><h2>Generate Report</h2></div>
            <form method="GET" class="report-filters">
                <div class="form-group">
                    <label>Report Type</label>
                    <select name="type" id="reportType">
                        <option value="orders" <?= $reportType === 'orders' ? 'selected' : '' ?>>Delivery Orders</option>
                        <option value="rider_performance" <?= $reportType === 'rider_performance' ? 'selected' : '' ?>>Rider Performance</option>
                        <option value="parcel_delivery" <?= $reportType === 'parcel_delivery' ? 'selected' : '' ?>>Parcel Delivery</option>
                        <option value="daily" <?= $reportType === 'daily' ? 'selected' : '' ?>>Daily Deliveries</option>
                        <option value="monthly" <?= $reportType === 'monthly' ? 'selected' : '' ?>>Monthly Deliveries</option>
                        <option value="failed" <?= $reportType === 'failed' ? 'selected' : '' ?>>Failed Deliveries</option>
                    </select>
                </div>
                <div class="form-group orders-fields <?= $reportType === 'orders' ? '' : 'hidden' ?>" id="ordersSearchGroup">
                    <label for="reportSearch">Search Orders</label>
                    <input type="search" id="reportSearch" name="search" value="<?= sanitize($search) ?>"
                           placeholder="Tracking, name, rider, address, remarks...">
                </div>
                <div class="form-group orders-fields <?= $reportType === 'orders' ? '' : 'hidden' ?>" id="ordersStatusGroup">
                    <label for="reportStatus">Status</label>
                    <select name="status" id="reportStatus">
                        <option value="">All statuses</option>
                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="out_for_delivery" <?= $statusFilter === 'out_for_delivery' ? 'selected' : '' ?>>Out For Delivery</option>
                        <option value="delivered" <?= $statusFilter === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                        <option value="failed" <?= $statusFilter === 'failed' ? 'selected' : '' ?>>Failed</option>
                    </select>
                </div>
                <div class="form-group" id="dateFromGroup">
                    <label>Date From</label>
                    <input type="date" name="date_from" value="<?= sanitize($dateFrom) ?>">
                </div>
                <div class="form-group" id="dateToGroup">
                    <label>Date To</label>
                    <input type="date" name="date_to" value="<?= sanitize($dateTo) ?>">
                </div>
                <div class="form-group monthly-fields hidden" id="monthlyFields">
                    <label>Month</label>
                    <input type="month" name="month_year" value="<?= date('Y-m') ?>">
                </div>
                <button type="submit" class="btn btn-primary">Generate</button>
            </form>
        </div>

        <?php if ($reportType === 'orders'): ?>
        <div class="card" id="reportCard">
            <div class="card-header">
                <h2><?= sanitize($title) ?></h2>
                <div class="btn-group">
                    <?php
                    $exportParams = http_build_query(array_merge($_GET, ['export' => '']));
                    $baseParams = preg_replace('/&?export=$/', '', $exportParams);
                    ?>
                    <?php if (!empty($data)): ?>
                    <a href="?<?= $baseParams ?>&export=csv" class="btn btn-sm btn-outline">Export CSV</a>
                    <a href="?<?= $baseParams ?>&export=excel" class="btn btn-sm btn-outline">Export Excel</a>
                    <a href="?<?= $baseParams ?>&export=pdf" class="btn btn-sm btn-outline" target="_blank">Export PDF</a>
                    <?php endif; ?>
                    <button onclick="window.print()" class="btn btn-sm btn-primary">Print</button>
                </div>
            </div>
            <?php if (empty($orderRows)): ?>
                <p class="report-empty-state">No orders found for the selected filters.</p>
            <?php else: ?>
            <p class="report-result-count"><?= count($orderRows) ?> order(s) found</p>
            <div class="table-responsive">
                <table class="data-table report-table orders-report-table">
                    <thead>
                        <tr>
                            <th>Tracking</th>
                            <th>Receiver</th>
                            <th>Delivery Address</th>
                            <th>Remarks</th>
                            <th>Rider</th>
                            <th>Route</th>
                            <th>Status</th>
                            <th>Delivered</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orderRows as $row): ?>
                        <tr>
                            <td>
                                <a href="<?= sanitize(linkWithReturn('admin/parcel-view.php', ['id' => (int) $row['id']])) ?>" class="table-link">
                                    <?= sanitize($row['tracking_number']) ?>
                                </a>
                            </td>
                            <td>
                                <strong><?= sanitize($row['receiver_name']) ?></strong>
                                <?php if (!empty($row['receiver_phone']) && $row['receiver_phone'] !== '-'): ?>
                                    <br><small class="text-muted"><?= sanitize($row['receiver_phone']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="report-address-cell"><?= sanitize($row['delivery_address']) ?></td>
                            <td><?= sanitize($row['parcel_description'] ?: '—') ?></td>
                            <td>
                                <?php if (!empty($row['rider_name'])): ?>
                                    <?= sanitize($row['rider_name']) ?>
                                    <br><small class="text-muted"><?= sanitize($row['rider_code']) ?></small>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= sanitize(formatRouteDistance((float) $row['route_distance_km'], (int) $row['route_points'])) ?></strong>
                                <?php if ((int) $row['route_points'] > 0): ?>
                                    <br><small class="text-muted"><?= (int) $row['route_points'] ?> GPS points</small>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?= statusBadgeClass($row['status']) ?>"><?= formatStatus($row['status']) ?></span></td>
                            <td><?= $row['delivered_at'] ? sanitize(formatDate($row['delivered_at'])) : '—' ?></td>
                            <td class="report-actions-cell">
                                <a href="<?= sanitize(linkWithReturn('admin/parcel-view.php', ['id' => (int) $row['id']])) ?>" class="btn btn-sm btn-outline">Order</a>
                                <?php if (!empty($row['assigned_rider_id']) && (int) $row['route_points'] > 1): ?>
                                <a href="<?= sanitize(linkWithReturn('admin/rider-view.php', ['id' => (int) $row['assigned_rider_id'], 'parcel_id' => (int) $row['id']])) ?>" class="btn btn-sm btn-primary">Route</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php elseif (!empty($data)): ?>
        <div class="card" id="reportCard">
            <div class="card-header">
                <h2><?= sanitize($title) ?></h2>
                <div class="btn-group">
                    <?php
                    $exportParams = http_build_query(array_merge($_GET, ['export' => '']));
                    $baseParams = preg_replace('/&?export=$/', '', $exportParams);
                    ?>
                    <a href="?<?= $baseParams ?>&export=csv" class="btn btn-sm btn-outline">Export CSV</a>
                    <a href="?<?= $baseParams ?>&export=excel" class="btn btn-sm btn-outline">Export Excel</a>
                    <a href="?<?= $baseParams ?>&export=pdf" class="btn btn-sm btn-outline" target="_blank">Export PDF</a>
                    <button onclick="window.print()" class="btn btn-sm btn-primary">Print</button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="data-table report-table">
                    <thead>
                        <tr><?php foreach ($headers as $h): ?><th><?= sanitize($h) ?></th><?php endforeach; ?></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data as $row): ?>
                        <tr><?php foreach ($row as $cell): ?><td><?= sanitize((string) $cell) ?></td><?php endforeach; ?></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
</div>

<script>
document.getElementById('reportType')?.addEventListener('change', function () {
    const isOrders = this.value === 'orders';
    document.querySelectorAll('.orders-fields').forEach(el => {
        el.classList.toggle('hidden', !isOrders);
    });
});
</script>

<?php require __DIR__ . '/../includes/app-shell-end.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
