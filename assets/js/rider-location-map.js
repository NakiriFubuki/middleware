/**
 * Rider Location Map - current position only (no history trail)
 */
const RiderLocationMap = {
    map: null,
    marker: null,
    hasCentered: false,
    defaultCenter: [14.5995, 120.9842],

    init() {
        const el = document.getElementById('riderLocationMap');
        if (!el || typeof L === 'undefined') return;

        const lat = parseFloat(el.dataset.lat);
        const lng = parseFloat(el.dataset.lng);
        const hasSaved = !Number.isNaN(lat) && !Number.isNaN(lng) && lat !== 0 && lng !== 0;
        const center = hasSaved ? [lat, lng] : this.defaultCenter;
        const zoom = hasSaved ? 16 : 12;

        this.map = L.map(el, {
            zoomControl: true,
            scrollWheelZoom: true,
            minZoom: 5,
            maxZoom: 19
        }).setView(center, zoom);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(this.map);

        setTimeout(() => this.map.invalidateSize(), 150);

        window.addEventListener('resize', () => {
            if (this.map) this.map.invalidateSize();
        });

        if (hasSaved) {
            this.setMarker(lat, lng);
            this.hasCentered = true;
        }
    },

    createIcon() {
        return L.divIcon({
            className: 'rider-marker-icon',
            html: '<div class="rider-marker online">📍</div>',
            iconSize: [36, 36],
            iconAnchor: [18, 18]
        });
    },

    setMarker(lat, lng) {
        const latLng = [lat, lng];
        if (!this.marker) {
            this.marker = L.marker(latLng, { icon: this.createIcon() }).addTo(this.map);
        } else {
            this.marker.setLatLng(latLng);
        }
    },

    updatePosition(lat, lng, accuracy, shouldCenter) {
        if (!this.map) return;

        this.setMarker(lat, lng);

        if (shouldCenter || !this.hasCentered) {
            this.map.setView([lat, lng], 16, { animate: true });
            this.hasCentered = true;
        }

        this.marker.bindPopup(
            `<strong>Your location</strong><br>${lat.toFixed(5)}, ${lng.toFixed(5)}` +
            (accuracy ? `<br>±${Math.round(accuracy)}m` : '')
        );
    },

    resetSession() {
        this.hasCentered = false;
    }
};

document.addEventListener('DOMContentLoaded', () => RiderLocationMap.init());
