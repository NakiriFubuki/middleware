/**
 * Rider Dashboard - Stats refresh
 */
document.addEventListener('DOMContentLoaded', () => {
    if (!document.querySelector('.stats-grid')) return;

    setInterval(async () => {
        try {
            const res = await Ajax.get(Ajax.getBaseUrl() + '/api/dashboard.php');
            if (res.success && res.stats) {
                document.querySelectorAll('.stat-value').forEach((el, i) => {
                    const keys = ['total_assigned', 'pending', 'out_for_delivery', 'delivered', 'failed', 'today_delivered'];
                    if (keys[i] && res.stats[keys[i]] !== undefined) {
                        el.textContent = res.stats[keys[i]];
                    }
                });
            }
        } catch (e) {}
    }, 60000);
});
