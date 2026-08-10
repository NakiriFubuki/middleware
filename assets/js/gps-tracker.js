/**
 * GPS Tracker - Sends location updates while rider is logged in
 */
const GpsTracker = {
    watchId: null,
    retryTimer: null,
    updateInterval: 20000,
    apiUrl: '',
    isTracking: false,
    lastSentAt: 0,
    lastSentLat: null,
    lastSentLng: null,
    minSendIntervalMs: 18000,
    minMoveMeters: 12,
    maxAccuracyMeters: 75,
    permissionDenied: false,
    lastErrorCode: null,
    lastToastAt: 0,
    hasCenteredMap: false,
    lowAccuracyMode: false,

    isAutoGpsEnabled() {
        return document.body.dataset.riderAutoGps === '1';
    },

    init(apiUrl) {
        this.apiUrl = apiUrl;

        const toggle = document.getElementById('onlineToggle');
        const retryBtn = document.getElementById('enableLocationBtn');

        if (toggle) {
            toggle.addEventListener('change', () => this.handleToggle(toggle.checked));
        }

        if (retryBtn) {
            retryBtn.addEventListener('click', () => this.retryLocation());
        }

        this.checkPermissionState();

        if (this.isAutoGpsEnabled() || (toggle && toggle.checked)) {
            setTimeout(() => this.checkPermissionAndStart(), 800);
        }

        window.addEventListener('beforeunload', () => this.stopTracking());
    },

    async checkPermissionState() {
        if (!navigator.permissions || !navigator.permissions.query) return;

        try {
            const result = await navigator.permissions.query({ name: 'geolocation' });
            this.updatePermissionUI(result.state);
            result.onchange = () => this.updatePermissionUI(result.state);
        } catch (e) {
            // Permissions API not fully supported
        }
    },

    updatePermissionUI(state) {
        const help = document.getElementById('locationHelp');
        if (!help) return;

        if (state === 'granted') {
            help.classList.add('hidden');
            this.permissionDenied = false;
        } else if (state === 'denied') {
            help.classList.remove('hidden');
            this.permissionDenied = true;
            this.showPermissionHelp(false);
        }
    },

    async checkPermissionAndStart() {
        await this.checkPermissionState();
        if (!this.permissionDenied) {
            this.startTracking();
        } else {
            this.showPermissionHelp(false);
        }
    },

    async handleToggle(isOnline) {
        const toggle = document.getElementById('onlineToggle');
        const label = document.getElementById('toggleLabel');
        const statusEl = document.getElementById('locationStatus');

        try {
            const res = await Ajax.post(
                Ajax.getBaseUrl() + '/api/update_online_status.php',
                { is_online: isOnline ? 1 : 0 }
            );

            if (res.success) {
                if (label) label.textContent = isOnline ? 'Online' : 'Offline';

                if (isOnline) {
                    this.permissionDenied = false;
                    this.lowAccuracyMode = false;
                    if (statusEl) {
                        statusEl.innerHTML = '<span class="badge badge-success">Online</span>';
                    }
                    setTimeout(() => this.checkPermissionAndStart(), 300);
                    Toast.success('You are online.');
                } else {
                    this.stopTracking();
                    this.hasCenteredMap = false;
                    if (typeof RiderLocationMap !== 'undefined') {
                        RiderLocationMap.resetSession();
                    }
                    this.hidePermissionHelp();
                    if (statusEl) {
                        statusEl.innerHTML = '<span class="badge badge-secondary">Offline</span>';
                    }
                    Toast.info('You are now offline.');
                }
            } else {
                if (toggle) toggle.checked = !isOnline;
                Toast.error(res.message);
            }
        } catch (e) {
            if (toggle) toggle.checked = !isOnline;
            Toast.error('Failed to update status.');
        }
    },

    retryLocation() {
        this.permissionDenied = false;
        this.lastErrorCode = null;
        this.lowAccuracyMode = false;
        this.hidePermissionHelp();
        const statusEl = document.getElementById('locationStatus');
        if (statusEl) {
            statusEl.innerHTML = '<span class="badge badge-success">Online</span>';
        }
        this.startTracking(true);
    },

    startTracking(force) {
        if (!navigator.geolocation) {
            this.showStatusMessage('', 'warning');
            return;
        }

        if (this.permissionDenied && !force) {
            this.showPermissionHelp(false);
            return;
        }

        this.isTracking = true;
        if (force) {
            this.hasCenteredMap = false;
            this.lowAccuracyMode = false;
        }

        this.stopWatchOnly();
        setTimeout(() => this.beginWatch(), force ? 200 : 400);
    },

    beginWatch() {
        if (!this.isTracking || !navigator.geolocation) return;

        const options = this.lowAccuracyMode
            ? { enableHighAccuracy: false, maximumAge: 120000, timeout: 10000 }
            : { enableHighAccuracy: false, maximumAge: 60000, timeout: 12000 };

        this.watchId = navigator.geolocation.watchPosition(
            pos => this.handlePosition(pos, !this.hasCenteredMap),
            err => this.handleGeoError(err),
            options
        );
    },

    stopWatchOnly() {
        if (this.watchId !== null) {
            navigator.geolocation.clearWatch(this.watchId);
            this.watchId = null;
        }
        if (this.retryTimer) {
            clearTimeout(this.retryTimer);
            this.retryTimer = null;
        }
    },

    stopTracking() {
        this.isTracking = false;
        this.stopWatchOnly();
    },

    handlePosition(position, shouldCenter) {
        this.permissionDenied = false;
        this.lastErrorCode = null;
        this.hidePermissionHelp();

        const { latitude, longitude, accuracy } = position.coords;
        const centerMap = shouldCenter || !this.hasCenteredMap;

        if (typeof RiderLocationMap !== 'undefined' && RiderLocationMap.map) {
            RiderLocationMap.updatePosition(latitude, longitude, accuracy, centerMap);
            if (centerMap) {
                this.hasCenteredMap = true;
            }
        }

        if (typeof DeliveryMap !== 'undefined' && DeliveryMap.map) {
            DeliveryMap.updateRiderPosition(latitude, longitude);
        }

        this.sendLocation(position, centerMap);
    },

    distanceMeters(lat1, lon1, lat2, lon2) {
        const earth = 6371000;
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat / 2) ** 2
            + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLon / 2) ** 2;
        return earth * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    },

    shouldSendPosition(latitude, longitude, accuracy, forceSend) {
        if (forceSend) return true;
        if (accuracy && accuracy > this.maxAccuracyMeters) return false;

        const now = Date.now();
        if (now - this.lastSentAt < this.minSendIntervalMs) {
            if (this.lastSentLat === null || this.lastSentLng === null) {
                return true;
            }

            const moved = this.distanceMeters(this.lastSentLat, this.lastSentLng, latitude, longitude);
            if (moved < this.minMoveMeters) {
                return false;
            }
        }

        return true;
    },

    async sendLocation(position, forceSend) {
        if (!this.isTracking) return;

        const { latitude, longitude, accuracy } = position.coords;

        if (!this.shouldSendPosition(latitude, longitude, accuracy, forceSend)) {
            return;
        }

        const now = Date.now();
        this.lastSentAt = now;

        try {
            const res = await Ajax.post(
                this.apiUrl || Ajax.getBaseUrl() + '/api/update_location.php',
                { latitude, longitude, accuracy },
                { timeout: 12000 }
            );

            if (res.success) {
                this.lastSentLat = latitude;
                this.lastSentLng = longitude;
                if (typeof DeliveryMap !== 'undefined' && typeof DeliveryMap.loadMyTrail === 'function') {
                    DeliveryMap.loadMyTrail();
                }
                this.showStatusMessage(
                    `${latitude.toFixed(5)}, ${longitude.toFixed(5)}`,
                    'success'
                );
            } else if (res.message) {
                this.showStatusMessage('', 'warning');
            }
        } catch (e) {
            console.error('Location upload failed:', e);
            this.showStatusMessage('', 'warning');
        }
    },

    handleGeoError(error) {
        if (error.code === 1) {
            this.permissionDenied = true;
            this.stopTracking();
            this.showPermissionHelp(false);
            return;
        }

        this.showStatusMessage('', 'warning');

        if (error.code === 3 && this.isTracking) {
            this.stopWatchOnly();
            this.lowAccuracyMode = true;
            this.retryTimer = setTimeout(() => {
                if (this.isTracking) this.beginWatch();
            }, 30000);
        }
    },

    showStatusMessage(text, type) {
        const statusEl = document.getElementById('locationStatus');
        if (!statusEl) return;
        const badge = type === 'success' ? 'badge-success' : (type === 'warning' ? 'badge-warning' : 'badge-secondary');
        statusEl.innerHTML = text
            ? `<span class="badge ${badge}">Online</span> — ${text}`
            : `<span class="badge ${badge}">Online</span>`;
    },

    showPermissionHelp() {
        const help = document.getElementById('locationHelp');
        if (help) help.classList.remove('hidden');
        this.showStatusMessage('', 'warning');
    },

    hidePermissionHelp() {
        const help = document.getElementById('locationHelp');
        if (help) help.classList.add('hidden');
    }
};

document.addEventListener('DOMContentLoaded', () => {
    if (GpsTracker.isAutoGpsEnabled() || document.getElementById('onlineToggle')) {
        GpsTracker.init(Ajax.getBaseUrl() + '/api/update_location.php');
    }
});
