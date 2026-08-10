/**
 * Admin Dashboard - Auto refresh stats and live deliveries
 */
document.addEventListener('DOMContentLoaded', () => {
    const statsGrid = document.getElementById('dashboardStats');
    const activeBody = document.getElementById('activeDeliveriesBody');
    const onlineList = document.getElementById('onlineRidersList');

    async function refresh() {
        try {
            const dashRes = await Ajax.get(Ajax.getBaseUrl() + '/api/dashboard.php');
            if (dashRes.success && dashRes.stats && statsGrid) {
                Object.entries(dashRes.stats).forEach(([key, value]) => {
                    const el = statsGrid.querySelector(`[data-stat="${key}"]`);
                    if (el) el.textContent = value;
                });
            }

            const liveRes = await Ajax.get(Ajax.getBaseUrl() + '/api/live_feed.php');
            if (liveRes.success && liveRes.data) {
                if (activeBody && typeof LiveTracking !== 'undefined') {
                    LiveTracking.renderActiveDeliveries(liveRes.data.active_deliveries || []);
                }

                if (onlineList && typeof LiveTracking !== 'undefined') {
                    LiveTracking.renderOnlineRiders(liveRes.data.online_riders || []);
                }

                const indicator = document.getElementById('liveIndicator');
                if (indicator && liveRes.data.server_time) {
                    indicator.textContent = 'Live — ' + new Date(liveRes.data.server_time).toLocaleTimeString();
                }
            }
        } catch (e) {}
    }

    refresh();
    setInterval(refresh, 5000);
});
