/**
 * Admin Live Tracking - real-time delivery feed
 */
const LiveTracking = {
    interval: null,
    refreshMs: 5000,

    init() {
        const hasFeed = document.getElementById('activeDeliveriesBody')
            || document.getElementById('onlineRidersList')
            || document.getElementById('statusFeed');

        if (!hasFeed) return;

        this.refresh();
        this.interval = setInterval(() => this.refresh(), this.refreshMs);
    },

    async refresh() {
        try {
            const res = await Ajax.get(Ajax.getBaseUrl() + '/api/live_feed.php');
            if (!res.success) return;

            const data = res.data;
            this.renderActiveDeliveries(data.active_deliveries || []);
            this.renderStatusFeed(data.status_updates || []);
            this.renderLocationFeed(data.location_updates || []);
            this.renderOnlineRiders(data.online_riders || []);
            this.updateLiveIndicator(data.server_time);
        } catch (e) {
            console.error('Live feed refresh failed', e);
        }
    },

    updateLiveIndicator(time) {
        const el = document.getElementById('liveIndicator');
        if (el) {
            el.textContent = 'Live';
        }
    },

    renderActiveDeliveries(list) {
        const tbody = document.getElementById('activeDeliveriesBody');
        if (!tbody) return;

        if (!list.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center">—</td></tr>';
            return;
        }

        tbody.innerHTML = list.map(d => `
            <tr class="delivery-row-clickable" data-rider-id="${d.rider_id || ''}" data-lat="${d.last_latitude || ''}" data-lng="${d.last_longitude || ''}">
                <td><strong>${this.esc(d.tracking_number)}</strong></td>
                <td>${this.esc(d.rider_name)} <small>(${this.esc(d.rider_code)})</small></td>
                <td>${this.esc(d.receiver_name)}</td>
                <td>${this.esc(d.delivery_address)}</td>
                <td>${d.last_latitude ? `${parseFloat(d.last_latitude).toFixed(5)}, ${parseFloat(d.last_longitude).toFixed(5)}` : '—'}</td>
                <td>${d.last_location_at ? this.formatTime(d.last_location_at) : '—'}</td>
            </tr>
        `).join('');

        tbody.querySelectorAll('.delivery-row-clickable').forEach(row => {
            row.style.cursor = 'pointer';
            row.addEventListener('click', () => {
                if (typeof MapManager !== 'undefined' && MapManager.focusRider) {
                    MapManager.focusRider(row.dataset.riderId, row.dataset.lat, row.dataset.lng);
                }
            });
        });
    },

    renderStatusFeed(updates) {
        const el = document.getElementById('statusFeed');
        if (!el) return;

        if (!updates.length) {
            el.innerHTML = '';
            return;
        }

        el.innerHTML = updates.map(u => `
            <div class="feed-item">
                <div class="feed-time">${this.formatTime(u.created_at)}</div>
                <div class="feed-content">
                    <strong>${this.esc(u.tracking_number)}</strong>
                    <span class="badge badge-${this.statusClass(u.status)}">${this.statusLabel(u.status)}</span>
                    <p>${this.esc(u.remarks || '')} — <em>${this.esc(u.rider_name || 'System')}</em></p>
                </div>
            </div>
        `).join('');
    },

    renderLocationFeed(locations) {
        const el = document.getElementById('locationFeed');
        if (!el) return;

        if (!locations.length) {
            el.innerHTML = '';
            return;
        }

        el.innerHTML = locations.map(l => `
            <div class="feed-item feed-item-location feed-item-clickable"
                 data-rider-id="${l.rider_id || ''}"
                 data-lat="${l.latitude}" data-lng="${l.longitude}">
                <div class="feed-time">${this.formatTime(l.created_at)}</div>
                <div class="feed-content">
                    <strong>${this.esc(l.rider_name)}</strong> (${this.esc(l.rider_code)})
                    <p>📍 ${parseFloat(l.latitude).toFixed(5)}, ${parseFloat(l.longitude).toFixed(5)}</p>
                </div>
            </div>
        `).join('');

        el.querySelectorAll('.feed-item-clickable').forEach(item => {
            item.style.cursor = 'pointer';
            item.addEventListener('click', () => {
                if (typeof MapManager !== 'undefined' && MapManager.focusRider && item.dataset.riderId) {
                    MapManager.focusRider(item.dataset.riderId, item.dataset.lat, item.dataset.lng);
                } else if (MapManager.map) {
                    MapManager.map.flyTo([parseFloat(item.dataset.lat), parseFloat(item.dataset.lng)], 16, { duration: 0.8 });
                }
            });
        });
    },

    renderOnlineRiders(riders) {
        const el = document.getElementById('onlineRidersList');
        if (!el) return;

        if (!riders.length) {
            el.innerHTML = '';
            return;
        }

        el.innerHTML = riders.map(r => {
            const hasGps = r.last_latitude && r.last_longitude;
            const gpsText = hasGps
                ? `${parseFloat(r.last_latitude).toFixed(5)}, ${parseFloat(r.last_longitude).toFixed(5)} · ${this.formatTime(r.last_location_at)}`
                : '—';
            const activeNote = (r.active_deliveries || 0) > 0
                ? `${r.active_deliveries}`
                : '0';
            return `
            <div class="online-rider-item"
                 data-rider-id="${r.id}"
                 data-lat="${hasGps ? r.last_latitude : ''}"
                 data-lng="${hasGps ? r.last_longitude : ''}">
                <span class="badge badge-success">Online</span>
                <strong>${this.esc(r.full_name)}</strong> (${this.esc(r.rider_code)})
                <small>${activeNote} · ${gpsText}</small>
            </div>`;
        }).join('');

        el.querySelectorAll('.online-rider-item').forEach(item => {
            if (document.getElementById('liveMap')) {
                item.style.cursor = 'pointer';
            }
            if (!item.dataset.bound) {
                item.dataset.bound = '1';
                item.addEventListener('click', () => {
                    if (typeof MapManager !== 'undefined' && MapManager.focusRider && document.getElementById('liveMap')) {
                        MapManager.focusRider(item.dataset.riderId, item.dataset.lat, item.dataset.lng);
                    } else {
                        window.location.href = Ajax.getBaseUrl() + '/admin/live-tracking.php';
                    }
                });
            }
        });

        if (typeof MapManager !== 'undefined' && MapManager.bindRiderClickHandlers) {
            MapManager.bindRiderClickHandlers();
        }
    },

    statusClass(s) {
        return { pending: 'warning', out_for_delivery: 'info', delivered: 'success', failed: 'danger' }[s] || 'secondary';
    },

    statusLabel(s) {
        return { pending: 'Pending', out_for_delivery: 'Out For Delivery', delivered: 'Delivered', failed: 'Failed' }[s] || s;
    },

    formatTime(dt) {
        if (!dt) return '—';
        return new Date(dt.replace(' ', 'T')).toLocaleString();
    },

    esc(str) {
        const d = document.createElement('div');
        d.textContent = str || '';
        return d.innerHTML;
    }
};

document.addEventListener('DOMContentLoaded', () => LiveTracking.init());
