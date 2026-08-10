/**
 * AJAX Helper Module
 */
const Ajax = {
    getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    },

    getBaseUrl() {
        return document.body.dataset.baseUrl || '';
    },

    async request(url, options = {}) {
        const timeoutMs = options.timeout || 20000;
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), timeoutMs);

        const defaults = {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': this.getCsrfToken()
            },
            signal: controller.signal
        };

        if (options.body && !(options.body instanceof FormData)) {
            defaults.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(options.body);
        }

        const { timeout, ...fetchOptions } = options;
        const config = {
            ...defaults,
            ...fetchOptions,
            headers: { ...defaults.headers, ...(fetchOptions.headers || {}) }
        };

        if (config.body instanceof FormData) {
            config.body.append('csrf_token', this.getCsrfToken());
        }

        try {
            const response = await fetch(url, config);
            let data;
            const contentType = response.headers.get('content-type') || '';
            if (contentType.includes('application/json')) {
                data = await response.json();
            } else {
                throw new Error('Server returned an invalid response.');
            }

            if (response.status === 401 && data.redirect) {
                window.location.href = data.redirect;
                return data;
            }

            return data;
        } catch (err) {
            if (err.name === 'AbortError') {
                throw new Error('Request timed out. Please check your connection.');
            }
            throw err;
        } finally {
            clearTimeout(timeoutId);
        }
    },

    get(url, params = {}, options = {}) {
        const query = new URLSearchParams(params).toString();
        const fullUrl = query ? `${url}?${query}` : url;
        return this.request(fullUrl, { method: 'GET', ...options });
    },

    post(url, body = {}, options = {}) {
        return this.request(url, { method: 'POST', body, ...options });
    }
};
