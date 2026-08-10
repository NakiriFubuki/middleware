/**
 * Main Application JS
 */
document.addEventListener('DOMContentLoaded', () => {
    initAppNav();
    initModals();
    initLogout();
    hideLoading();
});

window.addEventListener('load', () => {
    hideLoading();
});

function initAppNav() {
    const nav = document.getElementById('appNav');
    const toggle = document.getElementById('mobileMenuBtn');
    const backdrop = document.getElementById('sidebarBackdrop');

    if (!nav || !toggle) return;

    function setNavOpen(open) {
        nav.classList.toggle('open', open);
        backdrop?.classList.toggle('active', open);
        document.body.classList.toggle('sidebar-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (backdrop) {
            backdrop.setAttribute('aria-hidden', open ? 'false' : 'true');
        }
    }

    toggle.addEventListener('click', () => {
        setNavOpen(!nav.classList.contains('open'));
    });

    backdrop?.addEventListener('click', () => setNavOpen(false));

    nav.querySelectorAll('.app-nav-link').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                setNavOpen(false);
            }
        });
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) {
            setNavOpen(false);
        }
    });
}

function initModals() {
    document.querySelectorAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click', () => {
            btn.closest('.modal')?.classList.remove('active');
        });
    });

    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) modal.classList.remove('active');
        });
    });
}

function initLogout() {
    document.querySelectorAll('[data-logout]').forEach(link => {
        link.addEventListener('click', async (e) => {
            e.preventDefault();
            const fallbackUrl = link.getAttribute('href') || (Ajax.getBaseUrl() + '/logout.php');
            try {
                const res = await Ajax.post(Ajax.getBaseUrl() + '/api/logout.php', {});
                window.location.href = res.redirect || Ajax.getBaseUrl() + '/login.php';
            } catch (err) {
                window.location.href = fallbackUrl;
            }
        });
    });
}

function showLoading() {
    document.getElementById('loading-overlay')?.classList.remove('hidden');
}

function hideLoading() {
    document.getElementById('loading-overlay')?.classList.add('hidden');
}
