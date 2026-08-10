/**
 * Smart location autofill — address search, GPS reverse geocode, saved contacts.
 */
const LocationAutofill = {
    searchTimer: null,
    activeDropdown: null,

    init() {
        document.querySelectorAll('[data-location-autofill]').forEach(wrapper => {
            this.bindAddressField(wrapper);
        });

        document.querySelectorAll('[data-contact-autofill]').forEach(wrapper => {
            this.bindContactField(wrapper);
        });

        document.addEventListener('click', e => {
            if (!e.target.closest('.location-autofill-wrap, .contact-autofill-wrap')) {
                this.hideAllSuggestions();
            }
        });
    },

    fetchWithTimeout(url, ms = 12000, options = {}) {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), ms);
        return fetch(url, {
            ...options,
            signal: controller.signal,
            headers: {
                Accept: 'application/json',
                ...(options.headers || {})
            }
        }).finally(() => clearTimeout(timer));
    },

    hideAllSuggestions() {
        document.querySelectorAll('.location-suggestions').forEach(el => el.classList.add('hidden'));
        this.activeDropdown = null;
    },

    showSuggestions(dropdown, items, onSelect) {
        if (!dropdown) return;

        if (!items.length) {
            dropdown.classList.add('hidden');
            dropdown.innerHTML = '';
            return;
        }

        dropdown.innerHTML = items.map((item, index) => `
            <button type="button" class="location-suggestion-item" data-index="${index}">
                <span class="location-suggestion-title">${this.esc(item.title)}</span>
                ${item.subtitle ? `<span class="location-suggestion-sub">${this.esc(item.subtitle)}</span>` : ''}
            </button>
        `).join('');

        dropdown.classList.remove('hidden');
        this.activeDropdown = dropdown;

        dropdown.querySelectorAll('.location-suggestion-item').forEach(btn => {
            btn.addEventListener('click', () => {
                const item = items[parseInt(btn.dataset.index, 10)];
                if (item) onSelect(item);
                this.hideAllSuggestions();
            });
        });
    },

    bindAddressField(wrapper) {
        const input = wrapper.querySelector('[data-location-input]');
        const dropdown = wrapper.querySelector('.location-suggestions');
        const gpsBtn = wrapper.querySelector('[data-use-gps]');
        if (!input || !dropdown) return;

        input.addEventListener('input', () => {
            clearTimeout(this.searchTimer);
            const value = input.value.trim();
            if (value.length < 3) {
                dropdown.classList.add('hidden');
                return;
            }

            this.searchTimer = setTimeout(() => this.searchAddresses(value, dropdown, input), 350);
        });

        input.addEventListener('focus', () => {
            const value = input.value.trim();
            if (value.length >= 3) {
                this.searchAddresses(value, dropdown, input);
            }
        });

        if (gpsBtn) {
            gpsBtn.addEventListener('click', () => this.fillFromGps(input, gpsBtn));
        }
    },

    bindContactField(wrapper) {
        const nameInput = wrapper.querySelector('[data-contact-name]');
        const phoneInput = wrapper.querySelector('[data-contact-phone]');
        const addressInput = wrapper.querySelector('[data-contact-address]')
            || document.querySelector(wrapper.dataset.addressTarget || '');
        const dropdown = wrapper.querySelector('.location-suggestions');
        const type = wrapper.dataset.contactAutofill || 'receiver';

        if (!nameInput || !dropdown) return;

        const loadSuggestions = () => {
            const query = nameInput.value.trim();
            if (query.length < 2) {
                dropdown.classList.add('hidden');
                return;
            }
            this.loadContactSuggestions(type, query, dropdown, item => {
                nameInput.value = item.name || '';
                if (phoneInput && item.phone && item.phone !== '-') {
                    phoneInput.value = item.phone;
                }
                if (addressInput && item.address && item.address !== '-') {
                    addressInput.value = item.address;
                    addressInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });
        };

        nameInput.addEventListener('input', () => {
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(loadSuggestions, 250);
        });

        nameInput.addEventListener('focus', loadSuggestions);
    },

    async loadContactSuggestions(type, query, dropdown, onSelect) {
        try {
            const res = await Ajax.get(
                `${Ajax.getBaseUrl()}/api/address_suggestions.php?type=${encodeURIComponent(type)}&q=${encodeURIComponent(query)}`
            );
            if (!res.success) return;

            const items = (res.suggestions || []).map(row => ({
                title: row.label || row.name,
                subtitle: row.address,
                name: row.name,
                phone: row.phone,
                address: row.address
            }));

            this.showSuggestions(dropdown, items, onSelect);
        } catch (e) {
            console.error('Contact suggestions failed:', e);
        }
    },

    async searchAddresses(query, dropdown, input) {
        try {
            const url = 'https://nominatim.openstreetmap.org/search?format=json&limit=6&addressdetails=1&q='
                + encodeURIComponent(query);

            const response = await this.fetchWithTimeout(url, 12000);
            const data = await response.json();
            if (!Array.isArray(data)) return;

            const items = data.map(row => ({
                title: row.display_name,
                subtitle: this.formatAddressParts(row.address),
                value: row.display_name
            }));

            this.showSuggestions(dropdown, items, item => {
                input.value = item.value;
                input.dispatchEvent(new Event('input', { bubbles: true }));
            });
        } catch (e) {
            console.error('Address search failed:', e);
        }
    },

    async fillFromGps(input, button) {
        if (!navigator.geolocation) {
            Toast.warning('GPS is not supported on this device.');
            return;
        }

        const originalText = button.textContent;
        button.disabled = true;
        button.textContent = 'Locating...';

        try {
            const position = await new Promise((resolve, reject) => {
                navigator.geolocation.getCurrentPosition(resolve, reject, {
                    enableHighAccuracy: true,
                    timeout: 15000,
                    maximumAge: 0
                });
            });

            const { latitude, longitude } = position.coords;
            const address = await this.reverseGeocode(latitude, longitude);
            if (!address) {
                Toast.warning('Could not resolve address from GPS.');
                return;
            }

            input.value = address;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            Toast.success('Address filled from your location.');
        } catch (e) {
            Toast.warning('Unable to get current location. Please allow GPS permission.');
        } finally {
            button.disabled = false;
            button.textContent = originalText;
        }
    },

    async reverseGeocode(lat, lng) {
        const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${encodeURIComponent(lat)}&lon=${encodeURIComponent(lng)}&zoom=18&addressdetails=1`;
        const response = await this.fetchWithTimeout(url, 12000);
        const data = await response.json();
        if (!data) return '';

        const parts = this.formatAddressParts(data.address);
        return parts || data.display_name || '';
    },

    formatAddressParts(address) {
        if (!address || typeof address !== 'object') return '';

        const segments = [
            address.house_number,
            address.road || address.pedestrian || address.footway,
            address.neighbourhood || address.suburb || address.village,
            address.city || address.town || address.municipality,
            address.state,
            address.postcode,
            address.country
        ].filter(Boolean);

        return [...new Set(segments)].join(', ');
    },

    esc(str) {
        const el = document.createElement('div');
        el.textContent = str || '';
        return el.innerHTML;
    }
};

document.addEventListener('DOMContentLoaded', () => LocationAutofill.init());
