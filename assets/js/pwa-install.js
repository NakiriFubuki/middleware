/**
 * PWA install prompt — login page only
 */
const PwaInstall = {
    deferredPrompt: null,
    showTimer: null,
    INSTALLED_KEY: 'pdms_pwa_installed',
    bootstrapDone: false,

    init() {
        const overlay = document.getElementById('pwaInstallOverlay');
        if (!overlay) {
            return;
        }

        this.bindModal();
        this.registerServiceWorker();

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            this.deferredPrompt = e;

            if (this.bootstrapDone && !this.isStandalone()) {
                this.scheduleShow('native');
            }
        });

        window.addEventListener('appinstalled', () => {
            this.markInstalled();
            this.clearTimers();
            this.deferredPrompt = null;
            this.hideModal();
        });

        this.bootstrap();
    },

    async bootstrap() {
        if (await this.shouldSkipInstallPrompt()) {
            this.bootstrapDone = true;
            return;
        }

        if (this.isIos()) {
            this.scheduleShow('ios');
            this.bootstrapDone = true;
            return;
        }

        await this.waitForInstallPrompt(3000);

        if (await this.shouldSkipInstallPrompt()) {
            this.bootstrapDone = true;
            return;
        }

        // Only show when browser offers install — if no prompt, app is likely already installed
        if (this.deferredPrompt) {
            this.scheduleShow('native');
        }

        this.bootstrapDone = true;
    },

    async shouldSkipInstallPrompt() {
        if (this.isStandalone()) {
            this.markInstalled();
            return true;
        }

        if (this.hasInstalledFlag()) {
            return true;
        }

        if (await this.detectInstalledFromBrowser()) {
            this.markInstalled();
            return true;
        }

        return false;
    },

    waitForInstallPrompt(timeoutMs) {
        if (this.deferredPrompt) {
            return Promise.resolve(true);
        }

        return new Promise((resolve) => {
            const started = Date.now();

            const onPrompt = (e) => {
                e.preventDefault();
                this.deferredPrompt = e;
                window.removeEventListener('beforeinstallprompt', onPrompt);
                resolve(true);
            };

            window.addEventListener('beforeinstallprompt', onPrompt);

            const tick = () => {
                if (this.deferredPrompt) {
                    window.removeEventListener('beforeinstallprompt', onPrompt);
                    resolve(true);
                    return;
                }
                if (Date.now() - started >= timeoutMs) {
                    window.removeEventListener('beforeinstallprompt', onPrompt);
                    resolve(false);
                    return;
                }
                setTimeout(tick, 100);
            };

            tick();
        });
    },

    getBaseUrl() {
        const base = (document.body && document.body.dataset.baseUrl) || '';
        return base.replace(/\/$/, '');
    },

    getIconUrl() {
        const fromDom = document.getElementById('pwaInstallIcon')?.getAttribute('data-src');
        if (fromDom) {
            return fromDom;
        }
        return this.getBaseUrl() + '/assets/icons/pwa-icon.svg';
    },

    registerServiceWorker() {
        if (!('serviceWorker' in navigator)) {
            return;
        }

        const swUrl = this.getBaseUrl() + '/sw.js';
        const scope = this.getBaseUrl() + '/';

        window.addEventListener('load', () => {
            navigator.serviceWorker.register(swUrl, { scope }).catch((err) => {
                console.warn('PWA service worker registration failed:', err);
            });
        });
    },

    isStandalone() {
        return window.matchMedia('(display-mode: standalone)').matches
            || window.matchMedia('(display-mode: fullscreen)').matches
            || window.navigator.standalone === true;
    },

    isIos() {
        return /iphone|ipad|ipod/i.test(navigator.userAgent);
    },

    hasInstalledFlag() {
        try {
            return localStorage.getItem(this.INSTALLED_KEY) === '1';
        } catch (e) {
            return false;
        }
    },

    markInstalled() {
        try {
            localStorage.setItem(this.INSTALLED_KEY, '1');
        } catch (e) {
            // ignore
        }
    },

    async detectInstalledFromBrowser() {
        if ('getInstalledRelatedApps' in navigator) {
            try {
                const related = await navigator.getInstalledRelatedApps();
                if (related && related.length > 0) {
                    return true;
                }
            } catch (e) {
                // ignore
            }
        }

        return false;
    },

    scheduleShow(mode) {
        this.clearTimers();
        const delay = mode === 'native' ? 900 : 1400;
        this.showTimer = setTimeout(async () => {
            if (await this.shouldSkipInstallPrompt()) {
                return;
            }
            if (mode === 'native' && !this.deferredPrompt) {
                return;
            }
            this.showModal(mode);
        }, delay);
    },

    clearTimers() {
        clearTimeout(this.showTimer);
        this.showTimer = null;
    },

    bindModal() {
        document.getElementById('pwaInstallBtn')?.addEventListener('click', () => this.install());
        document.getElementById('pwaInstallDismiss')?.addEventListener('click', () => this.dismiss());
        document.getElementById('pwaInstallOverlay')?.addEventListener('click', (e) => {
            if (e.target.id === 'pwaInstallOverlay') {
                this.dismiss();
            }
        });
    },

    async showModal(mode) {
        if (await this.shouldSkipInstallPrompt()) {
            return;
        }

        if (mode === 'native' && !this.deferredPrompt) {
            return;
        }

        const overlay = document.getElementById('pwaInstallOverlay');
        const body = document.getElementById('pwaInstallBody');
        const installBtn = document.getElementById('pwaInstallBtn');
        const icon = document.getElementById('pwaInstallIcon');

        if (!overlay || overlay.classList.contains('is-visible')) {
            return;
        }

        if (icon) {
            icon.src = this.getIconUrl();
        }

        if (body) {
            if (mode === 'ios') {
                body.innerHTML = 'Add <strong>PDMS</strong> to your Home Screen for quick access:'
                    + '<ol class="pwa-install-ios-steps">'
                    + '<li>Tap the <strong>Share</strong> button in Safari</li>'
                    + '<li>Select <strong>Add to Home Screen</strong></li>'
                    + '<li>Tap <strong>Add</strong></li>'
                    + '</ol>';
                if (installBtn) {
                    installBtn.textContent = 'Got It';
                    installBtn.disabled = false;
                }
            } else {
                body.textContent = 'Install PDMS on your phone or computer for one-tap access without typing the URL next time.';
                if (installBtn) {
                    installBtn.textContent = 'Install Now';
                    installBtn.disabled = false;
                }
            }
        }

        overlay.dataset.mode = mode;
        overlay.removeAttribute('hidden');
        overlay.classList.add('is-visible');
        document.body.style.overflow = 'hidden';
    },

    setInstallPending() {
        const body = document.getElementById('pwaInstallBody');
        const installBtn = document.getElementById('pwaInstallBtn');

        if (body) {
            body.textContent = 'Please click "Install" in the browser prompt. You can continue to login after installation.';
        }
        if (installBtn) {
            installBtn.textContent = 'Waiting for confirmation...';
            installBtn.disabled = true;
        }
    },

    resetInstallButton() {
        const installBtn = document.getElementById('pwaInstallBtn');
        if (!installBtn) {
            return;
        }
        installBtn.textContent = 'Install Now';
        installBtn.disabled = false;
    },

    hideModal() {
        const overlay = document.getElementById('pwaInstallOverlay');
        if (!overlay) {
            return;
        }
        overlay.classList.remove('is-visible');
        overlay.setAttribute('hidden', '');
        document.body.style.overflow = '';
        this.resetInstallButton();
    },

    async install() {
        const overlay = document.getElementById('pwaInstallOverlay');
        const mode = overlay?.dataset.mode || 'native';
        const body = document.getElementById('pwaInstallBody');
        const installBtn = document.getElementById('pwaInstallBtn');

        if (await this.shouldSkipInstallPrompt()) {
            this.hideModal();
            return;
        }

        if (mode === 'ios') {
            return;
        }

        if (!this.deferredPrompt) {
            if (body) {
                body.textContent = 'PDMS is already installed. Open it from your desktop or home screen, or click "Maybe Later" to continue login.';
            }
            if (installBtn) {
                installBtn.textContent = 'Installed';
                installBtn.disabled = true;
            }
            this.markInstalled();
            return;
        }

        this.setInstallPending();

        try {
            await this.deferredPrompt.prompt();
            const choice = await this.deferredPrompt.userChoice;

            if (choice.outcome === 'accepted') {
                this.markInstalled();
                if (body) {
                    body.textContent = 'Installation successful! You can open PDMS from your desktop or home screen. Click "Maybe Later" to continue login.';
                }
                if (installBtn) {
                    installBtn.textContent = 'Installed';
                    installBtn.disabled = true;
                }
            } else if (body) {
                body.textContent = 'Installation was canceled. Click "Install Now" to try again, or "Maybe Later" to continue login.';
                this.resetInstallButton();
            }
        } catch (e) {
            console.warn('PWA install prompt failed:', e);
            if (body) {
                body.textContent = 'Unable to open the install dialog. Use your browser menu to install the app, or click "Maybe Later" to continue login.';
            }
            this.resetInstallButton();
        } finally {
            this.deferredPrompt = null;
        }
    },

    dismiss() {
        this.hideModal();
    }
};

document.addEventListener('DOMContentLoaded', () => PwaInstall.init());
