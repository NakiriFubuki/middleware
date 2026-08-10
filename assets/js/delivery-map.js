/**
 * Delivery Map - rider position + address navigation route
 */
const DeliveryMap = {
    map: null,
    riderMarker: null,
    destMarker: null,
    routeLine: null,
    trailLine: null,
    watchId: null,
    destLatLng: null,
    parcelId: null,
    trailRefreshTimer: null,
    followRider: true,
    followZoom: 16,
    _lastRiderPos: null,
    _programmaticMove: false,
    recenterBtn: null,

    fetchWithTimeout(url, ms, options) {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), ms);
        return fetch(url, { ...options, signal: controller.signal }).finally(() => clearTimeout(timer));
    },

    init() {
        const el = document.getElementById('deliveryMap');
        if (!el || typeof L === 'undefined') return;

        const address = el.dataset.address || '';
        const riderLat = parseFloat(el.dataset.riderLat);
        const riderLng = parseFloat(el.dataset.riderLng);
        const hasRider = !Number.isNaN(riderLat) && !Number.isNaN(riderLng);
        this.parcelId = parseInt(el.dataset.parcelId, 10) || null;

        this.map = L.map(el, { zoomControl: true, minZoom: 5, maxZoom: 19 })
            .setView(hasRider ? [riderLat, riderLng] : [14.5995, 120.9842], hasRider ? 15 : 12);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap',
            maxZoom: 19
        }).addTo(this.map);

        setTimeout(() => this.map.invalidateSize(), 150);

        this.setupFollowMode();
        this.addRecenterControl();

        if (hasRider) {
            this.updateRiderPosition(riderLat, riderLng, true);
        }

        if (address) {
            const from = hasRider ? [riderLat, riderLng] : null;
            setTimeout(() => this.loadAddressRoute(address, from), 500);
        }

        this.startGpsWatch();
        this.bindNavButtons(address);
        this.loadMyTrail();
        this.trailRefreshTimer = setInterval(() => this.loadMyTrail(), 15000);
        window.addEventListener('beforeunload', () => {
            if (this.trailRefreshTimer) clearInterval(this.trailRefreshTimer);
        });
    },

    bindNavButtons(address) {
        document.getElementById('openGoogleMapsBtn')?.addEventListener('click', () => {
            if (!address) return;
            window.open('https://www.google.com/maps/dir/?api=1&destination=' + encodeURIComponent(address), '_blank');
        });
        document.getElementById('openWazeBtn')?.addEventListener('click', () => {
            if (!this.destLatLng) {
                Toast.warning('Delivery address not located yet.');
                return;
            }
            const [lat, lng] = this.destLatLng;
            window.open(`https://waze.com/ul?ll=${lat},${lng}&navigate=yes`, '_blank');
        });
        document.getElementById('refreshRouteBtn')?.addEventListener('click', () => {
            const el = document.getElementById('deliveryMap');
            if (el && el.dataset.address) {
                const pos = this.riderMarker ? this.riderMarker.getLatLng() : null;
                const from = pos ? [pos.lat, pos.lng] : null;
                this.loadAddressRoute(el.dataset.address, from);
            }
            this.loadMyTrail();
        });
    },

    async loadMyTrail() {
        if (!this.parcelId || !this.map) return;

        try {
            const res = await Ajax.get(
                `${Ajax.getBaseUrl()}/api/get_my_route.php?parcel_id=${this.parcelId}`
            );
            if (!res.success) return;

            const rawPoints = typeof RouteUtils !== 'undefined'
                ? RouteUtils.historyToPoints(res.history)
                : (res.history || [])
                    .map(h => [parseFloat(h.latitude), parseFloat(h.longitude)])
                    .filter(p => !Number.isNaN(p[0]) && !Number.isNaN(p[1]));

            if (rawPoints.length < 2) {
                if (this.trailLine) {
                    this.map.removeLayer(this.trailLine);
                    this.trailLine = null;
                }
                return;
            }

            const points = typeof RouteUtils !== 'undefined'
                ? RouteUtils.cleanRoute(rawPoints)
                : rawPoints;

            if (this.trailLine) {
                this.trailLine.setLatLngs(points);
            } else {
                this.trailLine = L.polyline(points, {
                    color: '#16a34a',
                    weight: 4,
                    opacity: 0.75,
                    dashArray: '8 6',
                    smoothFactor: 1.2
                }).addTo(this.map);
                this.trailLine.bringToBack();
            }

            const statusEl = document.getElementById('deliveryMapStatus');
            if (statusEl && !statusEl.textContent.includes('Route ready')) {
                statusEl.textContent = `Trail: ${points.length} pts`;
            }
        } catch (e) {
            console.error('Trail load failed:', e);
        }
    },

    createRiderIcon() {
        return L.divIcon({
            className: 'rider-marker-icon',
            html: '<div class="rider-marker online">📍</div>',
            iconSize: [36, 36],
            iconAnchor: [18, 18]
        });
    },

    createDestIcon() {
        return L.divIcon({
            className: 'rider-marker-icon',
            html: '<div class="rider-marker dest-marker">🏠</div>',
            iconSize: [36, 36],
            iconAnchor: [18, 18]
        });
    },

    setupFollowMode() {
        this.followRider = true;
        this._programmaticMove = false;

        this.map.on('dragstart', () => {
            if (!this._programmaticMove) {
                this.followRider = false;
                this.updateRecenterButton();
            }
        });

        this.map.on('zoomstart', (e) => {
            if (e.originalEvent && !this._programmaticMove) {
                this.followRider = false;
                this.updateRecenterButton();
            }
        });
    },

    addRecenterControl() {
        const self = this;
        const control = L.control({ position: 'bottomleft' });

        control.onAdd = function () {
            const wrap = L.DomUtil.create('div', 'leaflet-bar map-recenter-control');
            const btn = L.DomUtil.create('button', 'map-recenter-btn', wrap);
            btn.type = 'button';
            btn.title = '回到当前位置';
            btn.setAttribute('aria-label', '回到当前位置');
            btn.innerHTML = '<span class="map-recenter-icon" aria-hidden="true"></span>';
            L.DomEvent.disableClickPropagation(btn);
            L.DomEvent.on(btn, 'click', L.DomEvent.stopPropagation);
            L.DomEvent.on(btn, 'click', () => self.recenterOnRider());
            self.recenterBtn = btn;
            self.updateRecenterButton();
            return wrap;
        };

        control.addTo(this.map);
    },

    updateRecenterButton() {
        if (!this.recenterBtn) return;
        this.recenterBtn.classList.toggle('is-following', this.followRider);
        this.recenterBtn.title = this.followRider ? '正在跟随您的位置' : '回到当前位置';
    },

    pauseFollow() {
        this.followRider = false;
        this.updateRecenterButton();
    },

    recenterOnRider() {
        let lat;
        let lng;

        if (this._lastRiderPos) {
            [lat, lng] = this._lastRiderPos;
        } else if (this.riderMarker) {
            const p = this.riderMarker.getLatLng();
            lat = p.lat;
            lng = p.lng;
        } else {
            return;
        }

        this.followRider = true;
        this._programmaticMove = true;
        this.map.setView([lat, lng], this.followZoom, { animate: true });
        setTimeout(() => { this._programmaticMove = false; }, 400);
        this.updateRecenterButton();
    },

    setRiderPosition(lat, lng) {
        const latLng = [lat, lng];
        if (!this.riderMarker) {
            this.riderMarker = L.marker(latLng, { icon: this.createRiderIcon() })
                .addTo(this.map)
                .bindPopup('<strong>You are here</strong>');
        } else {
            this.riderMarker.setLatLng(latLng);
        }
    },

    updateRiderPosition(lat, lng, forceCenter) {
        this.setRiderPosition(lat, lng);
        this._lastRiderPos = [lat, lng];

        if ((this.followRider || forceCenter) && this.map) {
            const zoom = forceCenter ? this.followZoom : Math.max(this.map.getZoom(), this.followZoom);
            this._programmaticMove = true;
            this.map.setView([lat, lng], zoom, { animate: !forceCenter });
            setTimeout(() => { this._programmaticMove = false; }, 400);
            if (forceCenter) {
                this.followRider = true;
            }
        }

        this.updateRecenterButton();
    },

    startGpsWatch() {
        if (!navigator.geolocation) return;

        if (typeof GpsTracker !== 'undefined' && GpsTracker.isTracking) {
            return;
        }

        if (this.watchId !== null) {
            navigator.geolocation.clearWatch(this.watchId);
        }

        setTimeout(() => {
            this.watchId = navigator.geolocation.watchPosition(
                pos => {
                    const { latitude, longitude } = pos.coords;
                    this.updateRiderPosition(latitude, longitude);

                    if (typeof GpsTracker !== 'undefined' && GpsTracker.isTracking) {
                        GpsTracker.handlePosition(pos, false);
                    }

                    const statusEl = document.getElementById('deliveryMapStatus');
                    if (statusEl) statusEl.textContent = 'GPS active';
                },
                () => {},
                { enableHighAccuracy: false, maximumAge: 60000, timeout: 12000 }
            );
        }, 600);
    },

    async geocodeAddress(address) {
        const url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q='
            + encodeURIComponent(address);

        const res = await this.fetchWithTimeout(url, 15000, {
            headers: { 'Accept': 'application/json' }
        });
        const data = await res.json();

        if (!data || !data.length) return null;

        return {
            lat: parseFloat(data[0].lat),
            lng: parseFloat(data[0].lon),
            displayName: data[0].display_name
        };
    },

    async fetchDrivingRoute(fromLatLng, toLatLng) {
        const [fromLat, fromLng] = fromLatLng;
        const [toLat, toLng] = toLatLng;
        const url = `https://router.project-osrm.org/route/v1/driving/${fromLng},${fromLat};${toLng},${toLat}?overview=full&geometries=geojson`;

        const res = await this.fetchWithTimeout(url, 15000);
        const data = await res.json();

        if (data.code !== 'Ok' || !data.routes || !data.routes.length) {
            return null;
        }

        const coords = data.routes[0].geometry.coordinates;
        return coords.map(c => [c[1], c[0]]);
    },

    async loadAddressRoute(address, fromLatLng) {
        const statusEl = document.getElementById('deliveryMapStatus');
        if (statusEl) statusEl.textContent = 'Finding address...';

        try {
            const dest = await this.geocodeAddress(address);
            if (!dest) {
                if (statusEl) statusEl.textContent = 'Address not found on map';
                Toast.warning('Could not locate delivery address. Use Open in Google Maps.');
                return;
            }

            this.destLatLng = [dest.lat, dest.lng];

            if (this.destMarker) {
                this.destMarker.setLatLng(this.destLatLng);
            } else {
                this.destMarker = L.marker(this.destLatLng, { icon: this.createDestIcon() })
                    .addTo(this.map)
                    .bindPopup(`<strong>Delivery</strong><br>${this.esc(address)}`);
            }

            let from = fromLatLng;
            if (!from && this.riderMarker) {
                const p = this.riderMarker.getLatLng();
                from = [p.lat, p.lng];
            }

            if (this.routeLine) {
                this.map.removeLayer(this.routeLine);
                this.routeLine = null;
            }

            if (from) {
                const routePoints = await this.fetchDrivingRoute(from, this.destLatLng);
                if (routePoints && routePoints.length) {
                    this.routeLine = L.polyline(routePoints, {
                        color: '#2563eb',
                        weight: 5,
                        opacity: 0.8,
                        smoothFactor: 1.2
                    }).addTo(this.map);

                    if (this.trailLine) {
                        this.trailLine.bringToBack();
                    }

                    const bounds = L.latLngBounds(routePoints);
                    this._programmaticMove = true;
                    this.map.fitBounds(bounds.pad(0.15));
                    setTimeout(() => { this._programmaticMove = false; }, 400);
                    this.pauseFollow();
                    if (statusEl) statusEl.textContent = 'Route ready';
                    return;
                }
            }

            this._programmaticMove = true;
            this.map.fitBounds(L.latLngBounds([this.destLatLng, from || this.destLatLng]).pad(0.2));
            setTimeout(() => { this._programmaticMove = false; }, 400);
            this.pauseFollow();
            if (statusEl) statusEl.textContent = from ? 'Destination marked' : 'Allow GPS for route';
        } catch (e) {
            console.error('Route load failed:', e);
            if (statusEl) statusEl.textContent = 'Route unavailable';
        }
    },

    esc(str) {
        const d = document.createElement('div');
        d.textContent = str || '';
        return d.innerHTML;
    }
};

document.addEventListener('DOMContentLoaded', () => DeliveryMap.init());
