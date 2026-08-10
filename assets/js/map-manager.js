/**
 * Map Manager - Admin rider positions and route history
 */
const MapManager = {
    map: null,
    markers: {},
    routeLine: null,
    routeStartMarker: null,
    routeEndMarker: null,
    replayMarker: null,
    refreshInterval: null,
    detailRefreshInterval: null,
    apiUrl: '',
    lastAllRiders: [],
    selectedRiderId: null,
    _routePoints: [],
    _replayInterval: null,
    _activeParcelId: null,
    _routeParcels: [],

    init() {
        const liveMap = document.getElementById('liveMap');
        const riderDetailMap = document.getElementById('riderDetailMap');

        if (liveMap) this.initLiveMap(liveMap);
        if (riderDetailMap) this.initRiderDetailMap(riderDetailMap);

        document.getElementById('replayRouteBtn')?.addEventListener('click', () => this.replayRoute());
        document.getElementById('loadRouteBtn')?.addEventListener('click', () => this.loadRouteFromFilter());
        document.getElementById('routeParcelFilter')?.addEventListener('change', () => this.loadRouteFromFilter());
        document.getElementById('showRouteFilter')?.addEventListener('change', () => {
            if (this.selectedRiderId) {
                this.loadRiderRoute(this.selectedRiderId);
            }
        });
    },

    createRiderIcon(isOnline) {
        return L.divIcon({
            className: 'rider-marker-icon',
            html: `<div class="rider-marker ${isOnline ? 'online' : 'offline'}">🏍️</div>`,
            iconSize: [36, 36],
            iconAnchor: [18, 18]
        });
    },

    initLiveMap(el) {
        this.apiUrl = el.dataset.api;
        this._focusRiderId = parseInt(el.dataset.focusRider, 10) || null;
        this._focusParcelId = parseInt(el.dataset.focusParcel, 10) || null;
        if (this._focusParcelId) {
            this._activeParcelId = this._focusParcelId;
        }

        this.map = L.map(el, {
            zoomControl: true,
            scrollWheelZoom: true,
            minZoom: 5,
            maxZoom: 19
        }).setView([14.5995, 120.9842], 12);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(this.map);

        setTimeout(() => {
            this.map.invalidateSize();
            this.bindRiderClickHandlers();
        }, 150);

        window.addEventListener('resize', () => {
            if (this.map) this.map.invalidateSize();
        });

        this.loadRiders().then(() => {
            if (this._focusRiderId) {
                this.focusRider(this._focusRiderId, null, null);
            }
        });
        this.refreshInterval = setInterval(() => this.loadRiders(), 5000);

        document.getElementById('refreshMapBtn')?.addEventListener('click', () => this.loadRiders());
        document.getElementById('fitBoundsBtn')?.addEventListener('click', () => this.fitAllMarkers());
        document.getElementById('onlineOnlyFilter')?.addEventListener('change', () => {
            this.clearRouteOverlay();
            this.selectedRiderId = null;
            this.loadRiders();
        });
        document.getElementById('showRouteFilter')?.addEventListener('change', () => {
            if (this.selectedRiderId) {
                this.loadRiderRoute(this.selectedRiderId);
            }
        });
    },

    getApiUrl() {
        const onlineOnly = document.getElementById('onlineOnlyFilter')?.checked;
        return this.apiUrl + (onlineOnly ? '?online_only=1' : '');
    },

    getRiderApiUrl(riderId, extraParams) {
        const base = this.apiUrl.split('?')[0];
        let url = `${base}?rider_id=${riderId}`;
        if (extraParams) url += '&' + extraParams;
        return url;
    },

    getRouteQueryParams() {
        const showRoute = document.getElementById('showRouteFilter');

        if (showRoute && !showRoute.checked && document.getElementById('liveMap')) {
            return null;
        }

        const parcelFilter = document.getElementById('routeParcelFilter');
        if (parcelFilter) {
            if (parcelFilter.value === 'active') {
                return this._activeParcelId ? `parcel_id=${this._activeParcelId}` : null;
            }
            if (parcelFilter.value) {
                return `parcel_id=${parcelFilter.value}`;
            }
        }

        return this._activeParcelId ? `parcel_id=${this._activeParcelId}` : null;
    },

    updateRouteParcelFilter(parcels, selectedParcelId) {
        const select = document.getElementById('routeParcelFilter');
        if (!select || !parcels?.length) return;

        const currentValue = select.value;
        const activeOption = select.querySelector('option[value="active"]');
        const options = parcels.map(p => {
            const selected = String(p.parcel_id) === String(selectedParcelId) ? ' selected' : '';
            return `<option value="${p.parcel_id}"${selected}>${this.esc(p.tracking_number)} (${p.points} pts)</option>`;
        });

        select.innerHTML = (activeOption ? activeOption.outerHTML : '<option value="active">Current delivery</option>')
            + options.join('');

        if (currentValue && [...select.options].some(opt => opt.value === currentValue)) {
            select.value = currentValue;
        } else if (selectedParcelId) {
            select.value = String(selectedParcelId);
        }
    },

    applyRouteResponseMeta(res) {
        this._activeParcelId = res.active_parcel_id || res.rider?.active_parcel_id || this._activeParcelId || null;
        this._routeParcels = res.route_parcels || [];
        if (res.route_parcel_id) {
            this._activeParcelId = this._activeParcelId || res.route_parcel_id;
        }
        this.updateRouteParcelFilter(this._routeParcels, res.route_parcel_id || this._activeParcelId);
    },

    async loadRiders() {
        try {
            const res = await Ajax.get(this.getApiUrl());
            if (!res.success) return;

            const mapRiders = res.locations || [];
            const allRiders = res.riders || mapRiders;
            this.lastAllRiders = allRiders;
            const activeIds = new Set();

            mapRiders.forEach(rider => {
                if (!rider.latitude || !rider.longitude) return;

                activeIds.add(rider.id);
                const latLng = [rider.latitude, rider.longitude];

                if (this.markers[rider.id]) {
                    this.markers[rider.id].setLatLng(latLng);
                    this.markers[rider.id].setIcon(this.createRiderIcon(rider.is_online));
                } else {
                    const marker = L.marker(latLng, {
                        icon: this.createRiderIcon(rider.is_online)
                    }).addTo(this.map);

                    marker.bindPopup(this.createPopup(rider));
                    marker.on('click', () => {
                        this.focusRider(rider.id, rider.latitude, rider.longitude);
                    });
                    this.markers[rider.id] = marker;
                }

                this.markers[rider.id].setPopupContent(this.createPopup(rider));
            });

            Object.keys(this.markers).forEach(id => {
                if (!activeIds.has(parseInt(id))) {
                    this.map.removeLayer(this.markers[id]);
                    delete this.markers[id];
                }
            });

            this.updateRiderList(allRiders);
            this.updateMapHint(mapRiders, allRiders);
            this.bindRiderClickHandlers();

            if (this.selectedRiderId) {
                await this.loadRiderRoute(this.selectedRiderId, true);
            }
        } catch (e) {
            console.error('Failed to load riders:', e);
        }
    },

    updateMapHint(onMap, all) {
        const hint = document.getElementById('mapEmptyHint');
        if (!hint) return;

        if (onMap.length > 0) {
            hint.classList.add('hidden');
            return;
        }

        hint.classList.remove('hidden');
        hint.textContent = '';
    },

    createPopup(rider) {
        const status = rider.is_online
            ? '<span style="color:#16a34a">Online</span>'
            : '<span style="color:#64748b">Offline</span>';
        const lastUpdate = rider.last_update
            ? new Date(rider.last_update.replace(' ', 'T')).toLocaleString()
            : 'Never';
        const parcel = rider.active_parcel
            ? `<br>Delivering: <strong>${rider.active_parcel}</strong>`
            : '';

        return `<strong>${rider.full_name}</strong> (${rider.rider_code})<br>
                Status: ${status}${parcel}<br>
                Last update: ${lastUpdate}`;
    },

    clearRouteOverlay() {
        if (this.routeLine) {
            this.map.removeLayer(this.routeLine);
            this.routeLine = null;
        }
        if (this.routeStartMarker) {
            this.map.removeLayer(this.routeStartMarker);
            this.routeStartMarker = null;
        }
        if (this.routeEndMarker) {
            this.map.removeLayer(this.routeEndMarker);
            this.routeEndMarker = null;
        }
        if (this.replayMarker && this.map) {
            this.map.removeLayer(this.replayMarker);
            this.replayMarker = null;
        }
        if (this._replayInterval) {
            clearInterval(this._replayInterval);
            this._replayInterval = null;
        }
    },

    prepareRoutePoints(points) {
        if (!points || points.length < 2) return points || [];
        if (typeof RouteUtils !== 'undefined') {
            return RouteUtils.cleanRoute(points);
        }
        return points;
    },

    drawRouteOverlay(points, fitBounds) {
        this.clearRouteOverlay();
        if (!this.map || !points || points.length < 2) {
            this._routePoints = points || [];
            return;
        }

        const cleaned = this.prepareRoutePoints(points);
        this._routePoints = cleaned;
        this.routeLine = L.polyline(cleaned, {
            color: '#2563eb',
            weight: 4,
            opacity: 0.8,
            smoothFactor: 1.2
        }).addTo(this.map);

        this.routeStartMarker = L.circleMarker(cleaned[0], {
            radius: 6, color: '#16a34a', fillColor: '#16a34a', fillOpacity: 1
        }).addTo(this.map).bindPopup('Route start');

        this.routeEndMarker = L.circleMarker(cleaned[cleaned.length - 1], {
            radius: 6, color: '#dc2626', fillColor: '#dc2626', fillOpacity: 1
        }).addTo(this.map).bindPopup('Latest position');

        if (fitBounds) {
            const bounds = this.routeLine.getBounds();
            if (this.selectedRiderId && this.markers[this.selectedRiderId]) {
                bounds.extend(this.markers[this.selectedRiderId].getLatLng());
            }
            this.map.fitBounds(bounds.pad(0.12));
        }
    },

    async loadRiderRoute(riderId, skipFly) {
        if (!riderId || !this.apiUrl) return;

        const params = this.getRouteQueryParams();
        if (params === null) {
            this.clearRouteOverlay();
            return;
        }

        try {
            const res = await Ajax.get(this.getRiderApiUrl(riderId, params));
            if (!res.success) return;

            this.applyRouteResponseMeta(res);

            const points = typeof RouteUtils !== 'undefined'
                ? RouteUtils.historyToPoints(res.history)
                : (res.history || [])
                    .map(h => [parseFloat(h.latitude), parseFloat(h.longitude)])
                    .filter(p => !Number.isNaN(p[0]) && !Number.isNaN(p[1]));

            if (points.length >= 2) {
                this.drawRouteOverlay(points, !skipFly);
            } else {
                this.clearRouteOverlay();
            }

            const info = document.getElementById('routePointCount');
            if (info) {
                info.textContent = points.length ? String(points.length) : '';
            }

            const rider = res.rider;
            if (rider?.last_latitude && rider?.last_longitude && this.markers[riderId]) {
                this.markers[riderId].setLatLng([
                    parseFloat(rider.last_latitude),
                    parseFloat(rider.last_longitude)
                ]);
            }
        } catch (e) {
            console.error('Failed to load rider route:', e);
        }
    },

    loadRouteFromFilter() {
        const riderId = this.selectedRiderId || parseInt(document.getElementById('riderDetailMap')?.dataset.riderId);
        if (riderId) {
            this.loadRiderRoute(riderId, false);
        }
    },

    async focusRider(riderId, lat, lng) {
        if (!this.map) return false;

        let latitude = parseFloat(lat);
        let longitude = parseFloat(lng);

        if (!latitude || !longitude) {
            const rider = this.lastAllRiders.find(r => parseInt(r.id) === parseInt(riderId));
            if (rider?.latitude && rider?.longitude) {
                latitude = parseFloat(rider.latitude);
                longitude = parseFloat(rider.longitude);
            } else if (this.markers[riderId]) {
                const pos = this.markers[riderId].getLatLng();
                latitude = pos.lat;
                longitude = pos.lng;
            }
        }

        this.selectedRiderId = parseInt(riderId);

        const rider = this.lastAllRiders.find(r => parseInt(r.id) === parseInt(riderId));
        if (rider?.active_parcel_id) {
            this._activeParcelId = parseInt(rider.active_parcel_id);
        }

        document.querySelectorAll('.rider-list-item, .online-rider-item').forEach(el => {
            el.classList.toggle('active', parseInt(el.dataset.riderId) === parseInt(riderId));
        });

        await this.loadRiderRoute(riderId, true);

        if (!latitude || !longitude) {
            Toast.warning('Rider location not available yet.');
            return false;
        }

        this.map.flyTo([latitude, longitude], 16, { duration: 0.8 });

        if (this.markers[riderId]) {
            setTimeout(() => this.markers[riderId].openPopup(), 600);
        }

        const hint = document.getElementById('selectedRiderHint');
        if (hint) hint.classList.add('hidden');

        return true;
    },

    bindRiderClickHandlers() {
        document.querySelectorAll('.rider-list-item[data-rider-id], .online-rider-item[data-rider-id]').forEach(item => {
            if (item.dataset.bound === '1') return;
            item.dataset.bound = '1';
            item.addEventListener('click', () => {
                this.focusRider(item.dataset.riderId, item.dataset.lat, item.dataset.lng);
            });
        });
    },

    updateRiderList(riders) {
        const list = document.getElementById('riderList');
        if (!list) return;

        if (!riders.length) {
            list.innerHTML = '';
            return;
        }

        list.innerHTML = riders.map(r => {
            const hasGps = r.latitude && r.longitude;
            const active = parseInt(r.id) === this.selectedRiderId ? ' active' : '';
            return `
            <div class="rider-list-item${active} ${hasGps ? '' : 'waiting-gps'}"
                 data-rider-id="${r.id}"
                 data-lat="${hasGps ? r.latitude : ''}"
                 data-lng="${hasGps ? r.longitude : ''}">
                <span class="rider-name">${this.esc(r.full_name)}</span>
                <span class="rider-meta">${r.rider_code} — ${r.is_online ? '🟢 Online' : '⚫ Offline'}</span>
            </div>`;
        }).join('');
    },

    fitAllMarkers() {
        const layers = [...Object.values(this.markers)];
        if (this.routeLine) layers.push(this.routeLine);
        if (!layers.length) {
            Toast.warning('No riders on map to fit.');
            this.map.setView([14.5995, 120.9842], 12);
            return;
        }
        const group = L.featureGroup(layers);
        this.map.fitBounds(group.getBounds().pad(0.15));
    },

    replayRoute() {
        if (!this._routePoints || this._routePoints.length < 2) {
            Toast.warning('No route to replay.');
            return;
        }

        let index = 0;
        const points = this._routePoints;
        if (this._replayInterval) clearInterval(this._replayInterval);

        if (!this.replayMarker) {
            this.replayMarker = L.marker(points[0], { icon: this.createRiderIcon(true) }).addTo(this.map);
        } else {
            this.replayMarker.setLatLng(points[0]);
            if (!this.map.hasLayer(this.replayMarker)) {
                this.replayMarker.addTo(this.map);
            }
        }

        this._replayInterval = setInterval(() => {
            if (index >= points.length) {
                clearInterval(this._replayInterval);
                this._replayInterval = null;
                Toast.success('Route replay complete.');
                return;
            }
            this.replayMarker.setLatLng(points[index]);
            this.map.panTo(points[index]);
            index++;
        }, 400);
    },

    esc(str) {
        const d = document.createElement('div');
        d.textContent = str || '';
        return d.innerHTML;
    },

    initRiderDetailMap(el) {
        const riderId = parseInt(el.dataset.riderId);
        const isOnline = el.dataset.online === '1';
        const name = el.dataset.name || 'Rider';
        const lat = parseFloat(el.dataset.lat);
        const lng = parseFloat(el.dataset.lng);
        const hasCoords = !Number.isNaN(lat) && !Number.isNaN(lng);

        this.selectedRiderId = riderId;
        this.apiUrl = el.dataset.api;
        this._activeParcelId = parseInt(el.dataset.activeParcelId) || null;

        const center = hasCoords ? [lat, lng] : [14.5995, 120.9842];
        const zoom = hasCoords ? 15 : 12;

        this.map = L.map(el, { minZoom: 5, maxZoom: 19 }).setView(center, zoom);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap'
        }).addTo(this.map);

        setTimeout(() => this.map.invalidateSize(), 100);

        if (hasCoords) {
            this.markers[riderId] = L.marker([lat, lng], {
                icon: this.createRiderIcon(isOnline)
            }).addTo(this.map).bindPopup(`<strong>${name}</strong><br>Current position`).openPopup();
        }

        this.loadRiderRoute(riderId, true);

        if (isOnline) {
            this.detailRefreshInterval = setInterval(() => this.refreshRiderDetail(riderId), 10000);
        }
    },

    async refreshRiderDetail(riderId) {
        if (!this.apiUrl) return;

        try {
            const params = this.getRouteQueryParams();
            if (!params) return;

            const res = await Ajax.get(this.getRiderApiUrl(riderId, params));
            if (!res.success) return;

            this.applyRouteResponseMeta(res);

            const points = typeof RouteUtils !== 'undefined'
                ? RouteUtils.historyToPoints(res.history)
                : (res.history || [])
                    .map(h => [parseFloat(h.latitude), parseFloat(h.longitude)])
                    .filter(p => !Number.isNaN(p[0]) && !Number.isNaN(p[1]));

            if (points.length >= 2) {
                this.drawRouteOverlay(points, false);
            }

            const rider = res.rider;
            if (rider?.last_latitude && rider?.last_longitude) {
                const latLng = [parseFloat(rider.last_latitude), parseFloat(rider.last_longitude)];
                if (this.markers[riderId]) {
                    this.markers[riderId].setLatLng(latLng);
                } else {
                    this.markers[riderId] = L.marker(latLng, {
                        icon: this.createRiderIcon(true)
                    }).addTo(this.map).bindPopup('Current position');
                }

                const coordsEl = document.getElementById('liveCoords');
                if (coordsEl) {
                    coordsEl.textContent = `${latLng[0].toFixed(5)}, ${latLng[1].toFixed(5)}`;
                }
            }

            const info = document.getElementById('routePointCount');
            if (info) {
                info.textContent = points.length ? String(points.length) : '';
            }
        } catch (e) {
            console.error('Rider detail refresh failed:', e);
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    if (typeof L !== 'undefined') {
        MapManager.init();
    }
});
